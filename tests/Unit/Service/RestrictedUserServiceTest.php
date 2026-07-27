<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Tests\Unit\Service;

use DomainException;
use InvalidArgumentException;
use OCA\UserLockdown\Compatibility\TextSessionGuard;
use OCA\UserLockdown\Db\RestrictedUser;
use OCA\UserLockdown\Policy\Permission;
use OCA\UserLockdown\Policy\PermissionSet;
use OCA\UserLockdown\Repository\RestrictedUserRepository;
use OCA\UserLockdown\Service\PermissionSettingsService;
use OCA\UserLockdown\Service\RestrictedUserService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RestrictedUserServiceTest extends TestCase {
	/** @var RestrictedUserRepository&MockObject */
	private RestrictedUserRepository $repository;

	/** @var IUserManager&MockObject */
	private IUserManager $userManager;

	/** @var IGroupManager&MockObject */
	private IGroupManager $groupManager;

	/** @var ITimeFactory&MockObject */
	private ITimeFactory $timeFactory;

	private int $defaultMask = 1;
	private RestrictedUserService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->repository = $this->createMock(RestrictedUserRepository::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('searchKeys')->willReturn([]);
		$appConfig->method('getValueInt')->willReturnCallback(
			fn (): int => $this->defaultMask,
		);
		$permissionSettingsService = new PermissionSettingsService(
			$appConfig,
			$this->createMock(ISecureRandom::class),
		);
		$this->service = new RestrictedUserService(
			$this->repository,
			$this->userManager,
			$this->groupManager,
			$this->timeFactory,
			new TextSessionGuard($appConfig, $this->timeFactory),
			$permissionSettingsService,
		);
	}

	public function testRestrictedUserPermissionsAreDetectedAndCached(): void {
		$entry = $this->createEntry('alice', PermissionSet::readOnly());
		$this->repository->expects(self::once())
			->method('findByUserId')
			->with('alice')
			->willReturn($entry);

		self::assertSame(PermissionSet::readOnly()->toArray(), $this->service->getPermissions('alice'));
		self::assertTrue($this->service->isRestricted('alice'));
	}

	public function testNormalUserIsDetectedAndCached(): void {
		$this->repository->expects(self::once())
			->method('findByUserId')
			->with('bob')
			->willReturn(null);

		self::assertFalse($this->service->isRestricted('bob'));
		self::assertFalse($this->service->isRestricted('bob'));
	}

	public function testFullAccessPolicyIsNotReportedAsRestricted(): void {
		$this->repository->method('findByUserId')
			->with('alice')
			->willReturn($this->createEntry('alice', PermissionSet::fullAccess()));

		self::assertFalse($this->service->isRestricted('alice'));
		self::assertTrue($this->service->getPermissionSet('alice')?->isFullAccess());
	}

	public function testAdministratorIsNeverReportedAsRestricted(): void {
		$this->groupManager->method('isAdmin')->with('admin')->willReturn(true);
		$this->repository->expects(self::never())->method('findByUserId');

		self::assertFalse($this->service->isRestricted('admin'));
		self::assertNull($this->service->getPermissionSet('admin'));
	}

	public function testStoredInvalidMaskFailsClosed(): void {
		$entry = new RestrictedUser();
		$entry->setUserId('alice');
		$entry->setPermissions(Permission::WriteFiles->value);
		$this->repository->method('findByUserId')->willReturn($entry);

		self::assertSame(PermissionSet::blocked()->toArray(), $this->service->getPermissions('alice'));
	}

	public function testUserIsAddedWithServerSideDefaultPermissions(): void {
		$this->defaultMask = Permission::ViewFiles->value | Permission::WriteFiles->value;
		$user = $this->createUser('alice', 'Alice Example');
		$this->userManager->expects(self::once())
			->method('get')
			->with('alice')
			->willReturn($user);
		$this->groupManager->method('isAdmin')
			->willReturnMap([
				['alice', false],
				['admin', true],
			]);
		$this->repository->method('existsByUserId')->with('alice')->willReturn(false);
		$this->timeFactory->method('getTime')->willReturn(1_722_000_000);
		$this->repository->expects(self::once())
			->method('insert')
			->with(self::callback(static function (RestrictedUser $entity): bool {
				return $entity->getUserId() === 'alice'
					&& $entity->getCreatedAt() === 1_722_000_000
					&& $entity->getCreatedBy() === 'admin'
					&& $entity->getPermissions() === 3;
			}))
			->willReturnCallback(static fn (RestrictedUser $entity): RestrictedUser => $entity);

		$this->service->addRestrictedUser('alice', 'admin');
	}

	public function testPermissionsCanBeUpdatedAndCacheIsInvalidated(): void {
		$user = $this->createUser('alice', 'Alice Example');
		$entry = $this->createEntry('alice', PermissionSet::readOnly());
		$updated = PermissionSet::fromMask(
			Permission::ViewFiles->value | Permission::DeleteFiles->value,
		);
		$this->userManager->method('get')->with('alice')->willReturn($user);
		$this->groupManager->method('isAdmin')
			->willReturnMap([
				['alice', false],
				['admin', true],
			]);
		$this->repository->expects(self::exactly(3))
			->method('findByUserId')
			->with('alice')
			->willReturn($entry);
		$this->repository->expects(self::once())
			->method('update')
			->with(self::callback(static fn (RestrictedUser $entity): bool => $entity->getPermissions() === 5))
			->willReturnCallback(static fn (RestrictedUser $entity): RestrictedUser => $entity);

		self::assertSame(PermissionSet::readOnly()->toMask(), $this->service->getPermissionSet('alice')?->toMask());
		$this->service->updatePermissions('alice', $updated, 'admin');
		self::assertSame($updated->toMask(), $this->service->getPermissionSet('alice')?->toMask());
	}

	public function testUpdatingUnmanagedUserFails(): void {
		$user = $this->createUser('alice', 'Alice Example');
		$this->userManager->method('get')->with('alice')->willReturn($user);
		$this->groupManager->method('isAdmin')
			->willReturnMap([
				['alice', false],
				['admin', true],
			]);
		$this->repository->method('findByUserId')->with('alice')->willReturn(null);
		$this->repository->expects(self::never())->method('update');

		$this->expectException(DomainException::class);
		$this->expectExceptionMessage(RestrictedUserService::ERROR_NOT_RESTRICTED);

		$this->service->updatePermissions('alice', PermissionSet::readOnly(), 'admin');
	}

	public function testUserCanBeRemoved(): void {
		$this->repository->expects(self::once())
			->method('deleteByUserId')
			->with('alice')
			->willReturn(1);

		$this->service->removeRestrictedUser('alice');
	}

	public function testRestrictedUsersIncludePermissions(): void {
		$aliceEntry = $this->createEntry('alice', PermissionSet::readOnly());
		$deletedEntry = $this->createEntry('deleted-user', PermissionSet::readOnly());
		$this->repository->method('findAll')->willReturn([$aliceEntry, $deletedEntry]);
		$alice = $this->createUser('alice', 'Alice Example');
		$this->userManager->method('get')->willReturnMap([
			['alice', $alice],
			['deleted-user', null],
		]);

		self::assertSame([
			[
				'id' => 'alice',
				'displayName' => 'Alice Example',
				'permissions' => PermissionSet::readOnly()->toArray(),
			],
		], $this->service->getRestrictedUsers());
	}

	public function testAdministratorCannotBeRestricted(): void {
		$user = $this->createUser('admin', 'Administrator');
		$this->userManager->method('get')->with('admin')->willReturn($user);
		$this->groupManager->method('isAdmin')->with('admin')->willReturn(true);
		$this->repository->expects(self::never())->method('insert');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage(RestrictedUserService::ERROR_ADMIN_USER);

		$this->service->addRestrictedUser('admin', 'admin');
	}

	public function testNonAdministratorCannotUpdatePermissions(): void {
		$user = $this->createUser('alice', 'Alice Example');
		$this->userManager->method('get')->with('alice')->willReturn($user);
		$this->groupManager->method('isAdmin')->willReturn(false);
		$this->repository->expects(self::never())->method('findByUserId');
		$this->repository->expects(self::never())->method('update');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage(RestrictedUserService::ERROR_INVALID_ADMIN);

		$this->service->updatePermissions('alice', PermissionSet::readOnly(), 'mallory');
	}

	public function testNonAdministratorCannotAddRestriction(): void {
		$user = $this->createUser('alice', 'Alice Example');
		$this->userManager->method('get')->with('alice')->willReturn($user);
		$this->groupManager->method('isAdmin')->willReturn(false);
		$this->repository->expects(self::never())->method('existsByUserId');
		$this->repository->expects(self::never())->method('insert');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage(RestrictedUserService::ERROR_INVALID_ADMIN);

		$this->service->addRestrictedUser('alice', 'mallory');
	}

	public function testDuplicateEntryIsPrevented(): void {
		$user = $this->createUser('alice', 'Alice Example');
		$this->userManager->method('get')->with('alice')->willReturn($user);
		$this->groupManager->method('isAdmin')
			->willReturnMap([
				['alice', false],
				['admin', true],
			]);
		$this->repository->method('existsByUserId')->with('alice')->willReturn(true);
		$this->repository->expects(self::never())->method('insert');

		$this->expectException(DomainException::class);
		$this->expectExceptionMessage(RestrictedUserService::ERROR_ALREADY_RESTRICTED);

		$this->service->addRestrictedUser('alice', 'admin');
	}

	public function testCacheIsInvalidatedAfterAdding(): void {
		$user = $this->createUser('alice', 'Alice Example');
		$entry = $this->createEntry('alice', PermissionSet::readOnly());
		$this->userManager->method('get')->with('alice')->willReturn($user);
		$this->groupManager->method('isAdmin')
			->willReturnMap([
				['alice', false],
				['admin', true],
			]);
		$this->repository->expects(self::exactly(2))
			->method('findByUserId')
			->with('alice')
			->willReturnOnConsecutiveCalls(null, $entry);
		$this->repository->method('existsByUserId')->with('alice')->willReturn(false);
		$this->repository->method('insert')
			->willReturnCallback(static fn (RestrictedUser $entity): RestrictedUser => $entity);

		self::assertFalse($this->service->isRestricted('alice'));
		$this->service->addRestrictedUser('alice', 'admin');
		self::assertTrue($this->service->isRestricted('alice'));
	}

	public function testCacheIsInvalidatedAfterRemoving(): void {
		$this->repository->expects(self::exactly(2))
			->method('findByUserId')
			->with('alice')
			->willReturnOnConsecutiveCalls(
				$this->createEntry('alice', PermissionSet::readOnly()),
				null,
			);
		$this->repository->method('deleteByUserId')->with('alice')->willReturn(1);

		self::assertTrue($this->service->isRestricted('alice'));
		$this->service->removeRestrictedUser('alice');
		self::assertFalse($this->service->isRestricted('alice'));
	}

	public function testSearchMergesIdsAndDisplayNamesAndFiltersAdministrators(): void {
		$alice = $this->createUser('alice', 'Alice Example');
		$bob = $this->createUser('bob', 'Bob Example');
		$admin = $this->createUser('admin', 'Admin Example');
		$this->userManager->method('search')->with('example', 20, 0)
			->willReturn([$alice, $admin]);
		$this->userManager->method('searchDisplayName')->with('example', 20, 0)
			->willReturn([$alice, $bob]);
		$this->groupManager->method('isAdmin')
			->willReturnCallback(static fn (string $userId): bool => $userId === 'admin');
		$this->repository->expects(self::once())
			->method('findRestrictedUserIds')
			->with(['alice', 'bob'])
			->willReturn(['bob']);

		self::assertSame([
			[
				'id' => 'alice',
				'displayName' => 'Alice Example',
				'restricted' => false,
			],
			[
				'id' => 'bob',
				'displayName' => 'Bob Example',
				'restricted' => true,
			],
		], $this->service->searchUsers('example'));
	}

	private function createEntry(string $userId, PermissionSet $permissions): RestrictedUser {
		$entry = new RestrictedUser();
		$entry->setUserId($userId);
		$entry->setPermissions($permissions->toMask());

		return $entry;
	}

	/** @return IUser&MockObject */
	private function createUser(string $userId, string $displayName): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$user->method('getDisplayName')->willReturn($displayName);

		return $user;
	}
}
