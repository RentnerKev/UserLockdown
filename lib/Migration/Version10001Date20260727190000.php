<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version10001Date20260727190000 extends SimpleMigrationStep {
	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options,
	): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable('user_lockdown_users')) {
			return null;
		}

		$table = $schema->getTable('user_lockdown_users');
		if ($table->hasColumn('permissions')) {
			return null;
		}

		$table->addColumn('permissions', Types::INTEGER, [
			'default' => 1,
			'notnull' => true,
			'unsigned' => true,
		]);

		return $schema;
	}
}
