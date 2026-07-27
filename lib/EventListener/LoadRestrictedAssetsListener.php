<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\EventListener;

use OCA\UserLockdown\Service\RestrictedAssetLoader;
use OCA\UserLockdown\Service\RestrictionContext;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/** @implements IEventListener<BeforeTemplateRenderedEvent> */
final class LoadRestrictedAssetsListener implements IEventListener {
	public function __construct(
		private readonly RestrictionContext $restrictionContext,
		private readonly IInitialState $initialState,
		private readonly RestrictedAssetLoader $assetLoader,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof BeforeTemplateRenderedEvent || !$event->isLoggedIn()) {
			return;
		}

		$permissionSet = $this->restrictionContext->getPermissionSet();
		if ($permissionSet === null) {
			return;
		}

		$this->initialState->provideInitialState('permissions', $permissionSet->toArray());
		$this->assetLoader->load();
	}
}
