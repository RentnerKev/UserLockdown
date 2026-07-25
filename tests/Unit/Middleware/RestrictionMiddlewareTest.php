<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files\Controller {
	use OCP\AppFramework\Controller;
	use OCP\IRequest;

	final class ViewController extends Controller {
		public function __construct(IRequest $request) {
			parent::__construct('files', $request);
		}
	}
}

namespace OCA\Files_Sharing\Controller {
	use OCP\AppFramework\Controller;
	use OCP\IRequest;

	final class AcceptController extends Controller {
		public function __construct(IRequest $request) {
			parent::__construct('files_sharing', $request);
		}
	}

	final class ShareAPIController extends Controller {
		public function __construct(IRequest $request) {
			parent::__construct('files_sharing', $request);
		}
	}
}

namespace OCA\Text\Controller {
	use OCP\AppFramework\Controller;
	use OCP\IRequest;
	use RuntimeException;

	final class SessionController extends Controller implements ISessionAwareController {
		public function __construct(
			IRequest $request,
			private readonly ?string $sessionUserId = null,
		) {
			parent::__construct('text', $request);
		}

		public function getUserId(): string {
			return $this->sessionUserId
				?? throw new RuntimeException('No Text session user was resolved.');
		}
	}

	final class AttachmentController extends Controller implements ISessionAwareController {
		public function __construct(
			IRequest $request,
			private readonly ?string $sessionUserId = null,
		) {
			parent::__construct('text', $request);
		}

		public function getUserId(): string {
			return $this->sessionUserId
				?? throw new RuntimeException('No Text session user was resolved.');
		}
	}
}

namespace OC\Core\Controller {
	use OCP\AppFramework\Controller;
	use OCP\IRequest;

	final class LostController extends Controller {
		public function __construct(IRequest $request) {
			parent::__construct('core', $request);
		}
	}

	final class TwoFactorChallengeController extends Controller {
		public function __construct(IRequest $request) {
			parent::__construct('core', $request);
		}
	}
}

namespace OCA\UserLockdown\Tests\Unit\Middleware {
	use OC\Core\Controller\LostController;
	use OC\Core\Controller\TwoFactorChallengeController;
	use OCA\Files\Controller\ViewController;
	use OCA\Files_Sharing\Controller\AcceptController;
	use OCA\Files_Sharing\Controller\ShareAPIController;
	use OCA\Text\Controller\AttachmentController;
	use OCA\Text\Controller\SessionController;
	use OCA\UserLockdown\Compatibility\TextSessionGuard;
	use OCA\UserLockdown\Exception\RestrictedActionException;
	use OCA\UserLockdown\Middleware\RestrictionMiddleware;
	use OCA\UserLockdown\Service\RestrictedUserService;
	use OCA\UserLockdown\Service\RestrictionContext;
	use OCP\AppFramework\Controller;
	use OCP\AppFramework\Http;
	use OCP\AppFramework\Http\DataResponse;
	use OCP\AppFramework\Http\JSONResponse;
	use OCP\AppFramework\Http\RedirectResponse;
	use OCP\AppFramework\Utility\ITimeFactory;
	use OCP\IAppConfig;
	use OCP\IGroupManager;
	use OCP\IL10N;
	use OCP\IRequest;
	use OCP\IURLGenerator;
	use OCP\IUser;
	use OCP\IUserManager;
	use OCP\IUserSession;
	use PHPUnit\Framework\Attributes\DataProvider;
	use PHPUnit\Framework\MockObject\MockObject;
	use PHPUnit\Framework\TestCase;

	final class RestrictionMiddlewareTest extends TestCase {
		/** @var IRequest&MockObject */
		private IRequest $request;

		/** @var IUserManager&MockObject */
		private IUserManager $userManager;

		/** @var RestrictedUserService&MockObject */
		private RestrictedUserService $restrictedUserService;

		public function testFilesReadControllerIsAllowed(): void {
			$middleware = $this->createMiddleware('GET', '/index.php/apps/files');

			$middleware->beforeController(new ViewController($this->request), 'index');

			$this->addToAssertionCount(1);
		}

