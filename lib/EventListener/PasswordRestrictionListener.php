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
use OCP\HintException;
use OCP\IL10N;
use OCP\User\Events\BeforePasswordUpdatedEvent;

/** @implements IEventListener<BeforePasswordUpdatedEvent> */
final class PasswordRestrictionListener implements IEventListener {
	public function __construct(
		private readonly RestrictionContext $restrictionContext,
		private readonly IL10N $l10n,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof BeforePasswordUpdatedEvent) {
			return;
		}

		$actorId = $this->restrictionContext->getRestrictedUserId();
		if ($actorId === null || $event->getUser()->getUID() !== $actorId) {
			return;
		}

		$message = $this->l10n->t('This action has been disabled by your administrator.');
		throw new HintException($message, $message);
	}
}
