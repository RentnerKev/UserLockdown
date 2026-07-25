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
			'name' => 'admin_api#destroy',
			'url' => '/api/restricted-users/{userId}',
			'verb' => 'DELETE',
			'requirements' => [
				'userId' => '.+',
			],
		],
	],
];
