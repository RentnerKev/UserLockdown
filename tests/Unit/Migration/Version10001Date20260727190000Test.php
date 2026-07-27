<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Tests\Unit\Migration;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\Types;
use OCA\UserLockdown\Migration\Version10001Date20260727190000;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

class Version10001Date20260727190000Test extends TestCase {
	public function testAddsReadOnlyPermissionsColumn(): void {
		$schema = $this->createMock(ISchemaWrapper::class);
		$table = new Table('user_lockdown_users');
		$schema->method('hasTable')->with('user_lockdown_users')->willReturn(true);
		$schema->expects(self::once())
			->method('getTable')
			->with('user_lockdown_users')
			->willReturn($table);
		$migration = new Version10001Date20260727190000();

		$result = $migration->changeSchema(
			$this->createMock(IOutput::class),
			static fn (): ISchemaWrapper => $schema,
			[],
		);

		self::assertSame($schema, $result);
		self::assertTrue($table->hasColumn('permissions'));
		$column = $table->getColumn('permissions');
		self::assertInstanceOf(IntegerType::class, $column->getType());
		self::assertTrue($column->getNotnull());
		self::assertSame(1, $column->getDefault());
		self::assertTrue($column->getUnsigned());
	}

	public function testLeavesExistingPermissionsColumnUntouched(): void {
		$schema = $this->createMock(ISchemaWrapper::class);
		$table = new Table('user_lockdown_users');
		$table->addColumn('permissions', Types::INTEGER);
		$schema->method('hasTable')->with('user_lockdown_users')->willReturn(true);
		$schema->method('getTable')->with('user_lockdown_users')->willReturn($table);
		$migration = new Version10001Date20260727190000();

		$result = $migration->changeSchema(
			$this->createMock(IOutput::class),
			static fn (): ISchemaWrapper => $schema,
			[],
		);

		self::assertNull($result);
	}

	public function testDoesNothingBeforeBaseTableExists(): void {
		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->with('user_lockdown_users')->willReturn(false);
		$schema->expects(self::never())->method('getTable');
		$migration = new Version10001Date20260727190000();

		$result = $migration->changeSchema(
			$this->createMock(IOutput::class),
			static fn (): ISchemaWrapper => $schema,
			[],
		);

		self::assertNull($result);
	}
}
