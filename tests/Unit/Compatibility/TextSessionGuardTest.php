<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Tests\Unit\Compatibility;

use OCA\UserLockdown\Compatibility\TextSessionGuard;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;

final class TextSessionGuardTest extends TestCase {
	public function testSessionOwnerIsStoredBySensitiveTokenHash(): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('getTime')->willReturn(1_722_000_000);
		$key = 'text_ro_' . substr(hash('sha256', 'secret-token'), 0, 56);

		$appConfig->method('getValueString')
			->with('user_lockdown', $key, '', true)
			->willReturn('');
		$appConfig->expects(self::once())
			->method('setValueString')
			->with(
				'user_lockdown',
				$key,
				'{"userId":"alice","seenAt":1722000000}',
				true,
				true,
			);
		$appConfig->method('getValueInt')->willReturn(0);
		$appConfig->method('searchKeys')->willReturn([]);

		$guard = new TextSessionGuard($appConfig, $timeFactory);
		$guard->remember('secret-token', 'alice');
	}

	public function testRememberedSessionOwnerCanBeResolvedAndForgotten(): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$timeFactory = $this->createMock(ITimeFactory::class);
		$key = 'text_ro_' . substr(hash('sha256', 'secret-token'), 0, 56);
		$appConfig->expects(self::once())
			->method('getValueString')
			->with('user_lockdown', $key, '', true)
			->willReturn('{"userId":"alice","seenAt":1722000000}');
		$appConfig->expects(self::once())
			->method('deleteKey')
			->with('user_lockdown', $key);

		$guard = new TextSessionGuard($appConfig, $timeFactory);

		self::assertSame('alice', $guard->getRememberedUserId('secret-token'));
		$guard->forget('secret-token');
	}
}
