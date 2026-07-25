<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$projectRoot = dirname(__DIR__);
$infoPath = $projectRoot . '/appinfo/info.xml';
$packagePath = $projectRoot . '/package.json';

$info = simplexml_load_file($infoPath);
if ($info === false) {
	fwrite(STDERR, "Could not parse appinfo/info.xml.\n");
	exit(1);
}

$packageContents = file_get_contents($packagePath);
if ($packageContents === false) {
	fwrite(STDERR, "Could not read package.json.\n");
	exit(1);
}

/** @var mixed $package */
$package = json_decode($packageContents, true, flags: JSON_THROW_ON_ERROR);
if (!is_array($package) || !is_string($package['version'] ?? null)) {
	fwrite(STDERR, "package.json has no valid version.\n");
	exit(1);
}

$infoVersion = trim((string)$info->version);
$packageVersion = $package['version'];
$requestedTag = $argv[1] ?? null;
$tagVersion = is_string($requestedTag) ? ltrim(trim($requestedTag), 'v') : null;

$versions = [
	'appinfo/info.xml' => $infoVersion,
	'package.json' => $packageVersion,
];
if ($tagVersion !== null && $tagVersion !== '') {
	$versions['release tag'] = $tagVersion;
}

if (count(array_unique($versions)) !== 1) {
	foreach ($versions as $source => $version) {
		fwrite(STDERR, "$source: $version\n");
	}
	fwrite(STDERR, "Release versions do not match.\n");
	exit(1);
}

fwrite(STDOUT, "Release version $infoVersion is consistent.\n");
