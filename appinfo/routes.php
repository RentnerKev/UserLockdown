<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'routes' => [
		[
			'name' => 'page#index',
			'url' => '/admin',
			'verb' => 'GET',
		],
		[
			'name' => 'admin_api#index',
			'url' => '/api/restricted-users',
			'verb' => 'GET',
		],
		[
			'name' => 'admin_api#search',
			'url' => '/api/users/search',
			'verb' => 'GET',
		],
		[
			'name' => 'admin_api#create',
			'url' => '/api/restricted-users',
			'verb' => 'POST',
		],
		[
			'name' => 'admin_api#update',
			'url' => '/api/restricted-users/{userId}',
			'verb' => 'PUT',
			'requirements' => [
				'userId' => '.+',
			],
		],
		[
			'name' => 'admin_api#destroy',
			'url' => '/api/restricted-users/{userId}',
			'verb' => 'DELETE',
			'requirements' => [
				'userId' => '.+',
			],
		],
		[
			'name' => 'permission_settings_api#index',
			'url' => '/api/permission-settings',
			'verb' => 'GET',
		],
		[
			'name' => 'permission_settings_api#updateDefault',
			'url' => '/api/permission-settings/default',
			'verb' => 'PUT',
		],
		[
			'name' => 'permission_settings_api#createPreset',
			'url' => '/api/presets',
			'verb' => 'POST',
		],
		[
			'name' => 'permission_settings_api#updatePreset',
			'url' => '/api/presets/{presetId}',
			'verb' => 'PUT',
			'requirements' => [
				'presetId' => '.+',
			],
		],
		[
			'name' => 'permission_settings_api#destroyPreset',
			'url' => '/api/presets/{presetId}',
			'verb' => 'DELETE',
			'requirements' => [
				'presetId' => '.+',
			],
		],
	],
];
