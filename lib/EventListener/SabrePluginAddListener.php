<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\EventListener;

use OCA\DAV\Events\SabrePluginAddEvent;
use OCA\UserLockdown\Dav\ReadOnlyPlugin;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/** @implements IEventListener<SabrePluginAddEvent> */
final class SabrePluginAddListener implements IEventListener {
	public function __construct(
		private readonly ReadOnlyPlugin $plugin,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof SabrePluginAddEvent) {
			return;
		}

		$event->getServer()->addPlugin($this->plugin);
	}
}
