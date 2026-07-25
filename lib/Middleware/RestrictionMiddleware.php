<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Middleware;

use Exception;
use OCA\UserLockdown\Compatibility\TextSessionGuard;
use OCA\UserLockdown\Exception\RestrictedActionException;
use OCA\UserLockdown\Service\RestrictedUserService;
use OCA\UserLockdown\Service\RestrictionContext;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;

final class RestrictionMiddleware extends Middleware {
	private const LOST_PASSWORD_CONTROLLER = 'OC\\Core\\Controller\\LostController';
	private const LOGOUT_CONTROLLER = 'OC\\Core\\Controller\\LoginController';
	private const TEXT_ATTACHMENT_CONTROLLER = 'OCA\\Text\\Controller\\AttachmentController';
	private const TEXT_SESSION_CONTROLLER = 'OCA\\Text\\Controller\\SessionController';

	/** @var list<string> */
	private const READ_ONLY_CONTROLLER_PREFIXES = [
		'OCA\\Viewer\\Controller\\',
		'OCA\\Theming\\Controller\\',
	];

	/** @var array<string, list<string>> */
	private const AUTHENTICATION_CONTROLLER_METHODS = [
		'OC\\Core\\Controller\\TwoFactorChallengeController' => [
			'selectChallenge',
			'showChallenge',
			'solveChallenge',
		],
	];

	/** @var array<string, list<string>> */
	private const READ_ONLY_CONTROLLER_METHODS = [
		'OCA\\Files\\Controller\\ApiController' => [
			'getConfigs',
			'getGridView',
			'getRecentFiles',
			'getStorageStats',
			'getThumbnail',
			'getViewConfigs',
			'serviceWorker',
		],
		'OCA\\Files\\Controller\\DirectEditingController' => [
			'info',
			'templates',
		],
		'OCA\\Files\\Controller\\TemplateController' => [
			'list',
			'listTemplateFields',
		],
		'OCA\\Files\\Controller\\ViewController' => [
			'index',
			'indexView',
			'indexViewFileid',
			'showFile',
		],
		'OCA\\Files_Sharing\\Controller\\DeletedShareAPIController' => [
			'index',
		],
		'OCA\\Files_Sharing\\Controller\\RemoteController' => [
			'getOpenShares',
			'getShare',
			'getShares',
		],
		'OCA\\Files_Sharing\\Controller\\ShareAPIController' => [
			'getInheritedShares',
			'getShare',
			'getShares',
			'pendingShares',
		],
		'OCA\\Text\\Controller\\AttachmentController' => [
			'getImageFile',
			'getMediaFile',
			'getMediaFilePreview',
		],
		'OCA\\Text\\Controller\\WorkspaceController' => [
			'folder',
		],
	];

	/**
	 * These endpoints use mutating HTTP verbs for read-session lifecycle calls.
	 *
	 * @var array<string, list<string>>
	 */
	private const STATEFUL_READ_ONLY_CONTROLLER_METHODS = [
		self::TEXT_ATTACHMENT_CONTROLLER => [
			'getAttachmentList',
		],
		self::TEXT_SESSION_CONTROLLER => [
			'create',
			'sync',
			'close',
		],
	];

	/** @var array<string, list<string>> */
	private const TEXT_TOKEN_MUTATION_CONTROLLER_METHODS = [
		self::TEXT_ATTACHMENT_CONTROLLER => [
			'insertAttachmentFile',
			'uploadAttachment',
			'createAttachment',
		],
		self::TEXT_SESSION_CONTROLLER => [
			'push',
			'save',
			'mention',
		],
	];

	/** @var list<string> */
	private const READ_ONLY_CORE_CONTROLLERS = [
		'OC\\Core\\Controller\\AvatarController',
		'OC\\Core\\Controller\\CssController',
		'OC\\Core\\Controller\\HeartbeatController',
		'OC\\Core\\Controller\\OCSController',
		'OC\\Core\\Controller\\PreviewController',
		'OC\\Core\\Controller\\SvgController',
	];

	/** @var list<string> */
	private const SAFE_HTTP_METHODS = [
		'GET',
		'HEAD',
		'OPTIONS',
	];

	public function __construct(
		private readonly IRequest $request,
		private readonly IURLGenerator $urlGenerator,
		private readonly IL10N $l10n,
		private readonly IUserManager $userManager,
		private readonly RestrictedUserService $restrictedUserService,
		private readonly RestrictionContext $restrictionContext,
		private readonly TextSessionGuard $textSessionGuard,
	) {
	}