		public function testMutatingAcceptRouteIsBlockedDespiteGetMethod(): void {
			$middleware = $this->createMiddleware(
				'GET',
				'/index.php/apps/files_sharing/accept/42',
			);

			try {
				$middleware->beforeController(new AcceptController($this->request), 'accept');
				self::fail('The mutating Accept route was not blocked.');
			} catch (RestrictedActionException $exception) {
				self::assertFalse($exception->isApiRequest());
			}
		}

		public function testTwoFactorChallengeSubmissionIsAllowedDuringLogin(): void {
			$middleware = $this->createMiddleware(
				'POST',
				'/index.php/login/challenge/totp',
			);

			$middleware->beforeController(
				new TwoFactorChallengeController($this->request),
				'solveChallenge',
			);

			$this->addToAssertionCount(1);
		}

		public function testTextPreviewSessionCreationIsAllowedAndForcedReadOnly(): void {
			$middleware = $this->createMiddleware(
				'PUT',
				'/index.php/apps/text/session/42/create',
			);
			$controller = new SessionController($this->request);

			$middleware->beforeController($controller, 'create');
			$response = new JSONResponse([
				'readOnly' => false,
				'content' => 'Preview content',
				'session' => [
					'token' => 'restricted-session-token',
					'userId' => 'alice',
				],
			]);

			$result = $middleware->afterController($controller, 'create', $response);

			self::assertInstanceOf(JSONResponse::class, $result);
			self::assertSame([
				'readOnly' => true,
				'content' => 'Preview content',
				'session' => [
					'token' => 'restricted-session-token',
					'userId' => 'alice',
				],
			], $result->getData());
		}

		public function testTextPreviewSaveRemainsBlocked(): void {
			$middleware = $this->createMiddleware(
				'POST',
				'/index.php/apps/text/session/42/save',
			);

			$this->expectException(RestrictedActionException::class);
			$middleware->beforeController(
				new SessionController($this->request),
				'save',
			);
		}

		public function testAnonymousTextSessionContentPushIsBlockedForResolvedRestrictedUser(): void {
			$middleware = $this->createMiddleware(
				'POST',
				'/index.php/apps/text/session/42/push',
				['steps' => [base64_encode("\0\2document-update")]],
				currentUserId: null,
			);

			$this->expectException(RestrictedActionException::class);
			$middleware->beforeController(
				new SessionController($this->request, 'alice'),
				'push',
			);
		}

		public function testAnonymousTextSessionAwarenessOnlyPushIsAllowed(): void {
			$middleware = $this->createMiddleware(
				'POST',
				'/index.php/apps/text/session/42/push',
				['steps' => []],
				currentUserId: null,
			);

			$middleware->beforeController(
				new SessionController($this->request, 'alice'),
				'push',
			);

			$this->addToAssertionCount(1);
		}

		public function testAnonymousTextSessionSyncQueryPushIsAllowed(): void {
			$middleware = $this->createMiddleware(
				'POST',
				'/index.php/apps/text/session/42/push',
				['steps' => [base64_encode("\0\0state-vector")]],
				currentUserId: null,
			);

			$middleware->beforeController(
				new SessionController($this->request, 'alice'),
				'push',
			);

			$this->addToAssertionCount(1);
		}

		public function testUnknownTextSessionPushFailsClosedEvenWithoutDocumentSteps(): void {
			$middleware = $this->createMiddleware(
				'POST',
				'/index.php/apps/text/session/42/push',
				['steps' => []],
				currentUserId: null,
			);

			$this->expectException(RestrictedActionException::class);
			$middleware->beforeController(
				new SessionController($this->request),
				'push',
			);
		}

		public function testAnonymousTextSessionSaveUsesRememberedRestrictionAsFallback(): void {
			$middleware = $this->createMiddleware(
				'POST',
				'/index.php/apps/text/session/42/save',
				['sessionToken' => 'restricted-session-token'],
				currentUserId: null,
				rememberedTextUserId: 'alice',
			);

			$this->expectException(RestrictedActionException::class);
			$middleware->beforeController(
				new SessionController($this->request),
				'save',
			);
		}

