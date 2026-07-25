<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;

class PageController extends Controller {
	public function __construct(string $appName, IRequest $request) {
		parent::__construct($appName, $request);
	}

	/** @return TemplateResponse<\OCP\AppFramework\Http::STATUS_OK, array<string, mixed>> */
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		return new TemplateResponse($this->appName, 'admin');
	}
}
