<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Service;

use DomainException;
use InvalidArgumentException;
use OCA\UserLockdown\Compatibility\TextSessionGuard;
use OCA\UserLockdown\Db\RestrictedUser;
use OCA\UserLockdown\Policy\PermissionSet;
use OCA\UserLockdown\Repository\RestrictedUserRepository;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;

class RestrictedUserService {
	public const ERROR_INVALID_USER = 'invalid_user';
	public const ERROR_ADMIN_USER = 'admin_user';
	public const ERROR_INVALID_ADMIN = 'invalid_admin';
	public const ERROR_ALREADY_RESTRICTED = 'already_restricted';
	public const ERROR_NOT_RESTRICTED = 'not_restricted';

	/** @var array<string, PermissionSet|null> */
	private array $permissionCache = [];

	public function __construct(
		private readonly RestrictedUserRepository $repository,
		private readonly IUserManager $userManager,
		private readonly IGroupManager $groupManager,
		private readonly ITimeFactory $timeFactory,
		private readonly TextSessionGuard $textSessionGuard,
		private readonly PermissionSettingsService $permissionSettingsService,
	) {
	}

	public function isRestricted(string $userId): bool {
		$permissions = $this->getPermissionSet($userId);

		return $permissions !== null && !$permissions->isFullAccess();
	}

	public function getPermissionSet(string $userId): ?PermissionSet {
		if ($this->groupManager->isAdmin($userId)) {
			$this->permissionCache[$userId] = null;

			return null;
		}

		if (array_key_exists($userId, $this->permissionCache)) {
			return $this->permissionCache[$userId];
		}

		$restrictedUser = $this->repository->findByUserId($userId);
		$this->permissionCache[$userId] = $restrictedUser instanceof RestrictedUser
			? PermissionSet::fromMask($restrictedUser->getPermissions())
			: null;

		return $this->permissionCache[$userId];
	}

	/**
	 * @return array{
	 *     viewFiles: bool,
	 *     writeFiles: bool,
	 *     deleteFiles: bool,
	 *     shareFiles: bool,
	 *     changePassword: bool,
	 *     fullAccess: bool,
	 * }|null
	 */
	public function getPermissions(string $userId): ?array {
		return $this->getPermissionSet($userId)?->toArray();
	}

	public function addRestrictedUser(string $userId, string $adminUserId): void {
		$user = $this->userManager->get($userId);
		if (!$user instanceof IUser) {
			throw new InvalidArgumentException(self::ERROR_INVALID_USER);
		}

		$canonicalUserId = $user->getUID();

		if ($this->groupManager->isAdmin($canonicalUserId)) {
			throw new InvalidArgumentException(self::ERROR_ADMIN_USER);
		}

		if (!$this->groupManager->isAdmin($adminUserId)) {
			throw new InvalidArgumentException(self::ERROR_INVALID_ADMIN);
		}

		if ($this->repository->existsByUserId($canonicalUserId)) {
			unset($this->permissionCache[$userId], $this->permissionCache[$canonicalUserId]);
			throw new DomainException(self::ERROR_ALREADY_RESTRICTED);
		}

		$restrictedUser = new RestrictedUser();
		$restrictedUser->setUserId($canonicalUserId);
		$restrictedUser->setCreatedAt($this->timeFactory->getTime());
		$restrictedUser->setCreatedBy($adminUserId);
		$restrictedUser->setPermissions(
			$this->permissionSettingsService->getDefaultPermissions()->toMask(),
		);

		try {
			$this->repository->insert($restrictedUser);
		} catch (Exception $exception) {
			if ($exception->getReason() === Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION
				|| $exception->getReason() === Exception::REASON_CONSTRAINT_VIOLATION) {
				unset($this->permissionCache[$userId], $this->permissionCache[$canonicalUserId]);
				throw new DomainException(
					self::ERROR_ALREADY_RESTRICTED,
					0,
					$exception,
				);
			}

			throw $exception;
		}

		unset($this->permissionCache[$userId], $this->permissionCache[$canonicalUserId]);
	}

