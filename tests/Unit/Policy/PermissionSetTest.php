<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Tests\Unit\Policy;

use InvalidArgumentException;
use OCA\UserLockdown\Policy\Permission;
use OCA\UserLockdown\Policy\PermissionSet;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PermissionSetTest extends TestCase {
	public function testArrayPayloadIsConvertedToCanonicalMask(): void {
		$permissions = PermissionSet::fromArray([
			'viewFiles' => true,
			'writeFiles' => false,
			'deleteFiles' => true,
			'shareFiles' => false,
			'changePassword' => true,
			'fullAccess' => false,
		]);

		self::assertSame(21, $permissions->toMask());
		self::assertTrue($permissions->allows(Permission::ViewFiles));
		self::assertTrue($permissions->allows(Permission::DeleteFiles));
		self::assertTrue($permissions->allows(Permission::ChangePassword));
		self::assertFalse($permissions->allows(Permission::WriteFiles));
		self::assertFalse($permissions->isFullAccess());
	}

	public function testFullAccessNormalizesEveryPermissionToTrue(): void {
		$permissions = PermissionSet::fromArray([
			'viewFiles' => false,
			'writeFiles' => false,
			'deleteFiles' => false,
			'shareFiles' => false,
			'changePassword' => false,
			'fullAccess' => true,
		]);

		self::assertTrue($permissions->isFullAccess());
		self::assertSame([
			'viewFiles' => true,
			'writeFiles' => true,
			'deleteFiles' => true,
			'shareFiles' => true,
			'changePassword' => true,
			'fullAccess' => true,
		], $permissions->toArray());
	}

	/** @param array<string, mixed> $payload */
	#[DataProvider('invalidPayloadProvider')]
	public function testInvalidApiPayloadsAreRejected(array $payload): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('invalid_permissions');

		PermissionSet::fromArray($payload);
	}

	/** @return iterable<string, array{array<string, mixed>}> */
	public static function invalidPayloadProvider(): iterable {
		$readOnly = PermissionSet::readOnly()->toArray();

		yield 'missing key' => [[
			'viewFiles' => true,
			'writeFiles' => false,
			'deleteFiles' => false,
			'shareFiles' => false,
			'changePassword' => false,
		]];
		yield 'unknown key' => [[
			...$readOnly,
			'unknown' => false,
		]];
		yield 'non boolean value' => [[
			...$readOnly,
			'viewFiles' => 1,
		]];
		yield 'write without view' => [[
			...$readOnly,
			'viewFiles' => false,
			'writeFiles' => true,
		]];
		yield 'delete without view' => [[
			...$readOnly,
			'viewFiles' => false,
			'deleteFiles' => true,
		]];
		yield 'share without view' => [[
			...$readOnly,
			'viewFiles' => false,
			'shareFiles' => true,
		]];
	}

	#[DataProvider('invalidStoredMaskProvider')]
	public function testInvalidStoredMasksFailClosed(int $mask): void {
		self::assertSame(PermissionSet::blocked()->toArray(), PermissionSet::fromMask($mask)->toArray());
	}

	/** @return iterable<string, array{int}> */
	public static function invalidStoredMaskProvider(): iterable {
		yield 'negative' => [-1];
		yield 'unknown bit' => [64];
		yield 'write without view' => [Permission::WriteFiles->value];
		yield 'delete without view' => [Permission::DeleteFiles->value];
		yield 'share without view' => [Permission::ShareFiles->value];
	}

	public function testStoredFullAccessBitNormalizesEveryPermission(): void {
		$permissions = PermissionSet::fromMask(Permission::FullAccess->value);

		self::assertSame(PermissionSet::fullAccess()->toArray(), $permissions->toArray());
		self::assertSame(63, $permissions->toMask());
	}
}
