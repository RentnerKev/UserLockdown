<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

require_once __DIR__ . '/vendor/autoload.php';

use Nextcloud\CodingStandard\Config;
use PhpCsFixerCustomFixers\Fixer\MultilinePromotedPropertiesFixer;

$config = new class() extends Config {
	public function getRules(): array {
		$rules = parent::getRules();

		// coding-standard 1.5 still selects the deprecated custom rule, while
		// current PHP CS Fixer provides the maintained built-in replacement.
		unset($rules[MultilinePromotedPropertiesFixer::name()]);
		$rules['multiline_promoted_properties'] = true;

		return $rules;
	}
};
$config
	->getFinder()
	->ignoreVCSIgnored(true)
	->notPath('build')
	->notPath('css')
	->notPath('docker')
	->notPath('js')
	->notPath('l10n')
	->notPath('node_modules')
	->notPath('src')
	->notPath('vendor')
	->in(__DIR__);

return $config;
