<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Tests\Unit\Service;

use OCA\UserLockdown\Service\RestrictedUserService;
use OCA\UserLockdown\Service\RestrictionContext;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RestrictionContextTest extends TestCase {
	/** @var IUserSession&MockObject */
	private IUserSession $userSession;

	/** @var IGroupManager&MockObject */
	private IGroupManager $groupManager;

	/** @var RestrictedUserService&MockObject */
	private RestrictedUserService $restrictedUserService;

	private RestrictionContext $context;

	protected function setUp(): void {
		parent::setUp();

		$this->userSession = $this->createMock(IUserSession::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->restrictedUserService = $this->createMock(RestrictedUserService::class);
		$this->context = new RestrictionContext(
			$this->userSession,
			$this->groupManager,
			$this->restrictedUserService,
		);
	}

	public function testRestrictedUserIsResolvedAndCached(): void {
		$user = $this->createUser('alice');
		$this->userSession->expects(self::once())
			->method('getUser')
			->willReturn($user);
		$this->groupManager->expects(self::once())
			->method('isAdmin')
			->with('alice')
			->willReturn(false);
		$this->restrictedUserService->expects(self::once())
			->method('isRestricted')
			->with('alice')
			->willReturn(true);

		self::assertSame('alice', $this->context->getRestrictedUserId());
		self::assertTrue($this->context->isCurrentUserRestricted());
		self::assertSame('alice', $this->context->getRestrictedUserId());
	}

	public function testAdministratorIsNeverRestricted(): void {
		$user = $this->createUser('admin');
		$this->userSession->expects(self::once())
			->method('getUser')
			->willReturn($user);
		$this->groupManager->expects(self::once())
			->method('isAdmin')
			->with('admin')
			->willReturn(true);
		$this->restrictedUserService->expects(self::never())
			->method('isRestricted');

		self::assertNull($this->context->getRestrictedUserId());
		self::assertFalse($this->context->isCurrentUserRestricted());
	}

	public function testUnrestrictedUserReturnsNoRestrictedUserId(): void {
		$user = $this->createUser('bob');
		$this->userSession->expects(self::once())
			->method('getUser')
			->willReturn($user);
		$this->groupManager->expects(self::once())
			->method('isAdmin')
			->with('bob')
			->willReturn(false);
		$this->restrictedUserService->expects(self::once())
			->method('isRestricted')
			->with('bob')
			->willReturn(false);

		self::assertNull($this->context->getRestrictedUserId());
		self::assertFalse($this->context->isCurrentUserRestricted());
	}

	public function testMissingUserSessionReturnsNoRestrictedUserId(): void {
		$this->userSession->expects(self::exactly(2))
			->method('getUser')
			->willReturn(null);
		$this->groupManager->expects(self::never())
			->method('isAdmin');
		$this->restrictedUserService->expects(self::never())
			->method('isRestricted');

		self::assertNull($this->context->getRestrictedUserId());
		self::assertFalse($this->context->isCurrentUserRestricted());
	}

	public function testUserResolvedLaterInRequestIsStillRestricted(): void {
		$user = $this->createUser('alice');
		$this->userSession->expects(self::exactly(2))
			->method('getUser')
			->willReturnOnConsecutiveCalls(null, $user);
		$this->groupManager->expects(self::once())
			->method('isAdmin')
			->with('alice')
			->willReturn(false);
		$this->restrictedUserService->expects(self::once())
			->method('isRestricted')
			->with('alice')
			->willReturn(true);

		self::assertNull($this->context->getRestrictedUserId());
		self::assertSame('alice', $this->context->getRestrictedUserId());
	}

	/**
	 * @return IUser&MockObject
	 */
	private function createUser(string $userId): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);

		return $user;
	}
}