	public function beforeController(Controller $controller, string $methodName): void {
		$controllerClass = $controller::class;

		if (
			$controllerClass === self::LOST_PASSWORD_CONTROLLER
			&& $this->isRestrictedLostPasswordTarget($methodName)
		) {
			if ($methodName === 'email') {
				throw new RestrictedActionException(
					true,
					$this->l10n->t('This action has been disabled by your administrator.'),
					true,
				);
			}

			throw $this->restrictedAction($methodName !== 'resetform');
		}

		if (
			$controllerClass === self::TEXT_SESSION_CONTROLLER
			|| $controllerClass === self::TEXT_ATTACHMENT_CONTROLLER
		) {
			$textSessionUserId = $this->getTextSessionUserId($controller);
			if (
				$textSessionUserId === null
				&& $this->isTextTokenMutation($controllerClass, $methodName)
			) {
				throw $this->restrictedAction(true);
			}

			if (
				$textSessionUserId !== null
				&& $this->restrictedUserService->isRestricted($textSessionUserId)
			) {
				if (
					$this->isAllowedStatefulReadOnlyController($controllerClass, $methodName)
					|| $this->isTextReadOnlyPush($controllerClass, $methodName)
					|| (
						$this->isAllowedReadOnlyController($controllerClass, $methodName)
						&& in_array(strtoupper($this->request->getMethod()), self::SAFE_HTTP_METHODS, true)
					)
				) {
					return;
				}

				throw $this->restrictedAction(true);
			}
		}

		if (!$this->restrictionContext->isCurrentUserRestricted()) {
			return;
		}

		if (
			$controllerClass === self::LOGOUT_CONTROLLER
			&& $methodName === 'logout'
		) {
			return;
		}

		if ($this->isAllowedAuthenticationController($controllerClass, $methodName)) {
			return;
		}

		if ($this->isAllowedReadOnlyController($controllerClass, $methodName)) {
			if (in_array(strtoupper($this->request->getMethod()), self::SAFE_HTTP_METHODS, true)) {
				return;
			}
		}

		if ($this->isAllowedStatefulReadOnlyController($controllerClass, $methodName)) {
			return;
		}

		throw $this->restrictedAction($this->isApiRequest());
	}

	/**
	 * @param Response<Http::STATUS_*, array<string, mixed>> $response
	 * @return Response<Http::STATUS_*, array<string, mixed>>
	 */
	public function afterController(
		Controller $controller,
		string $methodName,
		Response $response,
	): Response {
		if ($controller::class !== self::TEXT_SESSION_CONTROLLER) {
			return $response;
		}

		if ($methodName === 'close' && $response->getStatus() === Http::STATUS_OK) {
			$this->textSessionGuard->forget($this->getTextSessionToken());
			return $response;
		}

		if (
			!in_array($methodName, ['create', 'sync'], true)
			|| (
				!$response instanceof DataResponse
				&& !$response instanceof JSONResponse
			)
		) {
			return $response;
		}

		$data = $response->getData();
		if (!is_array($data)) {
			return $response;
		}

		if ($methodName === 'create') {
			$session = $data['session'] ?? null;
			$sessionToken = is_array($session) ? ($session['token'] ?? null) : null;
			$sessionUserId = is_array($session) ? ($session['userId'] ?? null) : null;
			if (
				is_string($sessionToken)
				&& is_string($sessionUserId)
			) {
				$this->textSessionGuard->remember($sessionToken, $sessionUserId);
				$sessionIsRestricted = $this->restrictedUserService->isRestricted($sessionUserId);
				if ($sessionIsRestricted) {
					$data['readOnly'] = true;
					$response->setData($data);
				}
			}

			return $response;
		}

		$textSessionUserId = $this->getTextSessionUserId($controller);
		if (
			$textSessionUserId === null
			|| !array_key_exists('readOnly', $data)
		) {
			return $response;
		}

		$this->textSessionGuard->remember(
			$this->getTextSessionToken(),
			$textSessionUserId,
		);
		if ($this->restrictedUserService->isRestricted($textSessionUserId)) {
			$data['readOnly'] = true;
			$response->setData($data);
		}
		return $response;
	}

	/** @return Response<Http::STATUS_*, array<string, mixed>> */
	public function afterException(
		Controller $controller,
		string $methodName,
		Exception $exception,
	): Response {
		if (!$exception instanceof RestrictedActionException) {
			throw $exception;
		}

		if ($exception->shouldHideAccountState()) {
			$response = new JSONResponse(['status' => 'success']);
			$response->addHeader('Cache-Control', 'no-store');
			$response->throttle();
			return $response;
		}

		if ($exception->isApiRequest()) {
			$response = new JSONResponse([
				'error' => [
					'code' => 'user_restricted',
					'message' => $exception->getMessage(),
				],
			], Http::STATUS_FORBIDDEN);
			$response->addHeader('Cache-Control', 'no-store');
			return $response;
		}

		$filesUrl = $this->urlGenerator->linkToRoute('files.view.index');
		$separator = str_contains($filesUrl, '?') ? '&' : '?';
		return new RedirectResponse($filesUrl . $separator . 'user_lockdown=blocked');
	}

