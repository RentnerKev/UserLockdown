<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Tests\Unit\Controller;

use DomainException;
use InvalidArgumentException;
use OCA\UserLockdown\Controller\AdminApiController;
use OCA\UserLockdown\Policy\PermissionSet;
use OCA\UserLockdown\Service\RestrictedUserService;
use OCP\AppFramework\Http;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AdminApiControllerTest extends TestCase {
	/** @var RestrictedUserService&MockObject */
	private RestrictedUserService $service;

	/** @var IUserSession&MockObject */
	private IUserSession $userSession;

	/** @var LoggerInterface&MockObject */
	private LoggerInterface $logger;

	private AdminApiController $controller;

	protected function setUp(): void {
		parent::setUp();

		$request = $this->createMock(IRequest::class);
		$this->service = $this->createMock(RestrictedUserService::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkToRoute')
			->willReturnCallback(static fn (string $route, array $parameters): string => sprintf(
				'/index.php/avatar/%s/%d',
				$parameters['userId'],
				$parameters['size'],
			));
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text): string => $text);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->controller = new AdminApiController(
			'user_lockdown',
			$request,
			$this->service,
			$this->userSession,
			$urlGenerator,
			$l10n,
			$this->logger,
		);
	}

	public function testIndexReturnsStructuredUsers(): void {
		$this->service->method('getRestrictedUsers')->willReturn([
			[
				'id' => 'alice',
				'displayName' => 'Alice Example',
				'permissions' => PermissionSet::readOnly()->toArray(),
			],
		]);

		$response = $this->controller->index();

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame([
			'data' => [
				'users' => [[
					'id' => 'alice',
					'displayName' => 'Alice Example',
					'permissions' => PermissionSet::readOnly()->toArray(),
					'avatarUrl' => '/index.php/avatar/alice/64',
				]],
			],
		], $response->getData());
	}

	public function testUnexpectedFailureDoesNotExposeExceptionDetails(): void {
		$this->service->method('getRestrictedUsers')
			->willThrowException(new \RuntimeException('sensitive database details'));
		$this->logger->expects(self::once())->method('error');

		$response = $this->controller->index();

		self::assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		self::assertSame([
			'error' => [
				'code' => 'list_restricted_users_failed',
				'message' => 'Could not load restricted users.',
			],
		], $response->getData());
		self::assertStringNotContainsString('sensitive', json_encode($response->getData(), JSON_THROW_ON_ERROR));
	}

	public function testSearchRejectsEmptyQuery(): void {
		$this->service->expects(self::never())->method('searchUsers');

		$response = $this->controller->search('  ');

		self::assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
		self::assertSame([
			'error' => [
				'code' => 'validation_failed',
				'message' => 'The search query is invalid.',
			],
		], $response->getData());
	}

	public function testSearchReturnsRestrictedFlag(): void {
		$this->service->expects(self::once())
			->method('searchUsers')
			->with('alice', 20)
			->willReturn([[
				'id' => 'alice',
				'displayName' => 'Alice Example',
				'restricted' => true,
			]]);

		$response = $this->controller->search(' alice ');

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame([
			'data' => [
				'users' => [[
					'id' => 'alice',
					'displayName' => 'Alice Example',
					'restricted' => true,
					'avatarUrl' => '/index.php/avatar/alice/64',
				]],
			],
		], $response->getData());
	}

	public function testCreateReturnsCreatedUser(): void {
		$admin = $this->createMock(IUser::class);
		$admin->method('getUID')->willReturn('admin');
		$this->userSession->method('getUser')->willReturn($admin);
		$this->service->expects(self::once())
			->method('addRestrictedUser')
			->with('alice', 'admin');
		$this->service->method('getRestrictedUser')->with('alice')->willReturn([
			'id' => 'alice',
			'displayName' => 'Alice Example',
			'permissions' => PermissionSet::readOnly()->toArray(),
		]);

		$response = $this->controller->create('alice');

		self::assertSame(Http::STATUS_CREATED, $response->getStatus());
		self::assertSame([
			'data' => [
				'user' => [
					'id' => 'alice',
					'displayName' => 'Alice Example',
					'permissions' => PermissionSet::readOnly()->toArray(),
					'avatarUrl' => '/index.php/avatar/alice/64',
				],
			],
		], $response->getData());
	}

	public function testCreateRejectsAdministrator(): void {
		$admin = $this->createMock(IUser::class);
		$admin->method('getUID')->willReturn('admin');
		$this->userSession->method('getUser')->willReturn($admin);
		$this->service->method('addRestrictedUser')
			->willThrowException(new InvalidArgumentException(RestrictedUserService::ERROR_ADMIN_USER));

		$response = $this->controller->create('second-admin');

		self::assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
		self::assertSame([
			'error' => [
				'code' => 'validation_failed',
				'message' => 'Administrators cannot be restricted.',
			],
		], $response->getData());
	}

	public function testDestroyReturnsRemovedUserId(): void {
		$this->service->expects(self::once())
			->method('removeRestrictedUser')
			->with('alice');

		$response = $this->controller->destroy('alice');

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame(['data' => ['userId' => 'alice']], $response->getData());
	}

	public function testUpdateReturnsUserWithCanonicalPermissions(): void {
		$admin = $this->createMock(IUser::class);
		$admin->method('getUID')->willReturn('admin');
		$this->userSession->method('getUser')->willReturn($admin);
		$permissions = PermissionSet::fromMask(5);
		$this->service->expects(self::once())
			->method('updatePermissions')
			->with(
				'alice',
				self::callback(static fn (PermissionSet $set): bool => $set->toMask() === 5),
				'admin',
			);
		$this->service->method('getRestrictedUser')->with('alice')->willReturn([
			'id' => 'alice',
			'displayName' => 'Alice Example',
			'permissions' => $permissions->toArray(),
		]);

		$response = $this->controller->update('alice', $permissions->toArray());

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame([
			'data' => [
				'user' => [
					'id' => 'alice',
					'displayName' => 'Alice Example',
					'permissions' => $permissions->toArray(),
					'avatarUrl' => '/index.php/avatar/alice/64',
				],
			],
		], $response->getData());
	}

	public function testUpdateRejectsDependentPermissionWithoutFileAccess(): void {
		$this->service->expects(self::never())->method('updatePermissions');

		$response = $this->controller->update('alice', [
			...PermissionSet::blocked()->toArray(),
			'deleteFiles' => true,
		]);

		self::assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
		self::assertSame('validation_failed', $response->getData()['error']['code']);
	}

	public function testUpdateReturnsNotFoundForUnmanagedUser(): void {
		$admin = $this->createMock(IUser::class);
		$admin->method('getUID')->willReturn('admin');
		$this->userSession->method('getUser')->willReturn($admin);
		$this->service->method('updatePermissions')
			->willThrowException(new DomainException(RestrictedUserService::ERROR_NOT_RESTRICTED));

		$response = $this->controller->update('alice', PermissionSet::readOnly()->toArray());

		self::assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		self::assertSame('not_restricted', $response->getData()['error']['code']);
	}
}
