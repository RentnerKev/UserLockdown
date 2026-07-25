<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\AppInfo;

use OCA\DAV\Events\SabrePluginAddEvent;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\UserLockdown\EventListener\FileMutationRestrictionListener;
use OCA\UserLockdown\EventListener\LoadFilesAssetsListener;
use OCA\UserLockdown\EventListener\PasswordRestrictionListener;
use OCA\UserLockdown\EventListener\SabrePluginAddListener;
use OCA\UserLockdown\EventListener\ShareInteractionRestrictionListener;
use OCA\UserLockdown\EventListener\ShareRestrictionListener;
use OCA\UserLockdown\EventListener\UserDeletedListener;
use OCA\UserLockdown\Middleware\RestrictionMiddleware;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\Events\Node\BeforeNodeCopiedEvent;
use OCP\Files\Events\Node\BeforeNodeCreatedEvent;
use OCP\Files\Events\Node\BeforeNodeDeletedEvent;
use OCP\Files\Events\Node\BeforeNodeRenamedEvent;
use OCP\Files\Events\Node\BeforeNodeTouchedEvent;
use OCP\Files\Events\Node\BeforeNodeWrittenEvent;
use OCP\Interaction\RestrictInteractionEvent;
use OCP\Share\Events\BeforeShareCreatedEvent;
use OCP\User\Events\BeforePasswordUpdatedEvent;
use OCP\User\Events\UserDeletedEvent;

final class Application extends App implements IBootstrap {
	public const APP_ID = 'user_lockdown';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerMiddleware(RestrictionMiddleware::class, true);

		$context->registerEventListener(
			SabrePluginAddEvent::class,
			SabrePluginAddListener::class,
		);
		$context->registerEventListener(
			LoadAdditionalScriptsEvent::class,
			LoadFilesAssetsListener::class,
		);
		$context->registerEventListener(
			BeforeShareCreatedEvent::class,
			ShareRestrictionListener::class,
		);
		if (class_exists(RestrictInteractionEvent::class)) {
			$context->registerEventListener(
				RestrictInteractionEvent::class,
				ShareInteractionRestrictionListener::class,
			);
		}
		$context->registerEventListener(
			BeforePasswordUpdatedEvent::class,
			PasswordRestrictionListener::class,
		);
		$context->registerEventListener(
			UserDeletedEvent::class,
			UserDeletedListener::class,
		);

		foreach ([
			BeforeNodeCopiedEvent::class,
			BeforeNodeCreatedEvent::class,
			BeforeNodeDeletedEvent::class,
			BeforeNodeRenamedEvent::class,
			BeforeNodeTouchedEvent::class,
			BeforeNodeWrittenEvent::class,
		] as $eventClass) {
			$context->registerEventListener(
				$eventClass,
				FileMutationRestrictionListener::class,
			);
		}
	}

	public function boot(IBootContext $context): void {
	}
}
