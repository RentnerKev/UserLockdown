<?php

declare(strict_types=1);

/**
 * Standalone unit-test stubs for documented cross-app events that are not part
 * of the nextcloud/ocp Composer package. Production uses Nextcloud's classes.
 *
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAV\Events {
	use OCP\EventDispatcher\Event;
	use Sabre\DAV\Server;

	if (!class_exists(SabrePluginAddEvent::class)) {
		class SabrePluginAddEvent extends Event {
			public function __construct(
				private readonly Server $server,
			) {
				parent::__construct();
			}

			public function getServer(): Server {
				return $this->server;
			}
		}
	}
}

namespace OCA\Files\Event {
	use OCP\EventDispatcher\Event;

	if (!class_exists(LoadAdditionalScriptsEvent::class)) {
		class LoadAdditionalScriptsEvent extends Event {
		}
	}
}

namespace OCA\Text\Controller {
	if (!interface_exists(ISessionAwareController::class)) {
		interface ISessionAwareController {
			public function getUserId(): string;
		}
	}
}
