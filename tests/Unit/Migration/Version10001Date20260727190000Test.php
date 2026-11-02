<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Tests\Unit\Migration;

use OCA\UserLockdown\Migration\Version10001Date20260727190000;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Schema\ITable;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

class Version10001Date20260727190000Test extends TestCase {
	public function testAddsReadOnlyPermissionsColumn(): void {
		$schema = $this->createMock(ISchemaWrapper::class);
		$table = $this->createMock(ITable::class);
		$table->expects(self::once())->method('hasColumn')->with('permissions')->willReturn(false);
		$table->expects(self::once())
			->method('addColumn')
			->with('permissions', Types::INTEGER, [
				'default' => 1,
				'notnull' => true,
				'unsigned' => true,
			]);
		$schema->expects(self::once())->method('hasTable')->with('user_lockdown_users')->willReturn(true);
		$schema->expects(self::once())
			->method('getTable')
			->with('user_lockdown_users')
			->willReturn($table);
		$migration = new Version10001Date20260727190000();

		$result = $migration->changeSchema(
			$this->createStub(IOutput::class),
			static fn (): ISchemaWrapper => $schema,
			[],
		);

		self::assertSame($schema, $result);
	}

	public function testLeavesExistingPermissionsColumnUntouched(): void {
		$schema = $this->createMock(ISchemaWrapper::class);
		$table = $this->createMock(ITable::class);
		$table->expects(self::once())->method('hasColumn')->with('permissions')->willReturn(true);
		$table->expects(self::never())->method('addColumn');
		$schema->expects(self::once())->method('hasTable')->with('user_lockdown_users')->willReturn(true);
		$schema->expects(self::once())->method('getTable')->with('user_lockdown_users')->willReturn($table);
		$migration = new Version10001Date20260727190000();

		$result = $migration->changeSchema(
			$this->createStub(IOutput::class),
			static fn (): ISchemaWrapper => $schema,
			[],
		);

		self::assertNull($result);
	}

	public function testDoesNothingBeforeBaseTableExists(): void {
		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->expects(self::once())->method('hasTable')->with('user_lockdown_users')->willReturn(false);
		$schema->expects(self::never())->method('getTable');
		$migration = new Version10001Date20260727190000();

		$result = $migration->changeSchema(
			$this->createStub(IOutput::class),
			static fn (): ISchemaWrapper => $schema,
			[],
		);

		self::assertNull($result);
	}
}