		public function testAnonymousTextSessionSyncIsAllowedAndForcedReadOnly(): void {
			$middleware = $this->createMiddleware(
				'POST',
				'/index.php/apps/text/session/42/sync',
				currentUserId: null,
			);
			$controller = new SessionController($this->request, 'alice');

			$middleware->beforeController($controller, 'sync');
			$response = new DataResponse(['readOnly' => false]);
			$result = $middleware->afterController($controller, 'sync', $response);

			self::assertInstanceOf(DataResponse::class, $result);
			self::assertSame(['readOnly' => true], $result->getData());
		}

		public function testAnonymousTextAttachmentUploadIsBlocked(): void {
			$middleware = $this->createMiddleware(
				'POST',
				'/index.php/apps/text/attachment/upload',
				currentUserId: null,
			);

			$this->expectException(RestrictedActionException::class);
			$middleware->beforeController(
				new AttachmentController($this->request, 'alice'),
				'uploadAttachment',
			);
		}

		/**
		 * @return iterable<string, array{string, string, string}>
		 */
		public static function shareMutationProvider(): iterable {
			yield 'create share' => [
				'POST',
				'/ocs/v2.php/apps/files_sharing/api/v1/shares',
				'createShare',
			];
			yield 'update share' => [
				'PUT',
				'/ocs/v2.php/apps/files_sharing/api/v1/shares/42',
				'updateShare',
			];
			yield 'delete share' => [
				'DELETE',
				'/ocs/v2.php/apps/files_sharing/api/v1/shares/42',
				'deleteShare',
			];
		}

		#[DataProvider('shareMutationProvider')]
		public function testBlockedShareMutationReturnsForbiddenJson(
			string $httpMethod,
			string $requestUri,
			string $controllerMethod,
		): void {
			$middleware = $this->createMiddleware(
				$httpMethod,
				$requestUri,
			);
			$controller = new ShareAPIController($this->request);

			$response = $this->handleRestriction(
				$middleware,
				$controller,
				$controllerMethod,
			);

			self::assertInstanceOf(JSONResponse::class, $response);
			self::assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
			self::assertSame([
				'error' => [
					'code' => 'user_restricted',
					'message' => 'This action has been disabled by your administrator.',
				],
			], $response->getData());
		}

		public function testBlockedBrowserRequestRedirectsToFiles(): void {
			$middleware = $this->createMiddleware(
				'GET',
				'/index.php/settings/user/security',
				[],
				['Accept' => 'text/html'],
			);
			$controller = new class('settings', $this->request) extends Controller {
			};

			$response = $this->handleRestriction($middleware, $controller, 'index');

			self::assertInstanceOf(RedirectResponse::class, $response);
			self::assertSame(
				'/index.php/apps/files?user_lockdown=blocked',
				$response->getRedirectURL(),
			);
		}

		public function testLostPasswordEmailHidesRestrictedAccountState(): void {
			$middleware = $this->createMiddleware(
				'POST',
				'/index.php/lostpassword/email',
				['user' => 'alice'],
			);
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn('alice');
			$this->userManager->method('get')->with('alice')->willReturn($user);
			$controller = new LostController($this->request);

			$response = $this->handleRestriction($middleware, $controller, 'email');

			self::assertInstanceOf(JSONResponse::class, $response);
			self::assertSame(Http::STATUS_OK, $response->getStatus());
			self::assertSame(['status' => 'success'], $response->getData());
			self::assertTrue($response->isThrottled());
		}

		public function testLostPasswordUpdateReturnsForbiddenJson(): void {
			$middleware = $this->createMiddleware(
				'POST',
				'/index.php/lostpassword/set/token/alice',
				['userId' => 'alice'],
			);
			$controller = new LostController($this->request);

			$response = $this->handleRestriction($middleware, $controller, 'setPassword');

			self::assertInstanceOf(JSONResponse::class, $response);
			self::assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
			self::assertSame([
				'error' => [
					'code' => 'user_restricted',
					'message' => 'This action has been disabled by your administrator.',
				],
			], $response->getData());
		}

