<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Service;

use OCP\IGroupManager;
use OCP\IUserSession;

final class RestrictionContext {
	private bool $resolved = false;
	private ?string $restrictedUserId = null;

	public function __construct(
		private readonly IUserSession $userSession,
		private readonly IGroupManager $groupManager,
		private readonly RestrictedUserService $restrictedUserService,
	) {
	}

	public function isCurrentUserRestricted(): bool {
		return $this->getRestrictedUserId() !== null;
	}

	public function getRestrictedUserId(): ?string {
		if ($this->resolved) {
			return $this->restrictedUserId;
		}

		$user = $this->userSession->getUser();
		if ($user === null) {
			return null;
		}

		$this->resolved = true;
		$userId = $user->getUID();
		if ($this->groupManager->isAdmin($userId)) {
			return null;
		}

		if ($this->restrictedUserService->isRestricted($userId)) {
			$this->restrictedUserId = $userId;
		}

		return $this->restrictedUserId;
	}
}