	public function updatePermissions(
		string $userId,
		PermissionSet $permissions,
		string $adminUserId,
	): void {
		$user = $this->userManager->get($userId);
		if (!$user instanceof IUser) {
			throw new InvalidArgumentException(self::ERROR_INVALID_USER);
		}

		$canonicalUserId = $user->getUID();
		if ($this->groupManager->isAdmin($canonicalUserId)) {
			throw new InvalidArgumentException(self::ERROR_ADMIN_USER);
		}

		if (!$this->groupManager->isAdmin($adminUserId)) {
			throw new InvalidArgumentException(self::ERROR_INVALID_ADMIN);
		}

		$restrictedUser = $this->repository->findByUserId($canonicalUserId);
		if (!$restrictedUser instanceof RestrictedUser) {
			unset($this->permissionCache[$userId], $this->permissionCache[$canonicalUserId]);
			throw new DomainException(self::ERROR_NOT_RESTRICTED);
		}

		$restrictedUser->setPermissions($permissions->toMask());
		$this->repository->update($restrictedUser);
		unset($this->permissionCache[$userId], $this->permissionCache[$canonicalUserId]);
	}

	public function removeRestrictedUser(string $userId): void {
		$this->repository->deleteByUserId($userId);
		$this->textSessionGuard->forgetUser($userId);
		unset($this->permissionCache[$userId]);
	}

	/**
	 * @return list<array{
	 *     id: string,
	 *     displayName: string,
	 *     permissions: array{
	 *         viewFiles: bool,
	 *         writeFiles: bool,
	 *         deleteFiles: bool,
	 *         shareFiles: bool,
	 *         changePassword: bool,
	 *         fullAccess: bool,
	 *     },
	 * }>
	 */
	public function getRestrictedUsers(): array {
		$users = [];

		foreach ($this->repository->findAll() as $restrictedUser) {
			$user = $this->userManager->get($restrictedUser->getUserId());
			if (
				!$user instanceof IUser
				|| $this->groupManager->isAdmin($user->getUID())
			) {
				continue;
			}

			$users[] = $this->summarizeRestrictedUser(
				$user,
				PermissionSet::fromMask($restrictedUser->getPermissions()),
			);
		}

		return $users;
	}

	/**
	 * @return array{
	 *     id: string,
	 *     displayName: string,
	 *     permissions: array{
	 *         viewFiles: bool,
	 *         writeFiles: bool,
	 *         deleteFiles: bool,
	 *         shareFiles: bool,
	 *         changePassword: bool,
	 *         fullAccess: bool,
	 *     },
	 * }|null
	 */
	public function getRestrictedUser(string $userId): ?array {
		$user = $this->userManager->get($userId);
		if (!$user instanceof IUser) {
			return null;
		}

		$permissions = $this->getPermissionSet($user->getUID());
		if (!$permissions instanceof PermissionSet) {
			return null;
		}

		return $this->summarizeRestrictedUser($user, $permissions);
	}

	/**
	 * @return list<array{id: string, displayName: string, restricted: bool}>
	 */
	public function searchUsers(string $query, int $limit = 20): array {
		if ($query === '' || $limit < 1) {
			return [];
		}

		$candidates = array_merge(
			$this->userManager->search($query, $limit, 0),
			$this->userManager->searchDisplayName($query, $limit, 0),
		);

		/** @var array<string, IUser> $uniqueUsers */
		$uniqueUsers = [];
		foreach ($candidates as $candidate) {
			if (!$candidate instanceof IUser) {
				continue;
			}

			$userId = $candidate->getUID();
			if ($this->groupManager->isAdmin($userId)) {
				continue;
			}

			$uniqueUsers[$userId] = $candidate;
		}

		$restrictedIds = array_fill_keys(
			$this->repository->findRestrictedUserIds(array_keys($uniqueUsers)),
			true,
		);

		$users = [];
		foreach ($uniqueUsers as $userId => $user) {
			$users[] = [
				...$this->summarizeUser($user),
				'restricted' => isset($restrictedIds[$userId]),
			];
		}

		usort(
			$users,
			static fn (array $left, array $right): int => strnatcasecmp(
				$left['displayName'] . $left['id'],
				$right['displayName'] . $right['id'],
			),
		);

		return array_slice($users, 0, $limit);
	}

	/**
	 * @return array{id: string, displayName: string}
	 */
	private function summarizeUser(IUser $user): array {
		$displayName = trim($user->getDisplayName());

		return [
			'id' => $user->getUID(),
			'displayName' => $displayName !== '' ? $displayName : $user->getUID(),
		];
	}

	/**
	 * @return array{
	 *     id: string,
	 *     displayName: string,
	 *     permissions: array{
	 *         viewFiles: bool,
	 *         writeFiles: bool,
	 *         deleteFiles: bool,
	 *         shareFiles: bool,
	 *         changePassword: bool,
	 *         fullAccess: bool,
	 *     },
	 * }
	 */
	private function summarizeRestrictedUser(IUser $user, PermissionSet $permissions): array {
		return [
			...$this->summarizeUser($user),
			'permissions' => $permissions->toArray(),
		];
	}
}
