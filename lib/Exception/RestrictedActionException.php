<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Exception;

use RuntimeException;

final class RestrictedActionException extends RuntimeException {
	public function __construct(
		private readonly bool $apiRequest,
		string $message,
		private readonly bool $hideAccountState = false,
	) {
		parent::__construct($message);
	}

	public function isApiRequest(): bool {
		return $this->apiRequest;
	}

	public function shouldHideAccountState(): bool {
		return $this->hideAccountState;
	}
}
