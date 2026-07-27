<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Service;

use OCP\Util;

class RestrictedAssetLoader {
	public function load(): void {
		Util::addInitScript('user_lockdown', 'user-lockdown-files');
		Util::addStyle('user_lockdown', 'user-lockdown-files');
	}
}
