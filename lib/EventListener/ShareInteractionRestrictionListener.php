<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\EventListener;

use OCA\UserLockdown\Service\RestrictionContext;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IL10N;
use OCP\Interaction\Actions\ShareAction;
use OCP\Interaction\InteractionRestrictedException;
use OCP\Interaction\RestrictInteractionEvent;

/**
 * Nextcloud 34.0.2+ provides this public create/update share authorization gate.
 *
 * @implements IEventListener<RestrictInteractionEvent>
 */
final class ShareInteractionRestrictionListener implements IEventListener {
	public function __construct(
		private readonly RestrictionContext $restrictionContext,
		private readonly IL10N $l10n,
	) {
	}

	public function handle(Event $event): void {
		if (
			!$event instanceof RestrictInteractionEvent
			|| !$event->action instanceof ShareAction
			|| !$this->restrictionContext->isCurrentUserRestricted()
		) {
			return;
		}

		throw new InteractionRestrictedException(
			'User Lockdown blocked a share interaction.',
			$this->l10n->t('This action has been disabled by your administrator.'),
		);
	}
}
