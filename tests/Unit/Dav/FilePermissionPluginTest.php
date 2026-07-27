<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Tests\Unit\Dav;

use OCA\UserLockdown\Dav\FilePermissionPlugin;
use OCA\UserLockdown\Policy\PermissionSet;
use OCA\UserLockdown\Service\RestrictedUserService;
use OCA\UserLockdown\Service\RestrictionContext;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Server;
use Sabre\DAV\SimpleCollection;
use Sabre\DAV\SimpleFile;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

class FilePermissionPluginTest extends TestCase {
	/** @return iterable<string, array{string}> */
	public static function readMethodProvider(): iterable {
		yield 'GET' => ['GET'];
		yield 'HEAD' => ['HEAD'];
		yield 'OPTIONS' => ['OPTIONS'];
		yield 'PROPFIND' => ['PROPFIND'];
		yield 'REPORT' => ['REPORT'];
	}

	/** @return iterable<string, array{string}> */
	public static function writeMethodProvider(): iterable {
		yield 'PUT' => ['PUT'];
		yield 'POST' => ['POST'];
		yield 'PATCH' => ['PATCH'];
		yield 'MKCOL' => ['MKCOL'];
		yield 'PROPPATCH' => ['PROPPATCH'];
		yield 'LOCK' => ['LOCK'];
		yield 'UNLOCK' => ['UNLOCK'];
	}

	#[DataProvider('readMethodProvider')]
	public function testViewPermissionAllowsReadMethods(string $method): void {
		$plugin = $this->createPlugin(PermissionSet::readOnly());

		$plugin->guardRequest(
			$this->createDavRequest($method, 'files/alice/Documents'),
			$this->createMock(ResponseInterface::class),
		);
	}

	#[DataProvider('readMethodProvider')]
	public function testMissingViewPermissionBlocksReadMethods(string $method): void {
		$plugin = $this->createPlugin(PermissionSet::blocked());

		$this->expectException(Forbidden::class);
		$plugin->guardRequest(
			$this->createDavRequest($method, 'files/alice/Documents'),
			$this->createMock(ResponseInterface::class),
		);
	}

	#[DataProvider('writeMethodProvider')]
	public function testWritePermissionAllowsWriteMethods(string $method): void {
		$plugin = $this->createPlugin($this->permissions(write: true));

		$plugin->guardRequest(
			$this->createDavRequest($method, 'files/alice/Documents'),
			$this->createMock(ResponseInterface::class),
		);
	}

	#[DataProvider('writeMethodProvider')]
	public function testMissingWritePermissionBlocksWriteMethods(string $method): void {
		$plugin = $this->createPlugin(PermissionSet::readOnly());

		$this->expectException(Forbidden::class);
		$this->expectExceptionMessage('This action has been disabled by your administrator.');
		$plugin->guardRequest(
			$this->createDavRequest($method, 'files/alice/Documents'),
			$this->createMock(ResponseInterface::class),
		);
	}

	public function testDeleteFilesRequiresDeletePermission(): void {
		$plugin = $this->createPlugin($this->permissions(write: true));

		$this->expectException(Forbidden::class);
		$plugin->guardRequest(
			$this->createDavRequest('DELETE', 'files/alice/Documents/report.txt'),
			$this->createMock(ResponseInterface::class),
		);
	}

	public function testDeletePermissionAllowsFileDeletion(): void {
		$plugin = $this->createPlugin($this->permissions(delete: true));

		$plugin->guardRequest(
			$this->createDavRequest('DELETE', 'files/alice/Documents/report.txt'),
			$this->createMock(ResponseInterface::class),
		);
	}

	public function testWritePermissionAllowsUploadChunkCleanup(): void {
		$plugin = $this->createPlugin($this->permissions(write: true));

		$plugin->guardRequest(
			$this->createDavRequest('DELETE', 'uploads/alice/upload-id'),
			$this->createMock(ResponseInterface::class),
		);
	}

	public function testMoveToNewOwnDestinationRequiresOnlyWritePermission(): void {
		$plugin = $this->createInitializedPlugin($this->permissions(write: true));
		$request = $this->createDavRequest('MOVE', 'files/alice/source.txt', [
			'Destination' => 'http://localhost/remote.php/dav/files/alice/new.txt',
		]);

		$plugin->guardRequest($request, $this->createMock(ResponseInterface::class));
	}

