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

	/** @var array<string, bool> */
	private array $restrictionCache = [];

	public function __construct(
		private readonly RestrictedUserRepository $repository,
		private readonly IUserManager $userManager,
		private readonly IGroupManager $groupManager,
		private readonly ITimeFactory $timeFactory,
		private readonly TextSessionGuard $textSessionGuard,
	) {
	}

	public function isRestricted(string $userId): bool {
		if ($this->groupManager->isAdmin($userId)) {
			$this->restrictionCache[$userId] = false;

			return false;
		}

		if (array_key_exists($userId, $this->restrictionCache)) {
			return $this->restrictionCache[$userId];
		}

		$this->restrictionCache[$userId] = $this->repository->existsByUserId($userId);

		return $this->restrictionCache[$userId];
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
			unset($this->restrictionCache[$userId], $this->restrictionCache[$canonicalUserId]);
			throw new DomainException(self::ERROR_ALREADY_RESTRICTED);
		}

		$restrictedUser = new RestrictedUser();
		$restrictedUser->setUserId($canonicalUserId);
		$restrictedUser->setCreatedAt($this->timeFactory->getTime());
		$restrictedUser->setCreatedBy($adminUserId);

		try {
			$this->repository->insert($restrictedUser);
		} catch (Exception $exception) {
			if ($exception->getReason() === Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION
				|| $exception->getReason() === Exception::REASON_CONSTRAINT_VIOLATION) {
				unset($this->restrictionCache[$userId], $this->restrictionCache[$canonicalUserId]);
				throw new DomainException(
					self::ERROR_ALREADY_RESTRICTED,
					0,
					$exception,
				);
			}

			throw $exception;
		}

		unset($this->restrictionCache[$userId], $this->restrictionCache[$canonicalUserId]);
	}

	public function removeRestrictedUser(string $userId): void {
		$this->repository->deleteByUserId($userId);
		$this->textSessionGuard->forgetUser($userId);
		unset($this->restrictionCache[$userId]);
	}

	/**
	 * @return list<array{id: string, displayName: string}>
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

			$users[] = $this->summarizeUser($user);
		}

		return $users;
	}

	/**
	 * @return array{id: string, displayName: string}|null
	 */
	public function getRestrictedUser(string $userId): ?array {
		$user = $this->userManager->get($userId);
		if (!$user instanceof IUser || !$this->isRestricted($user->getUID())) {
			return null;
		}

		return $this->summarizeUser($user);
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
}
