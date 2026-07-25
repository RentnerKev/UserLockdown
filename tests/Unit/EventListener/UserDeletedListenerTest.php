<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Tests\Unit\EventListener;

use Error;
use OCA\UserLockdown\EventListener\UserDeletedListener;
use OCA\UserLockdown\Service\RestrictedUserService;
use OCP\IUser;
use OCP\User\Events\UserDeletedEvent;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class UserDeletedListenerTest extends TestCase {
	public function testDeletedUserRestrictionIsCleanedUp(): void {
		$service = $this->createMock(RestrictedUserService::class);
		$service->expects(self::once())
			->method('removeRestrictedUser')
			->with('alice');
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::never())->method('error');
		$listener = new UserDeletedListener($service, $logger);

		$listener->handle(new UserDeletedEvent($this->createUser('alice')));
	}

	public function testCleanupThrowableIsSwallowedAndLogged(): void {
		$failure = new Error('database unavailable');
		$service = $this->createMock(RestrictedUserService::class);
		$service->expects(self::once())
			->method('removeRestrictedUser')
			->with('alice')
			->willThrowException($failure);
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())
			->method('error')
			->with(
				'Could not remove a deleted user from User Lockdown.',
				self::callback(static function (array $context) use ($failure): bool {
					self::assertSame('user_lockdown', $context['app'] ?? null);
					self::assertSame('alice', $context['userId'] ?? null);
					self::assertSame($failure, $context['exception'] ?? null);

					return true;
				}),
			);
		$listener = new UserDeletedListener($service, $logger);

		$listener->handle(new UserDeletedEvent($this->createUser('alice')));
	}

	private function createUser(string $userId): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);

		return $user;
	}
}