	public function testMoveOverwriteRequiresDeletePermission(): void {
		$plugin = $this->createInitializedPlugin($this->permissions(write: true));
		$request = $this->createDavRequest('MOVE', 'files/alice/source.txt', [
			'Destination' => 'http://localhost/remote.php/dav/files/alice/existing.txt',
		]);

		$this->expectException(Forbidden::class);
		$plugin->guardRequest($request, $this->createMock(ResponseInterface::class));
	}

	public function testMoveOverwriteIsAllowedWithDeletePermission(): void {
		$plugin = $this->createInitializedPlugin($this->permissions(write: true, delete: true));
		$request = $this->createDavRequest('MOVE', 'files/alice/source.txt', [
			'Destination' => 'http://localhost/remote.php/dav/files/alice/existing.txt',
		]);

		$plugin->guardRequest($request, $this->createMock(ResponseInterface::class));
	}

	public function testExplicitNoOverwriteDoesNotRequireDeletePermission(): void {
		$plugin = $this->createInitializedPlugin($this->permissions(write: true));
		$request = $this->createDavRequest('COPY', 'files/alice/source.txt', [
			'Destination' => 'http://localhost/remote.php/dav/files/alice/existing.txt',
			'Overwrite' => 'F',
		]);

		$plugin->guardRequest($request, $this->createMock(ResponseInterface::class));
	}

	public function testMoveToForeignDavNamespaceIsBlocked(): void {
		$plugin = $this->createInitializedPlugin($this->permissions(write: true));
		$request = $this->createDavRequest('MOVE', 'files/alice/source.txt', [
			'Destination' => 'http://localhost/remote.php/dav/calendars/alice/personal',
		]);

		$this->expectException(Forbidden::class);
		$plugin->guardRequest($request, $this->createMock(ResponseInterface::class));
	}

	/** @return iterable<string, array{string}> */
	public static function foreignPathProvider(): iterable {
		yield 'another user files' => ['files/bob/Documents'];
		yield 'calendar collection' => ['calendars/alice/personal'];
		yield 'encoded traversal' => ['files/alice/../bob/Documents'];
		yield 'double encoded separator' => ['files/alice%2F..%2Fbob/Documents'];
		yield 'missing user segment' => ['files'];
		yield 'unknown collection' => ['addressbooks/alice/contacts'];
	}

	#[DataProvider('foreignPathProvider')]
	public function testRestrictedPolicyBlocksOtherDavNamespaces(string $path): void {
		$plugin = $this->createPlugin(PermissionSet::readOnly());

		$this->expectException(Forbidden::class);
		$plugin->guardRequest(
			$this->createDavRequest('GET', $path),
			$this->createMock(ResponseInterface::class),
		);
	}

	public function testAllowsOptionsAtDavRootWithoutFilePermission(): void {
		$plugin = $this->createPlugin(PermissionSet::blocked());

		$plugin->guardRequest(
			$this->createDavRequest('OPTIONS', '/'),
			$this->createMock(ResponseInterface::class),
		);
	}

	public function testUnknownMethodFailsClosed(): void {
		$plugin = $this->createPlugin($this->permissions(write: true, delete: true));

		$this->expectException(Forbidden::class);
		$plugin->guardRequest(
			$this->createDavRequest('ACL', 'files/alice/Documents'),
			$this->createMock(ResponseInterface::class),
		);
	}

	public function testLegacyWebdavUsesTheCurrentUsersFilesNamespace(): void {
		$plugin = $this->createPlugin(
			PermissionSet::readOnly(),
			requestUri: '/remote.php/webdav/Documents/report.pdf',
		);

		$plugin->guardRequest(
			$this->createDavRequest('GET', 'Documents/report.pdf'),
			$this->createMock(ResponseInterface::class),
		);
	}

	public function testLegacyWebdavStillRequiresTheMatchingPermission(): void {
		$plugin = $this->createPlugin(
			PermissionSet::readOnly(),
			requestUri: '/remote.php/webdav/Documents/report.pdf',
		);

		$this->expectException(Forbidden::class);
		$plugin->guardRequest(
			$this->createDavRequest('PUT', 'Documents/report.pdf'),
			$this->createMock(ResponseInterface::class),
		);
	}

