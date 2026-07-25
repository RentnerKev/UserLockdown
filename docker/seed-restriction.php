<?php

declare(strict_types=1);

/**
 * Development-only fixture seeder. This file is excluded from release archives.
 *
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

define('OC_CONSOLE', 1);

require_once '/var/www/html/lib/base.php';

\OC::$CLI = true;

/** @var \OCP\Files\IRootFolder $rootFolder */
$rootFolder = \OCP\Server::get(\OCP\Files\IRootFolder::class);
$userFolder = $rootFolder->getUserFolder('restricted');
if (!$userFolder->nodeExists('read-only.txt')) {
	$file = $userFolder->newFile('read-only.txt');
	$file->putContent("User Lockdown read-only test fixture.\n");
}

/** @var \OCA\UserLockdown\Service\RestrictedUserService $service */
$service = \OCP\Server::get(\OCA\UserLockdown\Service\RestrictedUserService::class);
if (!$service->isRestricted('restricted')) {
	$service->addRestrictedUser('restricted', 'admin');
}
