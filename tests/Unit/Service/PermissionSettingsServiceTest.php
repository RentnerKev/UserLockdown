<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Tests\Unit\Service;

use DomainException;
use InvalidArgumentException;
use OCA\UserLockdown\Policy\Permission;
use OCA\UserLockdown\Policy\PermissionPreset;
use OCA\UserLockdown\Policy\PermissionSet;
use OCA\UserLockdown\Service\PermissionSettingsService;
use OCP\IAppConfig;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\TestCase;

class PermissionSettingsServiceTest extends TestCase {
	/** @var array<string, array<string, mixed>> */
	private array $storedPresets = [];
	private int $defaultMask = 1;
	private int $generatedId = 0;
	private PermissionSettingsService $service;

	protected function setUp(): void {
		parent::setUp();

		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('searchKeys')->willReturnCallback(
			fn (string $app, string $prefix): array => array_values(array_filter(
				array_keys($this->storedPresets),
				static fn (string $key): bool => str_starts_with($key, $prefix),
			)),
		);
		$appConfig->method('getValueArray')->willReturnCallback(
			fn (string $app, string $key, array $default): array => $this->storedPresets[$key] ?? $default,
		);
		$appConfig->method('setValueArray')->willReturnCallback(
			function (string $app, string $key, array $value): bool {
				$this->storedPresets[$key] = $value;

				return true;
			},
		);
		$appConfig->method('deleteKey')->willReturnCallback(
			function (string $app, string $key): void {
				unset($this->storedPresets[$key]);
			},
		);
		$appConfig->method('getValueInt')->willReturnCallback(
			fn (): int => $this->defaultMask,
		);
		$appConfig->method('setValueInt')->willReturnCallback(
			function (string $app, string $key, int $value): bool {
				$this->defaultMask = $value;

				return true;
			},
		);

		$secureRandom = $this->createMock(ISecureRandom::class);
		$secureRandom->method('generate')->willReturnCallback(function (): string {
			$this->generatedId++;

			return str_pad(dechex($this->generatedId), 16, '0', STR_PAD_LEFT);
		});

		$this->service = new PermissionSettingsService($appConfig, $secureRandom);
	}

	public function testReadOnlyIsTheServerSideDefault(): void {
		self::assertSame(PermissionSet::readOnly()->toArray(), $this->service->getDefaultPermissions()->toArray());
	}

	public function testDefaultPermissionsCanBeChanged(): void {
		$permissions = PermissionSet::fromMask(
			Permission::ViewFiles->value | Permission::WriteFiles->value,
		);

		$this->service->setDefaultPermissions($permissions);

		self::assertSame($permissions->toArray(), $this->service->getDefaultPermissions()->toArray());
	}

	public function testBuiltInPresetsHaveStableNamesAndPolicies(): void {
		$presets = array_map(
			static fn (PermissionPreset $preset): array => $preset->toArray(),
			$this->service->getPresets(),
		);

		self::assertSame([
			['id' => 'builtin:blocked', 'name' => 'Blocked', 'builtIn' => true, 'permissions' => PermissionSet::blocked()->toArray()],
			['id' => 'builtin:read-only', 'name' => 'Read only', 'builtIn' => true, 'permissions' => PermissionSet::readOnly()->toArray()],
			['id' => 'builtin:file-editor', 'name' => 'File editor', 'builtIn' => true, 'permissions' => PermissionSet::fromMask(3)->toArray()],
			['id' => 'builtin:deletion-only', 'name' => 'Deletion only', 'builtIn' => true, 'permissions' => PermissionSet::fromMask(5)->toArray()],
			['id' => 'builtin:password-only', 'name' => 'Password only', 'builtIn' => true, 'permissions' => PermissionSet::fromMask(16)->toArray()],
			['id' => 'builtin:normal-user', 'name' => 'Normal user', 'builtIn' => true, 'permissions' => PermissionSet::fullAccess()->toArray()],
		], $presets);
	}

	public function testCustomPresetIsStoredAsPolicySnapshot(): void {
		$permissions = PermissionSet::fromMask(5);

		$preset = $this->service->createCustomPreset('  Delete files  ', $permissions);

		self::assertSame('custom:0000000000000001', $preset->getId());
		self::assertSame('Delete files', $preset->getName());
		self::assertFalse($preset->isBuiltIn());
		self::assertSame($permissions->toArray(), $preset->getPermissions()->toArray());
		self::assertSame([
			'name' => 'Delete files',
			'permissions' => 5,
		], $this->storedPresets['permission_preset_0000000000000001']);
	}

	public function testPresetNamesAreUniqueCaseInsensitively(): void {
		$this->service->createCustomPreset('Limited', PermissionSet::readOnly());

		$this->expectException(DomainException::class);
		$this->expectExceptionMessage(PermissionSettingsService::ERROR_DUPLICATE_PRESET_NAME);

		$this->service->createCustomPreset(' limited ', PermissionSet::blocked());
	}

	public function testBuiltInPresetNamesAreReserved(): void {
		$this->expectException(DomainException::class);
		$this->expectExceptionMessage(PermissionSettingsService::ERROR_DUPLICATE_PRESET_NAME);

		$this->service->createCustomPreset('READ ONLY', PermissionSet::blocked());
	}

	public function testCustomPresetCanBeUpdatedAndDeleted(): void {
		$created = $this->service->createCustomPreset('Limited', PermissionSet::readOnly());
		$updated = $this->service->updateCustomPreset(
			$created->getId(),
			'Can delete',
			PermissionSet::fromMask(5),
		);

		self::assertSame($created->getId(), $updated->getId());
		self::assertSame('Can delete', $updated->getName());
		self::assertSame(5, $updated->getPermissions()->toMask());

		$this->service->deleteCustomPreset($created->getId());

		self::assertCount(6, $this->service->getPresets());
	}

	public function testBuiltInPresetsAreImmutable(): void {
		$this->expectException(DomainException::class);
		$this->expectExceptionMessage(PermissionSettingsService::ERROR_IMMUTABLE_PRESET);

		$this->service->deleteCustomPreset('builtin:read-only');
	}

	public function testCustomPresetLimitIsEnforced(): void {
		for ($index = 0; $index < 50; $index++) {
			$this->storedPresets[sprintf('permission_preset_%016x', $index)] = [
				'name' => 'Preset ' . $index,
				'permissions' => 1,
			];
		}

		$this->expectException(DomainException::class);
		$this->expectExceptionMessage(PermissionSettingsService::ERROR_PRESET_LIMIT_REACHED);

		$this->service->createCustomPreset('One too many', PermissionSet::readOnly());
	}

	public function testInvalidStoredMaskFailsClosed(): void {
		$this->storedPresets['permission_preset_0000000000000001'] = [
			'name' => 'Corrupt',
			'permissions' => Permission::WriteFiles->value,
		];

		$customPreset = $this->service->getPresets()[6];

		self::assertSame(PermissionSet::blocked()->toArray(), $customPreset->getPermissions()->toArray());
	}

	public function testInvalidPresetNameIsRejected(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage(PermissionSettingsService::ERROR_INVALID_PRESET);

		$this->service->createCustomPreset(' ', PermissionSet::readOnly());
	}
}