	public function testLegacyWebdavMarkerInQueryDoesNotBypassDavPathBoundary(): void {
		$plugin = $this->createPlugin(
			PermissionSet::readOnly(),
			requestUri: '/remote.php/dav/calendars/alice/personal?next=/remote.php/webdav',
		);

		$this->expectException(Forbidden::class);
		$plugin->guardRequest(
			$this->createDavRequest('GET', 'calendars/alice/personal'),
			$this->createMock(ResponseInterface::class),
		);
	}

	public function testFullAccessPolicyBypassesDavRestrictions(): void {
		$plugin = $this->createPlugin(PermissionSet::fullAccess());

		$plugin->guardRequest(
			$this->createDavRequest('DELETE', 'calendars/alice/personal'),
			$this->createMock(ResponseInterface::class),
		);
	}

	public function testUnmanagedAndAdministratorContextsBypassDavRestrictions(): void {
		foreach ([
			$this->createPlugin(null),
			$this->createPlugin(null, admin: true),
		] as $plugin) {
			$plugin->guardRequest(
				$this->createDavRequest('DELETE', 'calendars/alice/personal'),
				$this->createMock(ResponseInterface::class),
			);
		}

		$this->addToAssertionCount(2);
	}

	public function testMissingUserContextBypassesDavRestrictions(): void {
		$plugin = $this->createPlugin(PermissionSet::blocked(), userId: null);

		$plugin->guardRequest(
			$this->createDavRequest('MKCOL', 'files/anonymous/New'),
			$this->createMock(ResponseInterface::class),
		);
		$this->addToAssertionCount(1);
	}

	public function testReturnsStablePluginName(): void {
		self::assertSame(
			'user-lockdown-file-permissions',
			$this->createPlugin(PermissionSet::readOnly())->getPluginName(),
		);
	}

	private function createInitializedPlugin(PermissionSet $permissionSet): FilePermissionPlugin {
		$plugin = $this->createPlugin($permissionSet);
		$server = new Server([
			new SimpleCollection('files', [
				new SimpleCollection('alice', [
					new SimpleFile('existing.txt', 'existing'),
				]),
			]),
			new SimpleCollection('uploads', [
				new SimpleCollection('alice'),
			]),
		]);
		$server->setBaseUri('/remote.php/dav/');
		$plugin->initialize($server);

		return $plugin;
	}

	private function createPlugin(
		?PermissionSet $permissionSet,
		string $requestUri = '/remote.php/dav/files/alice/Documents',
		?string $userId = 'alice',
		bool $admin = false,
	): FilePermissionPlugin {
		$userSession = $this->createMock(IUserSession::class);
		$groupManager = $this->createMock(IGroupManager::class);
		$restrictedUserService = $this->createMock(RestrictedUserService::class);

		if ($userId === null) {
			$userSession->method('getUser')->willReturn(null);
		} else {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($userId);
			$userSession->method('getUser')->willReturn($user);
			$groupManager->method('isAdmin')->with($userId)->willReturn($admin);
			if ($admin) {
				$restrictedUserService->expects(self::never())->method('getPermissionSet');
			} else {
				$restrictedUserService->method('getPermissionSet')
					->with($userId)
					->willReturn($permissionSet);
			}
		}

		$nextcloudRequest = $this->createMock(IRequest::class);
		$nextcloudRequest->method('getRequestUri')->willReturn($requestUri);

		return new FilePermissionPlugin(
			new RestrictionContext($userSession, $groupManager, $restrictedUserService),
			$nextcloudRequest,
		);
	}

	/** @param array<string, string> $headers */
	private function createDavRequest(
		string $method,
		string $path,
		array $headers = [],
	): RequestInterface {
		$request = $this->createMock(RequestInterface::class);
		$request->method('getMethod')->willReturn($method);
		$request->method('getPath')->willReturn($path);
		$request->method('getHeader')
			->willReturnCallback(static fn (string $name): ?string => $headers[$name] ?? null);

		return $request;
	}

	private function permissions(bool $write = false, bool $delete = false): PermissionSet {
		return PermissionSet::fromArray([
			'viewFiles' => true,
			'writeFiles' => $write,
			'deleteFiles' => $delete,
			'shareFiles' => false,
			'changePassword' => false,
			'fullAccess' => false,
		]);
	}
}
