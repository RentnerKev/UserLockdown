<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Tests\Unit\EventListener;

use OCA\UserLockdown\EventListener\PasswordRestrictionListener;
use OCA\UserLockdown\Service\RestrictedUserService;
use OCA\UserLockdown\Service\RestrictionContext;
use OCP\HintException;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserSession;
use OCP\User\Events\BeforePasswordUpdatedEvent;
use PHPUnit\Framework\TestCase;

class PasswordRestrictionListenerTest extends TestCase {
	public function testRestrictedUserCannotChangeOwnPassword(): void {
		$context = $this->createRestrictionContext('alice', false, true);
		$l10n = $this->createMock(IL10N::class);
		$l10n->expects(self::once())
			->method('t')
			->with('This action has been disabled by your administrator.')
			->willReturn('Password changes are disabled.');
		$listener = new PasswordRestrictionListener($context, $l10n);
		$event = new BeforePasswordUpdatedEvent($this->createUser('alice'), 'new-password');

		try {
			$listener->handle($event);
			self::fail('A restricted user changing their own password must be rejected.');
		} catch (HintException $exception) {
			self::assertSame('Password changes are disabled.', $exception->getMessage());
			self::assertSame('Password changes are disabled.', $exception->getHint());
		}
	}

	public function testAdministratorCanChangeRestrictedUsersPassword(): void {
		$context = $this->createRestrictionContext('admin', true, false);
		$l10n = $this->createMock(IL10N::class);
		$l10n->expects(self::never())->method('t');
		$listener = new PasswordRestrictionListener($context, $l10n);
		$event = new BeforePasswordUpdatedEvent($this->createUser('alice'), 'reset-password');

		$listener->handle($event);

		self::assertSame('alice', $event->getUser()->getUID());
	}

	private function createRestrictionContext(
		string $userId,
		bool $isAdmin,
		bool $isRestricted,
	): RestrictionContext {
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($this->createUser($userId));
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isAdmin')->with($userId)->willReturn($isAdmin);
		$restrictedUserService = $this->createMock(RestrictedUserService::class);
		if ($isAdmin) {
			$restrictedUserService->expects(self::never())->method('isRestricted');
		} else {
			$restrictedUserService->method('isRestricted')->with($userId)->willReturn($isRestricted);
		}

		return new RestrictionContext($userSession, $groupManager, $restrictedUserService);
	}

	private function createUser(string $userId): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);

		return $user;
	}
}
