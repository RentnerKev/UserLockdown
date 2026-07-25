<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Tests\Unit\EventListener;

use OCA\UserLockdown\EventListener\ShareRestrictionListener;
use OCA\UserLockdown\Service\RestrictedUserService;
use OCA\UserLockdown\Service\RestrictionContext;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Share\Events\BeforeShareCreatedEvent;
use OCP\Share\IShare;
use PHPUnit\Framework\TestCase;

class ShareRestrictionListenerTest extends TestCase {
	public function testRestrictedUserCannotCreateShare(): void {
		$context = $this->createRestrictionContext('alice', false, true);
		$l10n = $this->createMock(IL10N::class);
		$l10n->expects(self::once())
			->method('t')
			->with('This action has been disabled by your administrator.')
			->willReturn('Sharing is disabled.');
		$listener = new ShareRestrictionListener($context, $l10n);
		$event = new BeforeShareCreatedEvent($this->createMock(IShare::class));

		$listener->handle($event);

		self::assertSame('Sharing is disabled.', $event->getError());
		self::assertTrue($event->isPropagationStopped());
	}

	private function createRestrictionContext(
		string $userId,
		bool $isAdmin,
		bool $isRestricted,
	): RestrictionContext {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isAdmin')->with($userId)->willReturn($isAdmin);
		$restrictedUserService = $this->createMock(RestrictedUserService::class);
		$restrictedUserService->method('isRestricted')->with($userId)->willReturn($isRestricted);

		return new RestrictionContext($userSession, $groupManager, $restrictedUserService);
	}
}
