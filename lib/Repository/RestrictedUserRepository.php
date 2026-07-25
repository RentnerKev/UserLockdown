<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Repository;

use OCA\UserLockdown\Db\RestrictedUser;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<RestrictedUser>
 */
class RestrictedUserRepository extends QBMapper {
	public const TABLE_NAME = 'user_lockdown_users';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::TABLE_NAME, RestrictedUser::class);
	}

	public function existsByUserId(string $userId): bool {
		$query = $this->db->getQueryBuilder();
		$query->select('id')
			->from($this->getTableName())
			->where($query->expr()->eq(
				'user_id',
				$query->createNamedParameter($userId, IQueryBuilder::PARAM_STR),
			))
			->setMaxResults(1);

		$result = $query->executeQuery();
		try {
			return $result->fetchOne() !== false;
		} finally {
			$result->closeCursor();
		}
	}

	/**
	 * @return list<RestrictedUser>
	 */
	public function findAll(): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->orderBy('created_at', 'ASC')
			->addOrderBy('id', 'ASC');

		/** @var list<RestrictedUser> $entities */
		$entities = $this->findEntities($query);

		return $entities;
	}

	/**
	 * @param list<string> $userIds
	 * @return list<string>
	 */
	public function findRestrictedUserIds(array $userIds): array {
		if ($userIds === []) {
			return [];
		}

		$query = $this->db->getQueryBuilder();
		$query->select('user_id')
			->from($this->getTableName())
			->where($query->expr()->in(
				'user_id',
				$query->createNamedParameter(
					array_values(array_unique($userIds)),
					IQueryBuilder::PARAM_STR_ARRAY,
				),
			));

		$result = $query->executeQuery();
		$restrictedUserIds = [];
		try {
			while (($row = $result->fetch()) !== false) {
				$userId = $row['user_id'] ?? null;
				if (is_string($userId)) {
					$restrictedUserIds[] = $userId;
				}
			}
		} finally {
			$result->closeCursor();
		}

		return $restrictedUserIds;
	}

	public function deleteByUserId(string $userId): int {
		$query = $this->db->getQueryBuilder();
		$query->delete($this->getTableName())
			->where($query->expr()->eq(
				'user_id',
				$query->createNamedParameter($userId, IQueryBuilder::PARAM_STR),
			));

		return $query->executeStatement();
	}
}
