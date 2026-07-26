<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const SEMVER_PATTERN = '~^(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)(?:-(?:(?:0|[1-9][0-9]*)|(?:[0-9]*[A-Za-z-][0-9A-Za-z-]*))(?:\.(?:(?:0|[1-9][0-9]*)|(?:[0-9]*[A-Za-z-][0-9A-Za-z-]*)))*)?(?:\+[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?$~D';

function fail(string $message): never {
	fwrite(STDERR, $message . "\n");
	exit(1);
}

function validateVersion(string $version, string $source): string {
	$version = trim($version);
	if (preg_match(SEMVER_PATTERN, $version) !== 1) {
		fail("$source is not a valid semantic version: $version");
	}

	return $version;
}

function versionFromTag(string $tag, string $source): string {
	$tag = trim($tag);
	if (!str_starts_with($tag, 'v')) {
		fail("$source must use the v<SemVer> format: $tag");
	}

	return validateVersion(substr($tag, 1), $source);
}

$projectRoot = dirname(__DIR__);
$infoPath = $projectRoot . '/appinfo/info.xml';
$packagePath = $projectRoot . '/package.json';

$info = simplexml_load_file($infoPath);
if ($info === false) {
	fail('Could not parse appinfo/info.xml.');
}

$packageContents = file_get_contents($packagePath);
if ($packageContents === false) {
	fail('Could not read package.json.');
}

/** @var mixed $package */
$package = json_decode($packageContents, true, flags: JSON_THROW_ON_ERROR);
if (!is_array($package) || !is_string($package['version'] ?? null)) {
	fail('package.json has no valid version.');
}

$infoVersion = validateVersion((string)$info->version, 'appinfo/info.xml');
$packageVersion = validateVersion($package['version'], 'package.json');
$requestedTag = $argv[1] ?? null;
$tagVersion = is_string($requestedTag) && trim($requestedTag) !== ''
	? versionFromTag($requestedTag, 'release tag')
	: null;
$previousTag = $argv[2] ?? null;
$previousVersion = is_string($previousTag) && trim($previousTag) !== ''
	? versionFromTag($previousTag, 'previous release tag')
	: null;

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
	fail('Release versions do not match.');
}

if ($previousVersion !== null && version_compare($infoVersion, $previousVersion, '<=')) {
	fail("Release version $infoVersion must be newer than $previousVersion.");
}

fwrite(STDOUT, "Release version $infoVersion is consistent.\n");
