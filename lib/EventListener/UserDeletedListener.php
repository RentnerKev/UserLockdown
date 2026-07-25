<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\EventListener;

use OCA\UserLockdown\Service\RestrictedUserService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;
use Psr\Log\LoggerInterface;
use Throwable;

/** @implements IEventListener<UserDeletedEvent> */
final class UserDeletedListener implements IEventListener {
	public function __construct(
		private readonly RestrictedUserService $restrictedUserService,
		private readonly LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof UserDeletedEvent) {
			return;
		}

		try {
			$this->restrictedUserService->removeRestrictedUser($event->getUid());
		} catch (Throwable $exception) {
			// UserDeletedEvent fires late in core deletion. Never leave core in a
			// partially deleted state because cleanup of this auxiliary row failed.
			$this->logger->error('Could not remove a deleted user from User Lockdown.', [
				'app' => 'user_lockdown',
				'userId' => $event->getUid(),
				'exception' => $exception,
			]);
		}
	}
}
