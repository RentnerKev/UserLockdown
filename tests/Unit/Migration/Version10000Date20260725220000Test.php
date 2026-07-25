<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Tests\Unit\Migration;

use Doctrine\DBAL\Schema\Table;
use OCA\UserLockdown\Migration\Version10000Date20260725220000;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

class Version10000Date20260725220000Test extends TestCase {
	public function testCreatesRestrictedUsersTable(): void {
		$schema = $this->createMock(ISchemaWrapper::class);
		$table = new Table('user_lockdown_users');
		$schema->expects(self::once())
			->method('hasTable')
			->with('user_lockdown_users')
			->willReturn(false);
		$schema->expects(self::once())
			->method('createTable')
			->with('user_lockdown_users')
			->willReturn($table);
		$migration = new Version10000Date20260725220000();

		$result = $migration->changeSchema(
			$this->createMock(IOutput::class),
			static fn (): ISchemaWrapper => $schema,
			[],
		);

		self::assertSame($schema, $result);
		self::assertTrue($table->hasColumn('id'));
		self::assertTrue($table->hasColumn('user_id'));
		self::assertTrue($table->hasColumn('created_at'));
		self::assertTrue($table->hasColumn('created_by'));
		self::assertSame(['id'], $table->getPrimaryKey()?->getColumns());
		self::assertTrue($table->getIndex('user_lockdown_uid_uniq')->isUnique());
		self::assertSame(['user_id'], $table->getIndex('user_lockdown_uid_uniq')->getColumns());
	}

	public function testLeavesExistingTableUntouched(): void {
		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->with('user_lockdown_users')->willReturn(true);
		$schema->expects(self::never())->method('createTable');
		$migration = new Version10000Date20260725220000();

		$result = $migration->changeSchema(
			$this->createMock(IOutput::class),
			static fn (): ISchemaWrapper => $schema,
			[],
		);

		self::assertNull($result);
	}
}
