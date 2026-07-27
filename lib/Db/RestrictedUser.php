<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method int|null getId()
 * @method void setId(int $id)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method string getCreatedBy()
 * @method void setCreatedBy(string $createdBy)
 * @method int getPermissions()
 * @method void setPermissions(int $permissions)
 */
class RestrictedUser extends Entity {
	protected string $userId = '';
	protected int $createdAt = 0;
	protected string $createdBy = '';
	protected int $permissions = 1;

	public function __construct() {
		$this->addType('id', Types::INTEGER);
		$this->addType('userId', Types::STRING);
		$this->addType('createdAt', Types::BIGINT);
		$this->addType('createdBy', Types::STRING);
		$this->addType('permissions', Types::INTEGER);
	}
}
