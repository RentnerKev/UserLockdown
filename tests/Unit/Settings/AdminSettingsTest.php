<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Tests\Unit\Settings;

use OCA\UserLockdown\Settings\Admin;
use OCA\UserLockdown\Settings\AdminSection;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

class AdminSettingsTest extends TestCase {
	public function testAdminFormUsesExpectedTemplateAndSection(): void {
		$settings = new Admin();

		self::assertSame('admin', $settings->getForm()->getTemplateName());
		self::assertSame('user_lockdown', $settings->getSection());
		self::assertSame(50, $settings->getPriority());
	}

	public function testAdminSectionMetadata(): void {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->with('User Lockdown')->willReturn('User Lockdown');
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('imagePath')
			->with('user_lockdown', 'app.svg')
			->willReturn('/apps/user_lockdown/img/app.svg');
		$section = new AdminSection($l10n, $urlGenerator);

		self::assertSame('user_lockdown', $section->getID());
		self::assertSame('User Lockdown', $section->getName());
		self::assertSame('/apps/user_lockdown/img/app.svg', $section->getIcon());
		self::assertSame(50, $section->getPriority());
	}
}
