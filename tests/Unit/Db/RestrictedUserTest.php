<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Tests\Unit\Db;

use OCA\UserLockdown\Db\RestrictedUser;
use PHPUnit\Framework\TestCase;

class RestrictedUserTest extends TestCase {
	public function testDatabaseRowIsMappedToTypedEntity(): void {
		$entity = RestrictedUser::fromRow([
			'id' => '7',
			'user_id' => 'alice',
			'created_at' => '1722000000',
			'created_by' => 'admin',
			'permissions' => '5',
		]);

		self::assertSame(7, $entity->getId());
		self::assertSame('alice', $entity->getUserId());
		self::assertSame(1_722_000_000, $entity->getCreatedAt());
		self::assertSame('admin', $entity->getCreatedBy());
		self::assertSame(5, $entity->getPermissions());
	}
}
