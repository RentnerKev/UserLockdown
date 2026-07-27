<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Policy;

use InvalidArgumentException;

final readonly class PermissionSet {
	private const ALL_MASK = 63;
	private const FILE_DEPENDENT_MASK = Permission::WriteFiles->value
		| Permission::DeleteFiles->value
		| Permission::ShareFiles->value;

	/** @var list<string> */
	private const API_KEYS = [
		'viewFiles',
		'writeFiles',
		'deleteFiles',
		'shareFiles',
		'changePassword',
		'fullAccess',
	];

	private function __construct(
		private int $mask,
	) {
	}

	/**
	 * @param array<string, mixed> $permissions
	 */
	public static function fromArray(array $permissions): self {
		$keys = array_keys($permissions);
		sort($keys);
		$expectedKeys = self::API_KEYS;
		sort($expectedKeys);
		if ($keys !== $expectedKeys) {
			throw new InvalidArgumentException('invalid_permissions');
		}

		foreach (self::API_KEYS as $key) {
			if (!is_bool($permissions[$key])) {
				throw new InvalidArgumentException('invalid_permissions');
			}
		}

		if ($permissions['fullAccess']) {
			return self::fullAccess();
		}

		if (
			!$permissions['viewFiles']
			&& (
				$permissions['writeFiles']
				|| $permissions['deleteFiles']
				|| $permissions['shareFiles']
			)
		) {
			throw new InvalidArgumentException('invalid_permissions');
		}

		$mask = 0;
		foreach (self::API_KEYS as $key) {
			if ($permissions[$key]) {
				$mask |= self::permissionForKey($key)->value;
			}
		}

		return new self($mask);
	}

	public static function fromMask(int $mask): self {
		if ($mask < 0 || ($mask & ~self::ALL_MASK) !== 0) {
			return self::blocked();
		}

		if (($mask & Permission::FullAccess->value) !== 0) {
			return self::fullAccess();
		}

		if (
			($mask & Permission::ViewFiles->value) === 0
			&& ($mask & self::FILE_DEPENDENT_MASK) !== 0
		) {
			return self::blocked();
		}

		return new self($mask);
	}

	public static function blocked(): self {
		return new self(0);
	}

	public static function readOnly(): self {
		return new self(Permission::ViewFiles->value);
	}

	public static function fullAccess(): self {
		return new self(self::ALL_MASK);
	}

	public function allows(Permission $permission): bool {
		return ($this->mask & $permission->value) !== 0;
	}

	public function isFullAccess(): bool {
		return $this->allows(Permission::FullAccess);
	}

	/**
	 * @return array{
	 *     viewFiles: bool,
	 *     writeFiles: bool,
	 *     deleteFiles: bool,
	 *     shareFiles: bool,
	 *     changePassword: bool,
	 *     fullAccess: bool,
	 * }
	 */
	public function toArray(): array {
		return [
			'viewFiles' => $this->allows(Permission::ViewFiles),
			'writeFiles' => $this->allows(Permission::WriteFiles),
			'deleteFiles' => $this->allows(Permission::DeleteFiles),
			'shareFiles' => $this->allows(Permission::ShareFiles),
			'changePassword' => $this->allows(Permission::ChangePassword),
			'fullAccess' => $this->allows(Permission::FullAccess),
		];
	}

	public function toMask(): int {
		return $this->mask;
	}

	private static function permissionForKey(string $key): Permission {
		return match ($key) {
			'viewFiles' => Permission::ViewFiles,
			'writeFiles' => Permission::WriteFiles,
			'deleteFiles' => Permission::DeleteFiles,
			'shareFiles' => Permission::ShareFiles,
			'changePassword' => Permission::ChangePassword,
			'fullAccess' => Permission::FullAccess,
			default => throw new InvalidArgumentException('invalid_permissions'),
		};
	}
}
