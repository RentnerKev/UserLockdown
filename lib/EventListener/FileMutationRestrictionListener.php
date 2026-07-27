<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\EventListener;

use OCA\UserLockdown\Policy\Permission;
use OCA\UserLockdown\Service\RestrictionContext;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\AbstractNodeEvent;
use OCP\Files\Events\Node\AbstractNodesEvent;
use OCP\Files\Events\Node\BeforeNodeCopiedEvent;
use OCP\Files\Events\Node\BeforeNodeCreatedEvent;
use OCP\Files\Events\Node\BeforeNodeDeletedEvent;
use OCP\Files\Events\Node\BeforeNodeReadEvent;
use OCP\Files\Events\Node\BeforeNodeRenamedEvent;
use OCP\Files\Events\Node\BeforeNodeTouchedEvent;
use OCP\Files\Events\Node\BeforeNodeWrittenEvent;
use OCP\Files\Node;
use OCP\Files\NotPermittedException;
use OCP\IL10N;

/**
 * @implements IEventListener<BeforeNodeCopiedEvent|BeforeNodeCreatedEvent|BeforeNodeDeletedEvent|BeforeNodeReadEvent|BeforeNodeRenamedEvent|BeforeNodeTouchedEvent|BeforeNodeWrittenEvent>
 */
final class FileMutationRestrictionListener implements IEventListener {
	public function __construct(
		private readonly RestrictionContext $restrictionContext,
		private readonly IL10N $l10n,
	) {
	}

	public function handle(Event $event): void {
		$permission = $this->getRequiredPermission($event);
		if ($permission === null) {
			return;
		}

		$permissionSet = $this->restrictionContext->getPermissionSet();
		$userId = $this->restrictionContext->getRestrictedUserId();
		if (
			$permissionSet === null
			|| $userId === null
			|| $permissionSet->allows($permission)
			|| !$this->touchesUserFiles($event, $userId)
		) {
			return;
		}

		throw new NotPermittedException(
			$this->l10n->t('This action has been disabled by your administrator.'),
		);
	}

	private function getRequiredPermission(Event $event): ?Permission {
		return match (true) {
			$event instanceof BeforeNodeReadEvent => Permission::ViewFiles,
			$event instanceof BeforeNodeDeletedEvent => Permission::DeleteFiles,
			$event instanceof BeforeNodeCopiedEvent,
			$event instanceof BeforeNodeCreatedEvent,
			$event instanceof BeforeNodeRenamedEvent,
			$event instanceof BeforeNodeTouchedEvent,
			$event instanceof BeforeNodeWrittenEvent => Permission::WriteFiles,
			default => null,
		};
	}

	private function touchesUserFiles(Event $event, string $userId): bool {
		if ($event instanceof AbstractNodeEvent) {
			return $this->isUserFileNode($event->getNode(), $userId);
		}

		if ($event instanceof AbstractNodesEvent) {
			return $this->isUserFileNode($event->getSource(), $userId)
				|| $this->isUserFileNode($event->getTarget(), $userId);
		}

		return false;
	}

	private function isUserFileNode(Node $node, string $userId): bool {
		$path = $node->getPath();
		$absolutePrefix = '/' . $userId . '/files';
		return $path === $absolutePrefix
			|| str_starts_with($path, $absolutePrefix . '/')
			|| $path === '/files'
			|| str_starts_with($path, '/files/');
	}
}
