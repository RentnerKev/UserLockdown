<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Tests\Unit\EventListener;

use OCA\UserLockdown\EventListener\LoadRestrictedAssetsListener;
use OCA\UserLockdown\Policy\Permission;
use OCA\UserLockdown\Policy\PermissionSet;
use OCA\UserLockdown\Service\RestrictedAssetLoader;
use OCA\UserLockdown\Service\RestrictedUserService;
use OCA\UserLockdown\Service\RestrictionContext;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class LoadRestrictedAssetsListenerTest extends TestCase {
	public function testProvidesPermissionsForLoggedInRestrictedUser(): void {
		$permissionSet = PermissionSet::fromMask(
			Permission::ViewFiles->value | Permission::HideSideNavigation->value,
		);
		$initialState = $this->createMock(IInitialState::class);
		$initialState->expects(self::once())
			->method('provideInitialState')
			->with('permissions', $permissionSet->toArray());
		$assetLoader = $this->createMock(RestrictedAssetLoader::class);
		$assetLoader->expects(self::once())->method('load');
		$listener = new LoadRestrictedAssetsListener(
			$this->createContext($permissionSet),
			$initialState,
			$assetLoader,
		);

		$listener->handle(new BeforeTemplateRenderedEvent(
			true,
			new TemplateResponse('files', 'index'),
		));
	}

	public function testDoesNotProvidePermissionsForAnonymousTemplate(): void {
		$initialState = $this->createMock(IInitialState::class);
		$initialState->expects(self::never())->method('provideInitialState');
		$assetLoader = $this->createMock(RestrictedAssetLoader::class);
		$assetLoader->expects(self::never())->method('load');
		$listener = new LoadRestrictedAssetsListener(
			$this->createContext(PermissionSet::readOnly()),
			$initialState,
			$assetLoader,
		);

		$listener->handle(new BeforeTemplateRenderedEvent(
			false,
			new TemplateResponse('core', 'login'),
		));
	}

	public function testFullAccessDoesNotLoadRestrictedState(): void {
		$initialState = $this->createMock(IInitialState::class);
		$initialState->expects(self::never())->method('provideInitialState');
		$assetLoader = $this->createMock(RestrictedAssetLoader::class);
		$assetLoader->expects(self::never())->method('load');
		$listener = new LoadRestrictedAssetsListener(
			$this->createContext(PermissionSet::fullAccess()),
			$initialState,
			$assetLoader,
		);

		$listener->handle(new BeforeTemplateRenderedEvent(
			true,
			new TemplateResponse('files', 'index'),
		));
	}

	private function createContext(PermissionSet $permissionSet): RestrictionContext {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isAdmin')->with('alice')->willReturn(false);
		$service = $this->createMock(RestrictedUserService::class);
		$service->method('getPermissionSet')->with('alice')->willReturn($permissionSet);

		return new RestrictionContext($userSession, $groupManager, $service);
	}
}
