<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Service;

use DomainException;
use InvalidArgumentException;
use OCA\UserLockdown\Policy\Permission;
use OCA\UserLockdown\Policy\PermissionPreset;
use OCA\UserLockdown\Policy\PermissionSet;
use OCP\IAppConfig;
use OCP\Security\ISecureRandom;

final class PermissionSettingsService {
	public const ERROR_DUPLICATE_PRESET_NAME = 'duplicate_preset_name';
	public const ERROR_IMMUTABLE_PRESET = 'immutable_preset';
	public const ERROR_INVALID_PRESET = 'invalid_preset';
	public const ERROR_PRESET_LIMIT_REACHED = 'preset_limit_reached';
	public const ERROR_PRESET_NOT_FOUND = 'preset_not_found';

	private const APP_ID = 'user_lockdown';
	private const CUSTOM_ID_PREFIX = 'custom:';
	private const CUSTOM_KEY_PREFIX = 'permission_preset_';
	private const DEFAULT_PERMISSIONS_KEY = 'default_permissions';
	private const ID_LENGTH = 16;
	private const MAX_CUSTOM_PRESETS = 50;
	private const MAX_NAME_LENGTH = 64;

	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly ISecureRandom $secureRandom,
	) {
	}

	public function getDefaultPermissions(): PermissionSet {
		return PermissionSet::fromMask($this->appConfig->getValueInt(
			self::APP_ID,
			self::DEFAULT_PERMISSIONS_KEY,
			PermissionSet::readOnly()->toMask(),
			lazy: true,
		));
	}

	public function setDefaultPermissions(PermissionSet $permissions): void {
		$this->appConfig->setValueInt(
			self::APP_ID,
			self::DEFAULT_PERMISSIONS_KEY,
			$permissions->toMask(),
			lazy: true,
			sensitive: false,
		);
	}

	/** @return list<PermissionPreset> */
	public function getPresets(): array {
		return [
			...$this->getBuiltInPresets(),
			...$this->getCustomPresets(),
		];
	}

	public function createCustomPreset(string $name, PermissionSet $permissions): PermissionPreset {
		$name = $this->normalizeName($name);
		$customPresets = $this->getCustomPresets();
		if (count($customPresets) >= self::MAX_CUSTOM_PRESETS) {
			throw new DomainException(self::ERROR_PRESET_LIMIT_REACHED);
		}

		$this->assertUniqueName($name, $this->getPresets());
		$id = $this->generateCustomId();
		$preset = new PermissionPreset($id, $name, false, $permissions);
		$this->storeCustomPreset($preset);

		return $preset;
	}

	public function updateCustomPreset(
		string $presetId,
		string $name,
		PermissionSet $permissions,
	): PermissionPreset {
		$key = $this->customKey($presetId);
		if (!in_array($key, $this->getCustomKeys(), true)) {
			throw new DomainException(self::ERROR_PRESET_NOT_FOUND);
		}

		$name = $this->normalizeName($name);
		$this->assertUniqueName(
			$name,
			array_values(array_filter(
				$this->getPresets(),
				static fn (PermissionPreset $preset): bool => $preset->getId() !== $presetId,
			)),
		);

		$preset = new PermissionPreset($presetId, $name, false, $permissions);
		$this->storeCustomPreset($preset);

		return $preset;
	}

	public function deleteCustomPreset(string $presetId): void {
		$key = $this->customKey($presetId);
		if (!in_array($key, $this->getCustomKeys(), true)) {
			throw new DomainException(self::ERROR_PRESET_NOT_FOUND);
		}

		$this->appConfig->deleteKey(self::APP_ID, $key);
	}

	/** @return list<PermissionPreset> */
	private function getBuiltInPresets(): array {
		$viewFiles = Permission::ViewFiles->value;

		return [
			new PermissionPreset(
				'builtin:blocked',
				'Blocked',
				true,
				PermissionSet::blocked(),
			),
			new PermissionPreset(
				'builtin:read-only',
				'Read only',
				true,
				PermissionSet::readOnly(),
			),
			new PermissionPreset(
				'builtin:file-editor',
				'File editor',
				true,
				PermissionSet::fromMask($viewFiles | Permission::WriteFiles->value),
			),
			new PermissionPreset(
				'builtin:deletion-only',
				'Deletion only',
				true,
				PermissionSet::fromMask($viewFiles | Permission::DeleteFiles->value),
			),
			new PermissionPreset(
				'builtin:password-only',
				'Password only',
				true,
				PermissionSet::fromMask(Permission::ChangePassword->value),
			),
			new PermissionPreset(
				'builtin:normal-user',
				'Normal user',
				true,
				PermissionSet::fullAccess(),
			),
		];
	}

	/** @return list<PermissionPreset> */
	private function getCustomPresets(): array {
		$presets = [];
		foreach ($this->getCustomKeys() as $key) {
			$stored = $this->appConfig->getValueArray(self::APP_ID, $key, [], lazy: true);
			$name = $stored['name'] ?? null;
			$mask = $stored['permissions'] ?? null;
			if (
				!is_string($name)
				|| trim($name) === ''
				|| mb_strlen($name) > self::MAX_NAME_LENGTH
				|| !is_int($mask)
			) {
				continue;
			}

			$id = self::CUSTOM_ID_PREFIX . substr($key, strlen(self::CUSTOM_KEY_PREFIX));
			$presets[] = new PermissionPreset(
				$id,
				trim($name),
				false,
				PermissionSet::fromMask($mask),
			);
		}

		usort(
			$presets,
			static fn (PermissionPreset $left, PermissionPreset $right): int => strnatcasecmp(
				$left->getName() . $left->getId(),
				$right->getName() . $right->getId(),
			),
		);

		return $presets;
	}

	/** @return list<string> */
	private function getCustomKeys(): array {
		return array_values(array_filter(
			$this->appConfig->searchKeys(self::APP_ID, self::CUSTOM_KEY_PREFIX, true),
			static fn (string $key): bool => preg_match(
				'/^' . preg_quote(self::CUSTOM_KEY_PREFIX, '/') . '[0-9a-f]{16}$/D',
				$key,
			) === 1,
		));
	}

	private function normalizeName(string $name): string {
		$name = trim($name);
		if ($name === '' || mb_strlen($name) > self::MAX_NAME_LENGTH) {
			throw new InvalidArgumentException(self::ERROR_INVALID_PRESET);
		}

		return $name;
	}

	/** @param list<PermissionPreset> $presets */
	private function assertUniqueName(string $name, array $presets): void {
		$normalizedName = mb_strtolower($name, 'UTF-8');
		foreach ($presets as $preset) {
			if (mb_strtolower($preset->getName(), 'UTF-8') === $normalizedName) {
				throw new DomainException(self::ERROR_DUPLICATE_PRESET_NAME);
			}
		}
	}

	private function generateCustomId(): string {
		$existingKeys = array_fill_keys($this->getCustomKeys(), true);
		do {
			$token = $this->secureRandom->generate(self::ID_LENGTH, '0123456789abcdef');
			$key = self::CUSTOM_KEY_PREFIX . $token;
		} while (isset($existingKeys[$key]));

		return self::CUSTOM_ID_PREFIX . $token;
	}

	private function customKey(string $presetId): string {
		if (str_starts_with($presetId, 'builtin:')) {
			throw new DomainException(self::ERROR_IMMUTABLE_PRESET);
		}

		if (preg_match('/^custom:([0-9a-f]{16})$/D', $presetId, $matches) !== 1) {
			throw new InvalidArgumentException(self::ERROR_INVALID_PRESET);
		}

		return self::CUSTOM_KEY_PREFIX . $matches[1];
	}

	private function storeCustomPreset(PermissionPreset $preset): void {
		$this->appConfig->setValueArray(
			self::APP_ID,
			$this->customKey($preset->getId()),
			[
				'name' => $preset->getName(),
				'permissions' => $preset->getPermissions()->toMask(),
			],
			lazy: true,
			sensitive: false,
		);
	}
}
