<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Tests\Unit\EventListener;

use OCA\UserLockdown\EventListener\ShareInteractionRestrictionListener;
use OCA\UserLockdown\Policy\PermissionSet;
use OCA\UserLockdown\Service\RestrictedUserService;
use OCA\UserLockdown\Service\RestrictionContext;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\Interaction\Actions\ShareAction;
use OCP\Interaction\InteractionRestrictedException;
use OCP\Interaction\RestrictInteractionEvent;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

final class ShareInteractionRestrictionListenerTest extends TestCase {
	public function testRestrictedUserCannotCreateOrUpdateShare(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isAdmin')->with('alice')->willReturn(false);
		$restrictedUserService = $this->createMock(RestrictedUserService::class);
		$restrictedUserService->method('getPermissionSet')
			->with('alice')
			->willReturn(PermissionSet::readOnly());
		$context = new RestrictionContext(
			$userSession,
			$groupManager,
			$restrictedUserService,
		);
		$l10n = $this->createMock(IL10N::class);
		$l10n->expects(self::once())
			->method('t')
			->with('This action has been disabled by your administrator.')
			->willReturn('Sharing is disabled.');
		$listener = new ShareInteractionRestrictionListener($context, $l10n);
		$event = new RestrictInteractionEvent(
			'alice',
			$user,
			[],
			new ShareAction(),
			[],
		);

		try {
			$listener->handle($event);
			self::fail('The share interaction was not blocked.');
		} catch (InteractionRestrictedException $exception) {
			self::assertSame('Sharing is disabled.', $exception->getHint());
		}
	}

	public function testSharePermissionAllowsShareInteraction(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isAdmin')->with('alice')->willReturn(false);
		$restrictedUserService = $this->createMock(RestrictedUserService::class);
		$restrictedUserService->method('getPermissionSet')
			->with('alice')
			->willReturn(PermissionSet::fromArray([
				'viewFiles' => true,
				'writeFiles' => false,
				'deleteFiles' => false,
				'shareFiles' => true,
				'changePassword' => false,
				'hideSideNavigation' => false,
				'fullAccess' => false,
			]));
		$l10n = $this->createMock(IL10N::class);
		$l10n->expects(self::never())->method('t');
		$listener = new ShareInteractionRestrictionListener(
			new RestrictionContext($userSession, $groupManager, $restrictedUserService),
			$l10n,
		);
		$event = new RestrictInteractionEvent('alice', $user, [], new ShareAction(), []);

		$listener->handle($event);

		$this->addToAssertionCount(1);
	}
}
