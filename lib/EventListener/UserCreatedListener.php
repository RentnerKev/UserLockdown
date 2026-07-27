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
use OCP\User\Events\UserCreatedEvent;
use Psr\Log\LoggerInterface;
use Throwable;

/** @implements IEventListener<UserCreatedEvent> */
final class UserCreatedListener implements IEventListener {
	public function __construct(
		private readonly RestrictedUserService $restrictedUserService,
		private readonly LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof UserCreatedEvent) {
			return;
		}

		try {
			$this->restrictedUserService->removeRestrictedUser($event->getUid());
		} catch (Throwable $exception) {
			$this->logger->error('Could not clear stale User Lockdown data for a new user.', [
				'app' => 'user_lockdown',
				'userId' => $event->getUid(),
				'exception' => $exception,
			]);
		}
	}
}
