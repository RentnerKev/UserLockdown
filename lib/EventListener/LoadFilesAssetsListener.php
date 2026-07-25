<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\EventListener;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\UserLockdown\Service\RestrictionContext;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/** @implements IEventListener<LoadAdditionalScriptsEvent> */
final class LoadFilesAssetsListener implements IEventListener {
	public function __construct(
		private readonly RestrictionContext $restrictionContext,
	) {
	}

	public function handle(Event $event): void {
		if (
			!$event instanceof LoadAdditionalScriptsEvent
			|| !$this->restrictionContext->isCurrentUserRestricted()
		) {
			return;
		}

		Util::addInitScript('user_lockdown', 'user-lockdown-files');
		Util::addStyle('user_lockdown', 'user-lockdown-files');
	}
}
