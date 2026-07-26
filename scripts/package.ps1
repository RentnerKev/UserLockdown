param(
	[ValidateSet('All', 'StageOnly', 'ArchiveStaged')]
	[string] $Mode = 'All',
	[switch] $RequireSignature
)

$ErrorActionPreference = 'Stop'

$appId = 'user_lockdown'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$buildDir = Join-Path $projectRoot 'build'
$stageRoot = Join-Path $buildDir 'appstore'
$stageDir = Join-Path $stageRoot $appId
$archive = Join-Path $buildDir "$appId.tar.gz"
$checksum = "$archive.sha256"

if ($Mode -ne 'ArchiveStaged') {
	Push-Location $projectRoot
	try {
		& bun install --frozen-lockfile
		if ($LASTEXITCODE -ne 0) { throw 'bun install failed.' }

		& bun run build
		if ($LASTEXITCODE -ne 0) { throw 'bun run build failed.' }
	} finally {
		Pop-Location
	}

	New-Item -ItemType Directory -Force -Path $buildDir | Out-Null
	$temporaryRoot = Join-Path $buildDir ('package.' + [guid]::NewGuid().ToString('N'))
	$temporaryApp = Join-Path $temporaryRoot $appId
	New-Item -ItemType Directory -Force -Path $temporaryApp | Out-Null

	$releaseInputs = @(
		'appinfo',
		'css',
		'img',
		'js',
		'l10n',
		'lib',
		'templates',
		'LICENSE'
	)

	foreach ($relativePath in $releaseInputs) {
		$source = Join-Path $projectRoot $relativePath
		if (-not (Test-Path -LiteralPath $source)) {
			throw "Missing release input: $relativePath"
		}

		Copy-Item -LiteralPath $source -Destination $temporaryApp -Recurse
	}

	$staleSignature = Join-Path $temporaryApp 'appinfo/signature.json'
	if (Test-Path -LiteralPath $staleSignature) {
		Remove-Item -LiteralPath $staleSignature -Force
	}

	if (Test-Path -LiteralPath $stageRoot) {
		$resolvedStageRoot = (Resolve-Path -LiteralPath $stageRoot).Path
		$resolvedBuildDir = (Resolve-Path -LiteralPath $buildDir).Path
		if (-not $resolvedStageRoot.StartsWith(
			$resolvedBuildDir + [IO.Path]::DirectorySeparatorChar,
			[StringComparison]::OrdinalIgnoreCase
		)) {
			throw "Refusing to remove path outside build directory: $resolvedStageRoot"
		}

		Remove-Item -LiteralPath $resolvedStageRoot -Recurse -Force
	}

	Move-Item -LiteralPath $temporaryRoot -Destination $stageRoot
}

if ($Mode -ne 'StageOnly') {
	if (-not (Test-Path -LiteralPath $stageDir -PathType Container)) {
		throw 'No staged app found. Run with -Mode StageOnly first.'
	}

	if ($RequireSignature -and -not (Test-Path -LiteralPath (Join-Path $stageDir 'appinfo/signature.json'))) {
		throw 'A signed appinfo/signature.json is required.'
	}

	$sourceDateEpoch = if ($env:SOURCE_DATE_EPOCH) { $env:SOURCE_DATE_EPOCH } else { '0' }
	if ($sourceDateEpoch -notmatch '^\d+$') {
		throw 'SOURCE_DATE_EPOCH must be an integer Unix timestamp.'
	}

	$dockerArguments = @(
		'run',
		'--rm',
		'--env',
		"SOURCE_DATE_EPOCH=$sourceDateEpoch"
	)
	if ($RequireSignature) {
		$dockerArguments += @('--env', 'REQUIRE_SIGNATURE=1')
	}
	$dockerArguments += @(
		'--volume',
		"${projectRoot}:/workspace",
		'--workdir',
		'/workspace',
		'debian:bookworm-slim',
		'bash',
		'scripts/package.sh',
		'--archive-staged'
	)

	& docker @dockerArguments
	if ($LASTEXITCODE -ne 0) {
		throw 'Creating the canonical release archive in Docker failed.'
	}

	if (-not (Test-Path -LiteralPath $archive) -or -not (Test-Path -LiteralPath $checksum)) {
		throw 'The release archive or checksum is missing.'
	}
}
