<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Controller;

use DomainException;
use InvalidArgumentException;
use OCA\UserLockdown\Policy\PermissionSet;
use OCA\UserLockdown\Service\RestrictedUserService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use Throwable;

class AdminApiController extends Controller {
	private const MAX_QUERY_LENGTH = 100;
	private const MAX_USER_ID_LENGTH = 64;
	private const SEARCH_LIMIT = 20;

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly RestrictedUserService $restrictedUserService,
		private readonly IUserSession $userSession,
		private readonly IURLGenerator $urlGenerator,
		private readonly IL10N $l10n,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/** @return JSONResponse<Http::STATUS_*, array<string, mixed>, array<string, mixed>> */
	public function index(): JSONResponse {
		try {
			$users = array_map(
				fn (array $user): array => $this->withAvatar($user),
				$this->restrictedUserService->getRestrictedUsers(),
			);

			return $this->success(['users' => $users]);
		} catch (Throwable $exception) {
			return $this->serverError(
				'list_restricted_users_failed',
				$this->l10n->t('Could not load restricted users.'),
				$exception,
			);
		}
	}

	/** @return JSONResponse<Http::STATUS_*, array<string, mixed>, array<string, mixed>> */
	public function search(mixed $query = null): JSONResponse {
		if (!is_string($query)) {
			return $this->validationError($this->l10n->t('The search query is invalid.'));
		}

		$query = trim($query);
		if ($query === '' || strlen($query) > self::MAX_QUERY_LENGTH) {
			return $this->validationError($this->l10n->t('The search query is invalid.'));
		}

		try {
			$users = array_map(
				fn (array $user): array => $this->withAvatar($user),
				$this->restrictedUserService->searchUsers($query, self::SEARCH_LIMIT),
			);

			return $this->success(['users' => $users]);
		} catch (Throwable $exception) {
			return $this->serverError(
				'user_search_failed',
				$this->l10n->t('Could not search users.'),
				$exception,
			);
		}
	}

	/** @return JSONResponse<Http::STATUS_*, array<string, mixed>, array<string, mixed>> */
	public function create(mixed $userId = null): JSONResponse {
		if (!$this->isValidUserId($userId)) {
			return $this->validationError($this->l10n->t('The selected user is invalid.'));
		}

		$admin = $this->userSession->getUser();
		if ($admin === null) {
			return $this->error(
				'authentication_required',
				$this->l10n->t('Authentication is required.'),
				Http::STATUS_UNAUTHORIZED,
			);
		}

		try {
			$this->restrictedUserService->addRestrictedUser($userId, $admin->getUID());
			$user = $this->restrictedUserService->getRestrictedUser($userId);
			if ($user === null) {
				throw new \RuntimeException('The newly restricted user could not be loaded.');
			}

			return $this->success(
				['user' => $this->withAvatar($user)],
				Http::STATUS_CREATED,
			);
		} catch (InvalidArgumentException $exception) {
			$message = $exception->getMessage() === RestrictedUserService::ERROR_ADMIN_USER
				? $this->l10n->t('Administrators cannot be restricted.')
				: $this->l10n->t('The selected user is invalid.');

			return $this->validationError($message);
		} catch (DomainException) {
			return $this->error(
				'already_restricted',
				$this->l10n->t('The selected user is already restricted.'),
				Http::STATUS_CONFLICT,
			);
		} catch (Throwable $exception) {
			return $this->serverError(
				'restrict_user_failed',
				$this->l10n->t('Could not restrict the selected user.'),
				$exception,
			);
		}
	}