	private function restrictedAction(bool $apiRequest): RestrictedActionException {
		return new RestrictedActionException(
			$apiRequest,
			$this->l10n->t('This action has been disabled by your administrator.'),
		);
	}

	private function isAllowedReadOnlyController(
		string $controllerClass,
		string $methodName,
	): bool {
		$allowedMethods = self::READ_ONLY_CONTROLLER_METHODS[$controllerClass] ?? null;
		if ($allowedMethods !== null) {
			return in_array($methodName, $allowedMethods, true);
		}

		if (in_array($controllerClass, self::READ_ONLY_CORE_CONTROLLERS, true)) {
			return true;
		}

		foreach (self::READ_ONLY_CONTROLLER_PREFIXES as $prefix) {
			if (str_starts_with($controllerClass, $prefix)) {
				return true;
			}
		}

		return false;
	}

	private function isAllowedAuthenticationController(
		string $controllerClass,
		string $methodName,
	): bool {
		$allowedMethods = self::AUTHENTICATION_CONTROLLER_METHODS[$controllerClass] ?? [];
		return in_array($methodName, $allowedMethods, true);
	}

	private function isAllowedStatefulReadOnlyController(
		string $controllerClass,
		string $methodName,
	): bool {
		$allowedMethods = self::STATEFUL_READ_ONLY_CONTROLLER_METHODS[$controllerClass] ?? [];
		return in_array($methodName, $allowedMethods, true);
	}

	private function getTextSessionUserId(Controller $controller): ?string {
		$userId = $this->textSessionGuard->getRememberedUserId($this->getTextSessionToken());
		if ($userId !== null) {
			return $userId;
		}

		return $this->textSessionGuard->getControllerUserId($controller);
	}

	private function getTextSessionToken(): string {
		$sessionToken = $this->request->getParam('sessionToken');
		return is_string($sessionToken) ? $sessionToken : '';
	}

	private function isTextTokenMutation(string $controllerClass, string $methodName): bool {
		$methods = self::TEXT_TOKEN_MUTATION_CONTROLLER_METHODS[$controllerClass] ?? [];
		return in_array($methodName, $methods, true);
	}

	private function isTextReadOnlyPush(string $controllerClass, string $methodName): bool {
		if (
			$controllerClass !== self::TEXT_SESSION_CONTROLLER
			|| $methodName !== 'push'
		) {
			return false;
		}

		$steps = $this->request->getParam('steps');
		if (!is_array($steps)) {
			return false;
		}

		foreach ($steps as $step) {
			if (!is_string($step) || !$this->isYjsSyncStepOne($step)) {
				return false;
			}
		}

		return true;
	}

	private function isYjsSyncStepOne(string $encodedStep): bool {
		$decodedStep = base64_decode($encodedStep, true);
		return is_string($decodedStep)
			&& strlen($decodedStep) >= 2
			&& ord($decodedStep[0]) === 0
			&& ord($decodedStep[1]) === 0;
	}

	private function isApiRequest(): bool {
		$method = strtoupper($this->request->getMethod());
		if (!in_array($method, self::SAFE_HTTP_METHODS, true)) {
			return true;
		}

		$requestUri = $this->request->getRequestUri();
		if (
			str_contains($requestUri, '/ocs/')
			|| str_contains($requestUri, '/api/')
			|| str_contains($requestUri, '/remote.php/')
		) {
			return true;
		}

		$accept = strtolower($this->request->getHeader('Accept'));
		$requestedWith = strtolower($this->request->getHeader('X-Requested-With'));
		return str_contains($accept, 'json') || $requestedWith === 'xmlhttprequest';
	}

	private function isRestrictedLostPasswordTarget(string $methodName): bool {
		if ($methodName === 'email') {
			$input = $this->request->getParam('user');
			if (!is_string($input)) {
				return false;
			}

			$input = trim($input);
			if ($input === '') {
				return false;
			}

			$user = $this->userManager->get($input);
			if ($user instanceof IUser) {
				return $this->restrictedUserService->isRestricted($user->getUID());
			}

			$enabledMatches = array_values(array_filter(
				$this->userManager->getByEmail($input),
				static fn (IUser $candidate): bool => $candidate->isEnabled(),
			));

			return count($enabledMatches) === 1
				&& $this->restrictedUserService->isRestricted($enabledMatches[0]->getUID());
		}

		if ($methodName !== 'resetform' && $methodName !== 'setPassword') {
			return false;
		}

		$userId = $this->request->getParam('userId');
		if (!is_string($userId) || $userId === '') {
			return false;
		}

		$user = $this->userManager->get($userId);
		$canonicalUserId = $user instanceof IUser ? $user->getUID() : $userId;
		return $this->restrictedUserService->isRestricted($canonicalUserId);
	}
}
