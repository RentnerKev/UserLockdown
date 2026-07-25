<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Tests\Unit\Controller;

use OCA\UserLockdown\Controller\PageController;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

class PageControllerTest extends TestCase {
	public function testIndexRendersAdminTemplate(): void {
		$controller = new PageController(
			'user_lockdown',
			$this->createMock(IRequest::class),
		);

		$response = $controller->index();

		self::assertSame('admin', $response->getTemplateName());
	}
}