	/** @return JSONResponse<Http::STATUS_*, array<string, mixed>, array<string, mixed>> */
	public function update(mixed $userId = null, mixed $permissions = null): JSONResponse {
		if (!$this->isValidUserId($userId) || !is_array($permissions)) {
			return $this->validationError($this->l10n->t('The selected permissions are invalid.'));
		}

		try {
			$permissionSet = PermissionSet::fromArray($permissions);
		} catch (InvalidArgumentException) {
			return $this->validationError($this->l10n->t('The selected permissions are invalid.'));
		}

		$admin = $this->userSession->getUser();
		if ($admin === null) {
			return $this->error(
				'authentication_required',
				$this->l10n->t('Authentication is required.'),
				Http::STATUS_UNAUTHORIZED,
			);
		}

		try {
			$this->restrictedUserService->updatePermissions(
				$userId,
				$permissionSet,
				$admin->getUID(),
			);
			$user = $this->restrictedUserService->getRestrictedUser($userId);
			if ($user === null) {
				throw new \RuntimeException('The updated restricted user could not be loaded.');
			}

			return $this->success(['user' => $this->withAvatar($user)]);
		} catch (InvalidArgumentException $exception) {
			$message = $exception->getMessage() === RestrictedUserService::ERROR_ADMIN_USER
				? $this->l10n->t('Administrators cannot be restricted.')
				: $this->l10n->t('The selected user is invalid.');

			return $this->validationError($message);
		} catch (DomainException $exception) {
			if ($exception->getMessage() === RestrictedUserService::ERROR_NOT_RESTRICTED) {
				return $this->error(
					'not_restricted',
					$this->l10n->t('The selected user is not restricted.'),
					Http::STATUS_NOT_FOUND,
				);
			}

			return $this->serverError(
				'update_permissions_failed',
				$this->l10n->t('Could not update the selected user.'),
				$exception,
			);
		} catch (Throwable $exception) {
			return $this->serverError(
				'update_permissions_failed',
				$this->l10n->t('Could not update the selected user.'),
				$exception,
			);
		}
	}

	/** @return JSONResponse<Http::STATUS_*, array<string, mixed>, array<string, mixed>> */
	public function destroy(mixed $userId = null): JSONResponse {
		if (!$this->isValidUserId($userId)) {
			return $this->validationError($this->l10n->t('The selected user is invalid.'));
		}

		try {
			$this->restrictedUserService->removeRestrictedUser($userId);

			return $this->success(['userId' => $userId]);
		} catch (Throwable $exception) {
			return $this->serverError(
				'unrestrict_user_failed',
				$this->l10n->t('Could not remove the selected user.'),
				$exception,
			);
		}
	}

	/** @phpstan-assert-if-true non-empty-string $userId */
	private function isValidUserId(mixed $userId): bool {
		return is_string($userId)
			&& $userId !== ''
			&& strlen($userId) <= self::MAX_USER_ID_LENGTH;
	}

	/**
	 * @param array<string, mixed> $user
	 * @return array<string, mixed>
	 */
	private function withAvatar(array $user): array {
		return [
			...$user,
			'avatarUrl' => $this->urlGenerator->linkToRoute('core.avatar.getAvatar', [
				'userId' => $user['id'],
				'size' => 64,
			]),
		];
	}

	/**
	 * @template S of Http::STATUS_OK|Http::STATUS_CREATED
	 * @param array<string, mixed> $data
	 * @param S $status
	 * @return JSONResponse<S, array<string, mixed>, array<string, mixed>>
	 */
	private function success(array $data, int $status = Http::STATUS_OK): JSONResponse {
		return new JSONResponse(['data' => $data], $status);
	}

	/** @return JSONResponse<Http::STATUS_UNPROCESSABLE_ENTITY, array<string, mixed>, array<string, mixed>> */
	private function validationError(string $message): JSONResponse {
		return $this->error('validation_failed', $message, Http::STATUS_UNPROCESSABLE_ENTITY);
	}

	/**
	 * @template S of Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND|Http::STATUS_UNPROCESSABLE_ENTITY|Http::STATUS_CONFLICT|Http::STATUS_INTERNAL_SERVER_ERROR
	 * @param S $status
	 * @return JSONResponse<S, array<string, mixed>, array<string, mixed>>
	 */
	private function error(string $code, string $message, int $status): JSONResponse {
		return new JSONResponse([
			'error' => [
				'code' => $code,
				'message' => $message,
			],
		], $status);
	}

	/** @return JSONResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array<string, mixed>, array<string, mixed>> */
	private function serverError(string $code, string $message, Throwable $exception): JSONResponse {
		$this->logger->error($message, [
			'app' => $this->appName,
			'exception' => $exception,
		]);

		return $this->error($code, $message, Http::STATUS_INTERNAL_SERVER_ERROR);
	}
}
