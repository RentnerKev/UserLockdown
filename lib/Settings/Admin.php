<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

class Admin implements ISettings {
	/** @return TemplateResponse<\OCP\AppFramework\Http::STATUS_OK, array<string, mixed>> */
	public function getForm(): TemplateResponse {
		return new TemplateResponse('user_lockdown', 'admin');
	}

	public function getSection(): string {
		return 'user_lockdown';
	}

	public function getPriority(): int {
		return 50;
	}
}
