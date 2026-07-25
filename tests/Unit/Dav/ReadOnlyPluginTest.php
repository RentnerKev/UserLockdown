<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Tests\Unit\Dav;

use OCA\UserLockdown\Dav\ReadOnlyPlugin;
use OCA\UserLockdown\Service\RestrictedUserService;
use OCA\UserLockdown\Service\RestrictionContext;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabre\DAV\Exception\Forbidden;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

class ReadOnlyPluginTest extends TestCase {
	/**
	 * @return iterable<string, array{string}>
	 */
	public static function allowedMethodProvider(): iterable {
		yield 'GET' => ['GET'];
		yield 'HEAD' => ['HEAD'];
		yield 'OPTIONS' => ['OPTIONS'];
		yield 'PROPFIND' => ['PROPFIND'];
		yield 'REPORT' => ['REPORT'];
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function blockedMethodProvider(): iterable {
		yield 'PUT' => ['PUT'];
		yield 'POST' => ['POST'];
		yield 'PATCH' => ['PATCH'];
		yield 'DELETE' => ['DELETE'];
		yield 'MKCOL' => ['MKCOL'];
		yield 'MOVE' => ['MOVE'];
		yield 'COPY' => ['COPY'];
		yield 'PROPPATCH' => ['PROPPATCH'];
	}

	/**
	 * @return iterable<string, array{string, string, string}>
	 */
	public static function ownFilesPathProvider(): iterable {
		yield 'files root' => ['GET', 'files/alice', 'alice'];
		yield 'nested file' => ['HEAD', 'files/alice/Documents/report.pdf', 'alice'];
		yield 'upload collection' => ['PROPFIND', 'uploads/alice/upload-id', 'alice'];
		yield 'encoded user id' => ['GET', 'files/alice%2Bexternal/report.pdf', 'alice+external'];
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function foreignPathProvider(): iterable {
		yield 'another user files' => ['files/bob/Documents'];
		yield 'calendar collection' => ['calendars/alice/personal'];
		yield 'missing user segment' => ['files'];
		yield 'unknown collection' => ['addressbooks/alice/contacts'];
	}

	#[DataProvider('allowedMethodProvider')]
	public function testAllowsReadMethodsForRestrictedUser(string $method): void {
		$plugin = $this->createPlugin();

		$plugin->guardRequest(
			$this->createDavRequest(strtolower($method), 'files/alice/Documents'),
			$this->createMock(ResponseInterface::class),
		);
	}

	#[DataProvider('blockedMethodProvider')]
	public function testBlocksWriteMethodsForRestrictedUser(string $method): void {
		$plugin = $this->createPlugin();

		$this->expectException(Forbidden::class);
		$this->expectExceptionMessage('This action has been disabled by your administrator.');

		$plugin->guardRequest(
			$this->createDavRequest($method, 'files/alice/Documents'),
			$this->createMock(ResponseInterface::class),
		);
	}

	#[DataProvider('ownFilesPathProvider')]
	public function testAllowsReadAccessToOwnFilesAndUploadCollections(
		string $method,
		string $path,
		string $userId,
	): void {
		$plugin = $this->createPlugin(userId: $userId);

		$plugin->guardRequest(
			$this->createDavRequest($method, $path),
			$this->createMock(ResponseInterface::class),
		);
	}

	#[DataProvider('foreignPathProvider')]
	public function testBlocksReadAccessOutsideOwnFiles(string $path): void {
		$plugin = $this->createPlugin();

		$this->expectException(Forbidden::class);

		$plugin->guardRequest(
			$this->createDavRequest('GET', $path),
			$this->createMock(ResponseInterface::class),
		);
	}

	public function testAllowsOptionsAtDavRoot(): void {
		$plugin = $this->createPlugin();

		$plugin->guardRequest(
			$this->createDavRequest('OPTIONS', '/'),
			$this->createMock(ResponseInterface::class),
		);
	}

	public function testLegacyWebdavEndpointAllowsReadMethodRegardlessOfPath(): void {
		$plugin = $this->createPlugin(requestUri: '/remote.php/webdav/Documents/report.pdf');

		$plugin->guardRequest(
			$this->createDavRequest('GET', 'unrelated/path'),
			$this->createMock(ResponseInterface::class),
		);
	}

	public function testLegacyWebdavEndpointStillBlocksWriteMethod(): void {
		$plugin = $this->createPlugin(requestUri: '/remote.php/webdav/Documents/report.pdf');

		$this->expectException(Forbidden::class);

		$plugin->guardRequest(
			$this->createDavRequest('PUT', 'Documents/report.pdf'),
			$this->createMock(ResponseInterface::class),
		);
	}

	public function testLegacyWebdavMarkerInQueryDoesNotBypassDavPathBoundary(): void {
		$plugin = $this->createPlugin(
			requestUri: '/remote.php/dav/calendars/alice/personal?next=/remote.php/webdav',
		);

		$this->expectException(Forbidden::class);

		$plugin->guardRequest(
			$this->createDavRequest('GET', 'calendars/alice/personal'),
			$this->createMock(ResponseInterface::class),
		);
	}

	public function testAdministratorContextBypassesDavRestrictions(): void {
		$plugin = $this->createPlugin(admin: true);

		$plugin->guardRequest(
			$this->createDavRequest('PUT', 'calendars/admin/personal'),
			$this->createMock(ResponseInterface::class),
		);
	}

	public function testUnrestrictedUserContextBypassesDavRestrictions(): void {
		$plugin = $this->createPlugin(restricted: false);

		$plugin->guardRequest(
			$this->createDavRequest('DELETE', 'files/alice/Documents'),
			$this->createMock(ResponseInterface::class),
		);
	}

	public function testMissingUserContextBypassesDavRestrictions(): void {
		$this->expectNotToPerformAssertions();
		$plugin = $this->createPlugin(userId: null);

		$plugin->guardRequest(
			$this->createDavRequest('MKCOL', 'files/anonymous/New'),
			$this->createMock(ResponseInterface::class),
		);
	}

	public function testReturnsStablePluginName(): void {
		self::assertSame('user-lockdown-read-only', $this->createPlugin()->getPluginName());
	}

	private function createPlugin(
		string $requestUri = '/remote.php/dav/files/alice/Documents',
		?string $userId = 'alice',
		bool $restricted = true,
		bool $admin = false,
	): ReadOnlyPlugin {
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
			$restrictedUserService->method('isRestricted')->with($userId)->willReturn($restricted);
		}

		$nextcloudRequest = $this->createMock(IRequest::class);
		$nextcloudRequest->method('getRequestUri')->willReturn($requestUri);

		return new ReadOnlyPlugin(
			new RestrictionContext($userSession, $groupManager, $restrictedUserService),
			$nextcloudRequest,
		);
	}

	private function createDavRequest(string $method, string $path): RequestInterface {
		$request = $this->createMock(RequestInterface::class);
		$request->method('getMethod')->willReturn($method);
		$request->method('getPath')->willReturn($path);

		return $request;
	}
}