		/**
		 * @return iterable<string, array{string, string}>
		 */
		public static function lostPasswordResetMethodProvider(): iterable {
			yield 'reset form' => ['GET', 'resetform'];
			yield 'password submission' => ['POST', 'setPassword'];
		}

		#[DataProvider('lostPasswordResetMethodProvider')]
		public function testLostPasswordResetCanonicalizesBackendUserAlias(
			string $httpMethod,
			string $controllerMethod,
		): void {
			$middleware = $this->createMiddleware(
				$httpMethod,
				'/index.php/lostpassword/reset/token/Alice.Login',
				['userId' => 'Alice.Login'],
			);
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn('alice');
			$this->userManager->method('get')
				->with('Alice.Login')
				->willReturn($user);

			$this->expectException(RestrictedActionException::class);
			$middleware->beforeController(
				new LostController($this->request),
				$controllerMethod,
			);
		}

		/**
		 * @param array<string, mixed> $params
		 * @param array<string, string> $headers
		 */
		private function createMiddleware(
			string $httpMethod,
			string $requestUri,
			array $params = [],
			array $headers = [],
			?string $currentUserId = 'alice',
			?string $rememberedTextUserId = null,
		): RestrictionMiddleware {
			$this->request = $this->createMock(IRequest::class);
			$this->request->method('getMethod')->willReturn($httpMethod);
			$this->request->method('getRequestUri')->willReturn($requestUri);
			$this->request->method('getParam')
				->willReturnCallback(static fn (string $key, mixed $default = null): mixed => $params[$key] ?? $default);
			$this->request->method('getHeader')
				->willReturnCallback(static fn (string $name): string => $headers[$name] ?? '');

			$urlGenerator = $this->createMock(IURLGenerator::class);
			$urlGenerator->method('linkToRoute')
				->with('files.view.index')
				->willReturn('/index.php/apps/files');

			$l10n = $this->createMock(IL10N::class);
			$l10n->method('t')->willReturnCallback(static fn (string $text): string => $text);

			$this->userManager = $this->createMock(IUserManager::class);
			$this->restrictedUserService = $this->createMock(RestrictedUserService::class);
			$this->restrictedUserService->method('isRestricted')->willReturn(true);

			$userSession = $this->createMock(IUserSession::class);
			$groupManager = $this->createMock(IGroupManager::class);
			if ($currentUserId === null) {
				$userSession->method('getUser')->willReturn(null);
			} else {
				$currentUser = $this->createMock(IUser::class);
				$currentUser->method('getUID')->willReturn($currentUserId);
				$userSession->method('getUser')->willReturn($currentUser);
				$groupManager->method('isAdmin')->with($currentUserId)->willReturn(false);
			}
			$restrictionContext = new RestrictionContext(
				$userSession,
				$groupManager,
				$this->restrictedUserService,
			);
			$appConfig = $this->createMock(IAppConfig::class);
			$rememberedTextMarker = $rememberedTextUserId === null
				? ''
				: json_encode([
					'userId' => $rememberedTextUserId,
					'seenAt' => 1_722_000_000,
				], JSON_THROW_ON_ERROR);
			$appConfig->method('getValueString')->willReturn($rememberedTextMarker);
			$timeFactory = $this->createMock(ITimeFactory::class);
			$timeFactory->method('getTime')->willReturn(1_722_000_000);
			$textSessionGuard = new TextSessionGuard($appConfig, $timeFactory);

			return new RestrictionMiddleware(
				$this->request,
				$urlGenerator,
				$l10n,
				$this->userManager,
				$this->restrictedUserService,
				$restrictionContext,
				$textSessionGuard,
			);
		}

		/** @return \OCP\AppFramework\Http\Response<Http::STATUS_*, array<string, mixed>> */
		private function handleRestriction(
			RestrictionMiddleware $middleware,
			Controller $controller,
			string $methodName,
		): \OCP\AppFramework\Http\Response {
			try {
				$middleware->beforeController($controller, $methodName);
				self::fail('The request was not blocked.');
			} catch (RestrictedActionException $exception) {
				return $middleware->afterException($controller, $methodName, $exception);
			}
		}
	}
}
