<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Service;

use OCA\UserLockdown\Policy\PermissionSet;
use OCP\IGroupManager;
use OCP\IUserSession;

final class RestrictionContext {
	private bool $resolved = false;
	private ?string $restrictedUserId = null;
	private ?PermissionSet $permissionSet = null;

	public function __construct(
		private readonly IUserSession $userSession,
		private readonly IGroupManager $groupManager,
		private readonly RestrictedUserService $restrictedUserService,
	) {
	}

	public function getRestrictedUserId(): ?string {
		$this->resolve();

		return $this->restrictedUserId;
	}

	public function getPermissionSet(): ?PermissionSet {
		$this->resolve();

		return $this->permissionSet;
	}

	private function resolve(): void {
		if ($this->resolved) {
			return;
		}

		$user = $this->userSession->getUser();
		if ($user === null) {
			return;
		}

		$this->resolved = true;
		$userId = $user->getUID();
		if ($this->groupManager->isAdmin($userId)) {
			return;
		}

		$permissionSet = $this->restrictedUserService->getPermissionSet($userId);
		if ($permissionSet === null || $permissionSet->isFullAccess()) {
			return;
		}

		$this->restrictedUserId = $userId;
		$this->permissionSet = $permissionSet;
	}
}
