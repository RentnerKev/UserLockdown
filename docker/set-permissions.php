<?php

declare(strict_types=1);

/**
 * Development-only permission helper. This file is excluded from release archives.
 *
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\UserLockdown\Policy\PermissionSet;
use OCA\UserLockdown\Service\RestrictedUserService;

define('OC_CONSOLE', 1);

require_once '/var/www/html/lib/base.php';

\OC::$CLI = true;

if ($argc !== 3 || preg_match('/^(?:0|[1-9][0-9]?|1[01][0-9]|12[0-7])$/D', $argv[2]) !== 1) {
	fwrite(STDERR, "Usage: set-permissions.php <user-id> <mask:0-127>\n");
	exit(2);
}

$userId = $argv[1];
$permissionSet = PermissionSet::fromMask((int)$argv[2]);

/** @var RestrictedUserService $service */
$service = \OCP\Server::get(RestrictedUserService::class);
$service->updatePermissions($userId, $permissionSet, 'admin');

fwrite(STDOUT, "Updated $userId to permission mask {$permissionSet->toMask()}.\n");
