<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Policy;

final readonly class PermissionPreset {
	public function __construct(
		private string $id,
		private string $name,
		private bool $builtIn,
		private PermissionSet $permissions,
	) {
	}

	public function getId(): string {
		return $this->id;
	}

	public function getName(): string {
		return $this->name;
	}

	public function isBuiltIn(): bool {
		return $this->builtIn;
	}

	public function getPermissions(): PermissionSet {
		return $this->permissions;
	}

	/**
	 * @return array{
	 *     id: string,
	 *     name: string,
	 *     builtIn: bool,
	 *     permissions: array{
	 *         viewFiles: bool,
	 *         writeFiles: bool,
	 *         deleteFiles: bool,
	 *         shareFiles: bool,
	 *         changePassword: bool,
	 *         hideSideNavigation: bool,
	 *         fullAccess: bool,
	 *     },
	 * }
	 */
	public function toArray(): array {
		return [
			'id' => $this->id,
			'name' => $this->name,
			'builtIn' => $this->builtIn,
			'permissions' => $this->permissions->toArray(),
		];
	}
}
