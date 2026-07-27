<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files\Controller {
	use OCP\AppFramework\Controller;
	use OCP\IRequest;

	final class ApiController extends Controller {
		public function __construct(IRequest $request) {
			parent::__construct('files', $request);
		}
	}

	final class ViewController extends Controller {
		public function __construct(IRequest $request) {
			parent::__construct('files', $request);
		}
	}
}

namespace OCA\Settings\Controller {
	use OCP\AppFramework\Controller;
	use OCP\IRequest;

	final class ChangePasswordController extends Controller {
		public function __construct(IRequest $request) {
			parent::__construct('settings', $request);
		}
	}

	final class PersonalSettingsController extends Controller {
		public function __construct(IRequest $request) {
			parent::__construct('settings', $request);
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

namespace OCA\FirstRunWizard\Controller {
	use OCP\AppFramework\Controller;
	use OCP\IRequest;

	final class WizardController extends Controller {
		public function __construct(IRequest $request) {
			parent::__construct('firstrunwizard', $request);
		}
	}
}

namespace OCA\Notifications\Controller {
	use OCP\AppFramework\Controller;
	use OCP\IRequest;

	final class EndpointController extends Controller {
		public function __construct(IRequest $request) {
			parent::__construct('notifications', $request);
		}
	}
}

namespace OCA\Recommendations\Controller {
	use OCP\AppFramework\Controller;
	use OCP\IRequest;

	final class RecommendationController extends Controller {
		public function __construct(IRequest $request) {
			parent::__construct('recommendations', $request);
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

namespace OCA\UserStatus\Controller {
	use OCP\AppFramework\Controller;
	use OCP\IRequest;

	final class UserStatusController extends Controller {
		public function __construct(IRequest $request) {
			parent::__construct('user_status', $request);
		}
	}

	final class HeartbeatController extends Controller {
		public function __construct(IRequest $request) {
			parent::__construct('user_status', $request);
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

	final class ContactsMenuController extends Controller {
		public function __construct(IRequest $request) {
			parent::__construct('core', $request);
		}
	}

	final class CSRFTokenController extends Controller {
		public function __construct(IRequest $request) {
			parent::__construct('core', $request);
		}
	}
}

namespace OCA\UserLockdown\Tests\Unit\Middleware {
	use OC\Core\Controller\ContactsMenuController;
	use OC\Core\Controller\CSRFTokenController;
	use OC\Core\Controller\LostController;
	use OC\Core\Controller\TwoFactorChallengeController;
	use OCA\Files\Controller\ApiController;
	use OCA\Files\Controller\ViewController;
	use OCA\Files_Sharing\Controller\AcceptController;
	use OCA\Files_Sharing\Controller\ShareAPIController;
	use OCA\FirstRunWizard\Controller\WizardController;
	use OCA\Notifications\Controller\EndpointController;
	use OCA\Recommendations\Controller\RecommendationController;
	use OCA\Settings\Controller\ChangePasswordController;
	use OCA\Settings\Controller\PersonalSettingsController;
	use OCA\Text\Controller\AttachmentController;
	use OCA\Text\Controller\SessionController;
	use OCA\UserLockdown\Compatibility\TextSessionGuard;
	use OCA\UserLockdown\Exception\RestrictedActionException;
	use OCA\UserLockdown\Middleware\RestrictionMiddleware;
	use OCA\UserLockdown\Policy\PermissionSet;
	use OCA\UserLockdown\Service\RestrictedUserService;
	use OCA\UserLockdown\Service\RestrictionContext;
	use OCA\UserStatus\Controller\HeartbeatController as UserStatusHeartbeatController;
	use OCA\UserStatus\Controller\UserStatusController;
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

		public function testFilesShellRemainsAvailableWithoutViewPermission(): void {
			$middleware = $this->createMiddleware(
				'GET',
				'/index.php/apps/files',
				permissionSet: PermissionSet::blocked(),
			);

			$middleware->beforeController(new ViewController($this->request), 'index');

			$this->addToAssertionCount(1);
		}

		public function testHiddenNavigationRedirectsFilesRootToAllFiles(): void {
			$middleware = $this->createMiddleware(
				'GET',
				'/index.php/apps/files',
				[],
				['Accept' => 'text/html'],
				permissionSet: $this->permissions(hideSideNavigation: true),
			);

			$response = $this->handleRestriction(
				$middleware,
				new ViewController($this->request),
				'index',
			);

			self::assertInstanceOf(RedirectResponse::class, $response);
			self::assertSame('/index.php/apps/files/files', $response->getRedirectURL());
		}

		/** @return iterable<string, array{string, string}> */
		public static function alternativeFilesViewProvider(): iterable {
			yield 'recent files' => ['indexView', 'recent'];
			yield 'favorites' => ['indexView', 'favorites'];
			yield 'shares' => ['indexView', 'shares'];
			yield 'trash file' => ['indexViewFileid', 'trashbin'];
		}

		#[DataProvider('alternativeFilesViewProvider')]
		public function testHiddenNavigationRedirectsAlternativeFilesViews(
			string $controllerMethod,
			string $view,
		): void {
			$middleware = $this->createMiddleware(
				'GET',
				'/index.php/apps/files/' . $view,
				['view' => $view],
				['Accept' => 'text/html'],
				permissionSet: $this->permissions(hideSideNavigation: true),
			);

			$response = $this->handleRestriction(
				$middleware,
				new ViewController($this->request),
				$controllerMethod,
			);

			self::assertInstanceOf(RedirectResponse::class, $response);
			self::assertSame('/index.php/apps/files/files', $response->getRedirectURL());
		}

		public function testHiddenNavigationAllowsCanonicalAllFilesView(): void {
			$middleware = $this->createMiddleware(
				'GET',
				'/index.php/apps/files/files',
				['view' => 'files'],
				permissionSet: $this->permissions(hideSideNavigation: true),
			);

			$middleware->beforeController(
				new ViewController($this->request),
				'indexView',
			);

			$this->addToAssertionCount(1);
		}

		public function testFilesShellRedirectsPasswordOnlyUserToSecuritySettings(): void {
			$middleware = $this->createMiddleware(
				'GET',
				'/index.php/apps/files',
				[],
				['Accept' => 'text/html'],
				permissionSet: $this->permissions(view: false, password: true),
			);

			$response = $this->handleRestriction(
				$middleware,
				new ViewController($this->request),
				'index',
			);

			self::assertInstanceOf(RedirectResponse::class, $response);
			self::assertSame('/index.php/settings/user/security', $response->getRedirectURL());
		}

		public function testFileReadControllerIsBlockedWithoutViewPermission(): void {
			$middleware = $this->createMiddleware(
				'GET',
				'/index.php/apps/files/api/v1/thumbnail',
				permissionSet: PermissionSet::blocked(),
			);

			$this->expectException(RestrictedActionException::class);
			$middleware->beforeController(new ApiController($this->request), 'getThumbnail');
		}

		public function testFullAccessPolicyBypassesControllerRestrictions(): void {
			$middleware = $this->createMiddleware(
				'DELETE',
				'/ocs/v2.php/apps/files_sharing/api/v1/shares/42',
				permissionSet: PermissionSet::fullAccess(),
			);

			$middleware->beforeController(
				new ShareAPIController($this->request),
				'deleteShare',
			);

			$this->addToAssertionCount(1);
		}

		/**
		 * @return iterable<string, array{class-string<Controller>, string, string, string}>
		 */
		public static function backgroundReadRequestProvider(): iterable {
			yield 'recommendations' => [
				RecommendationController::class,
				'GET',
				'/ocs/v2.php/apps/recommendations/api/v1/recommendations',
				'index',
			];
			yield 'user status' => [
				UserStatusController::class,
				'GET',
				'/ocs/v2.php/apps/user_status/api/v1/user_status',
				'getStatus',
			];
			yield 'notifications' => [
				EndpointController::class,
				'GET',
				'/ocs/v2.php/apps/notifications/api/v2/notifications',
				'listNotifications',
			];
			yield 'contact teams' => [
				ContactsMenuController::class,
				'GET',
				'/index.php/contactsmenu/teams',
				'getTeams',
			];
			yield 'CSRF token' => [
				CSRFTokenController::class,
				'GET',
				'/index.php/csrftoken',
				'index',
			];
			yield 'user status heartbeat' => [
				UserStatusHeartbeatController::class,
				'PUT',
				'/ocs/v2.php/apps/user_status/api/v1/heartbeat',
				'heartbeat',
			];
			yield 'dismiss first-run wizard' => [
				WizardController::class,
				'DELETE',
				'/index.php/apps/firstrunwizard/wizard',
				'disable',
			];
		}

		/** @param class-string<Controller> $controllerClass */
		#[DataProvider('backgroundReadRequestProvider')]
		public function testBackgroundReadRequestIsAllowed(
			string $controllerClass,
			string $httpMethod,
			string $requestUri,
			string $controllerMethod,
		): void {
			$middleware = $this->createMiddleware($httpMethod, $requestUri);

			$middleware->beforeController(new $controllerClass($this->request), $controllerMethod);

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

		public function testTextSessionIsBlockedWithoutViewPermission(): void {
			$middleware = $this->createMiddleware(
				'PUT',
				'/index.php/apps/text/session/42/create',
				permissionSet: PermissionSet::blocked(),
			);

			$this->expectException(RestrictedActionException::class);
			$middleware->beforeController(
				new SessionController($this->request, 'alice'),
				'create',
			);
		}

		public function testWritePermissionAllowsTextSaveAndDoesNotForceReadOnly(): void {
			$permissionSet = $this->permissions(write: true);
			$middleware = $this->createMiddleware(
				'POST',
				'/index.php/apps/text/session/42/save',
				permissionSet: $permissionSet,
			);
			$controller = new SessionController($this->request, 'alice');

			$middleware->beforeController($controller, 'save');

			$createMiddleware = $this->createMiddleware(
				'PUT',
				'/index.php/apps/text/session/42/create',
				permissionSet: $permissionSet,
			);
			$createController = new SessionController($this->request, 'alice');
			$createMiddleware->beforeController($createController, 'create');
			$response = new JSONResponse([
				'readOnly' => false,
				'session' => [
					'token' => 'writer-session-token',
					'userId' => 'alice',
				],
			]);

			$result = $createMiddleware->afterController($createController, 'create', $response);

			self::assertInstanceOf(JSONResponse::class, $result);
			$resultData = $result->getData();
			self::assertIsArray($resultData);
			self::assertSame(false, $resultData['readOnly'] ?? null);
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

		public function testRestrictedTextContentPushIsAcknowledgedWithoutApplyingIt(): void {
			$middleware = $this->createMiddleware(
				'POST',
				'/index.php/apps/text/session/42/push',
				[
					'steps' => [base64_encode("\0\2document-update")],
					'version' => 17,
				],
				currentUserId: null,
			);
			$controller = new SessionController($this->request, 'alice');

			$response = $this->handleRestriction($middleware, $controller, 'push');

			self::assertInstanceOf(JSONResponse::class, $response);
			self::assertSame(Http::STATUS_OK, $response->getStatus());
			self::assertSame([
				'steps' => [],
				'version' => 17,
			], $response->getData());
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

		/** @return iterable<string, array{string}> */
		public static function emptyTextSyncStepProvider(): iterable {
			yield 'sync step two' => ["\0\1\2\0\0"];
			yield 'update' => ["\0\2\2\0\0"];
		}

		#[DataProvider('emptyTextSyncStepProvider')]
		public function testAnonymousTextSessionEmptySyncPushIsAllowed(string $step): void {
			$middleware = $this->createMiddleware(
				'POST',
				'/index.php/apps/text/session/42/push',
				['steps' => [base64_encode($step)]],
				currentUserId: null,
			);

			$middleware->beforeController(
				new SessionController($this->request, 'alice'),
				'push',
			);

			$this->addToAssertionCount(1);
		}

		public function testAnonymousTextSessionNonEmptySyncStepTwoIsBlocked(): void {
			$middleware = $this->createMiddleware(
				'POST',
				'/index.php/apps/text/session/42/push',
				['steps' => [base64_encode("\0\1\3abc")]],
				currentUserId: null,
			);

			$this->expectException(RestrictedActionException::class);
			$middleware->beforeController(
				new SessionController($this->request, 'alice'),
				'push',
			);
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

		#[DataProvider('shareMutationProvider')]
		public function testSharePermissionAllowsShareMutations(
			string $httpMethod,
			string $requestUri,
			string $controllerMethod,
		): void {
			$middleware = $this->createMiddleware(
				$httpMethod,
				$requestUri,
				permissionSet: $this->permissions(share: true),
			);

			$middleware->beforeController(
				new ShareAPIController($this->request),
				$controllerMethod,
			);

			$this->addToAssertionCount(1);
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
				'/index.php/apps/files',
				$response->getRedirectURL(),
			);

			$safeShellMiddleware = $this->createMiddleware(
				'GET',
				$response->getRedirectURL(),
				permissionSet: PermissionSet::blocked(),
			);
			$safeShellMiddleware->beforeController(
				new ViewController($this->request),
				'index',
			);
			$this->addToAssertionCount(1);
		}

		public function testPasswordOnlyBrowserRequestRedirectsToSecuritySettings(): void {
			$middleware = $this->createMiddleware(
				'GET',
				'/index.php/apps/dashboard',
				[],
				['Accept' => 'text/html'],
				permissionSet: $this->permissions(view: false, password: true),
			);
			$controller = new class('dashboard', $this->request) extends Controller {
			};

			$response = $this->handleRestriction($middleware, $controller, 'index');

			self::assertInstanceOf(RedirectResponse::class, $response);
			self::assertSame('/index.php/settings/user/security', $response->getRedirectURL());
		}

		public function testPasswordPermissionAllowsSecurityPageAndPasswordChange(): void {
			$permissionSet = $this->permissions(view: false, password: true);
			$settingsMiddleware = $this->createMiddleware(
				'GET',
				'/index.php/settings/user/security',
				['section' => 'security'],
				permissionSet: $permissionSet,
			);
			$settingsMiddleware->beforeController(
				new PersonalSettingsController($this->request),
				'index',
			);

			$passwordMiddleware = $this->createMiddleware(
				'POST',
				'/index.php/settings/personal/changepassword',
				permissionSet: $permissionSet,
			);
			$passwordMiddleware->beforeController(
				new ChangePasswordController($this->request),
				'changePersonalPassword',
			);

			$this->addToAssertionCount(2);
		}

		public function testPasswordPermissionDoesNotAllowOtherSettingsSections(): void {
			$middleware = $this->createMiddleware(
				'GET',
				'/index.php/settings/user/profile',
				['section' => 'profile'],
				permissionSet: $this->permissions(view: false, password: true),
			);

			$this->expectException(RestrictedActionException::class);
			$middleware->beforeController(
				new PersonalSettingsController($this->request),
				'index',
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

		public function testChangePasswordPermissionAllowsLostPasswordFlow(): void {
			$permissionSet = $this->permissions(view: false, password: true);
			$middleware = $this->createMiddleware(
				'POST',
				'/index.php/lostpassword/set/token/alice',
				['userId' => 'alice'],
				permissionSet: $permissionSet,
			);

			$middleware->beforeController(
				new LostController($this->request),
				'setPassword',
			);

			$this->addToAssertionCount(1);
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

		private function permissions(
			bool $view = true,
			bool $write = false,
			bool $delete = false,
			bool $share = false,
			bool $password = false,
			bool $hideSideNavigation = false,
		): PermissionSet {
			return PermissionSet::fromArray([
				'viewFiles' => $view,
				'writeFiles' => $write,
				'deleteFiles' => $delete,
				'shareFiles' => $share,
				'changePassword' => $password,
				'hideSideNavigation' => $hideSideNavigation,
				'fullAccess' => false,
			]);
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
			?PermissionSet $permissionSet = null,
		): RestrictionMiddleware {
			$permissionSet ??= PermissionSet::readOnly();
			$this->request = $this->createMock(IRequest::class);
			$this->request->method('getMethod')->willReturn($httpMethod);
			$this->request->method('getRequestUri')->willReturn($requestUri);
			$this->request->method('getParam')
				->willReturnCallback(static fn (string $key, mixed $default = null): mixed => $params[$key] ?? $default);
			$this->request->method('getHeader')
				->willReturnCallback(static fn (string $name): string => $headers[$name] ?? '');

			$urlGenerator = $this->createMock(IURLGenerator::class);
			$urlGenerator->method('linkToRoute')
				->willReturnCallback(static fn (string $route, array $parameters = []): string => match ($route) {
					'files.view.indexView' => $parameters === ['view' => 'files']
						? '/index.php/apps/files/files'
						: throw new \RuntimeException('Unexpected files route parameters.'),
					'files.view.index' => '/index.php/apps/files',
					'settings.PersonalSettings.index' => '/index.php/settings/user/security',
					default => throw new \RuntimeException('Unexpected route: ' . $route),
				});

			$l10n = $this->createMock(IL10N::class);
			$l10n->method('t')->willReturnCallback(static fn (string $text): string => $text);

			$this->userManager = $this->createMock(IUserManager::class);
			$this->restrictedUserService = $this->createMock(RestrictedUserService::class);
			$this->restrictedUserService->method('getPermissionSet')->willReturn($permissionSet);

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
