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
use OCP\DB\Types;
use OCP\Migration\IOutput;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;

class Version10000Date20260725220000Test extends TestCase {
	public function testCreatesRestrictedUsersTable(): void {
		$schema = $this->createMock(ISchemaWrapper::class);
		$table = $this->createTableMock();
		$columnType = (new ReflectionMethod($table, 'addColumn'))->getReturnType();
		self::assertInstanceOf(ReflectionNamedType::class, $columnType);
		/** @var class-string $columnClass */
		$columnClass = $columnType->getName();
		$column = $this->createStub($columnClass);
		$columns = [];
		$table->expects(self::exactly(4))
			->method('addColumn')
			->willReturnCallback(static function (string $name, string $type, array $options) use (&$columns, $column): object {
				self::assertArrayNotHasKey($name, $columns);
				$columns[$name] = [$type, $options];
				return $column;
			});
		$table->expects(self::once())->method('setPrimaryKey')->with(['id']);
		$table->expects(self::once())
			->method('addUniqueIndex')
			->with(['user_id'], 'user_lockdown_uid_uniq');
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
			$this->createStub(IOutput::class),
			static fn (): ISchemaWrapper => $schema,
			[],
		);

		self::assertSame($schema, $result);
		self::assertSame([
			'id' => [Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]],
			'user_id' => [Types::STRING, [
				'length' => 64,
				'notnull' => true,
			]],
			'created_at' => [Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]],
			'created_by' => [Types::STRING, [
				'length' => 64,
				'notnull' => true,
			]],
		], $columns);
	}

	public function testLeavesExistingTableUntouched(): void {
		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->expects(self::once())->method('hasTable')->with('user_lockdown_users')->willReturn(true);
		$schema->expects(self::never())->method('createTable');
		$migration = new Version10000Date20260725220000();

		$result = $migration->changeSchema(
			$this->createStub(IOutput::class),
			static fn (): ISchemaWrapper => $schema,
			[],
		);

		self::assertNull($result);
	}

	private function createTableMock(): MockObject {
		$tableType = (new ReflectionMethod(ISchemaWrapper::class, 'createTable'))->getReturnType();
		if ($tableType === null) {
			// Older OCP versions declare the Doctrine table only in their public PHPDoc.
			return $this->createMock(Table::class);
		}

		self::assertInstanceOf(ReflectionNamedType::class, $tableType);
		/** @var class-string $tableClass */
		$tableClass = $tableType->getName();
		return $this->createMock($tableClass);
	}
}
