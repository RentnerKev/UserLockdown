<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCP\Util;

Util::addScript('user_lockdown', 'user-lockdown-admin');
Util::addStyle('user_lockdown', 'user-lockdown-admin');
?>

<div id="user-lockdown-admin-root"></div>
