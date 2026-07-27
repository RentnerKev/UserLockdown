<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Controller;

use DomainException;
use InvalidArgumentException;
use OCA\UserLockdown\Policy\PermissionPreset;
use OCA\UserLockdown\Policy\PermissionSet;
use OCA\UserLockdown\Service\PermissionSettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;
use Throwable;

final class PermissionSettingsApiController extends Controller {
	private const MAX_PRESET_ID_LENGTH = 64;

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly PermissionSettingsService $permissionSettingsService,
		private readonly IL10N $l10n,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/** @return JSONResponse<Http::STATUS_*, array<string, mixed>, array<string, mixed>> */
	public function index(): JSONResponse {
		try {
			return $this->settingsResponse();
		} catch (Throwable $exception) {
			return $this->serverError(
				'load_permission_settings_failed',
				$this->l10n->t('Could not load permission settings.'),
				$exception,
			);
		}
	}

	/** @return JSONResponse<Http::STATUS_*, array<string, mixed>, array<string, mixed>> */
	public function updateDefault(mixed $permissions = null): JSONResponse {
		try {
			$permissionSet = $this->parsePermissions($permissions);
		} catch (InvalidArgumentException) {
			return $this->validationError($this->l10n->t('The selected permissions are invalid.'));
		}

		try {
			$this->permissionSettingsService->setDefaultPermissions($permissionSet);

			return $this->settingsResponse();
		} catch (Throwable $exception) {
			return $this->serverError(
				'update_default_permissions_failed',
				$this->l10n->t('Could not update the default permissions.'),
				$exception,
			);
		}
	}

	/** @return JSONResponse<Http::STATUS_*, array<string, mixed>, array<string, mixed>> */
	public function createPreset(mixed $name = null, mixed $permissions = null): JSONResponse {
		if (!is_string($name)) {
			return $this->validationError($this->l10n->t('The preset is invalid.'));
		}

		try {
			$permissionSet = $this->parsePermissions($permissions);
			$preset = $this->permissionSettingsService->createCustomPreset($name, $permissionSet);

			return $this->success(
				['preset' => $preset->toArray()],
				Http::STATUS_CREATED,
			);
		} catch (InvalidArgumentException) {
			return $this->validationError($this->l10n->t('The preset is invalid.'));
		} catch (DomainException $exception) {
			return $this->presetDomainError($exception);
		} catch (Throwable $exception) {
			return $this->serverError(
				'create_preset_failed',
				$this->l10n->t('Could not create the preset.'),
				$exception,
			);
		}
	}

	/** @return JSONResponse<Http::STATUS_*, array<string, mixed>, array<string, mixed>> */
	public function updatePreset(
		mixed $presetId = null,
		mixed $name = null,
		mixed $permissions = null,
	): JSONResponse {
		if (!$this->isValidPresetId($presetId) || !is_string($name)) {
			return $this->validationError($this->l10n->t('The preset is invalid.'));
		}

		try {
			$permissionSet = $this->parsePermissions($permissions);
			$preset = $this->permissionSettingsService->updateCustomPreset(
				$presetId,
				$name,
				$permissionSet,
			);

			return $this->success(['preset' => $preset->toArray()]);
		} catch (InvalidArgumentException) {
			return $this->validationError($this->l10n->t('The preset is invalid.'));
		} catch (DomainException $exception) {
			return $this->presetDomainError($exception);
		} catch (Throwable $exception) {
			return $this->serverError(
				'update_preset_failed',
				$this->l10n->t('Could not update the preset.'),
				$exception,
			);
		}
	}

	/** @return JSONResponse<Http::STATUS_*, array<string, mixed>, array<string, mixed>> */
	public function destroyPreset(mixed $presetId = null): JSONResponse {
		if (!$this->isValidPresetId($presetId)) {
			return $this->validationError($this->l10n->t('The preset is invalid.'));
		}

		try {
			$this->permissionSettingsService->deleteCustomPreset($presetId);

			return $this->success(['presetId' => $presetId]);
		} catch (InvalidArgumentException) {
			return $this->validationError($this->l10n->t('The preset is invalid.'));
		} catch (DomainException $exception) {
			return $this->presetDomainError($exception);
		} catch (Throwable $exception) {
			return $this->serverError(
				'delete_preset_failed',
				$this->l10n->t('Could not delete the preset.'),
				$exception,
			);
		}
	}

	/** @return JSONResponse<Http::STATUS_OK, array<string, mixed>, array<string, mixed>> */
	private function settingsResponse(): JSONResponse {
		return $this->success([
			'defaultPermissions' => $this->permissionSettingsService
				->getDefaultPermissions()
				->toArray(),
			'presets' => array_map(
				static fn (PermissionPreset $preset): array => $preset->toArray(),
				$this->permissionSettingsService->getPresets(),
			),
		]);
	}

	private function parsePermissions(mixed $permissions): PermissionSet {
		if (!is_array($permissions)) {
			throw new InvalidArgumentException('invalid_permissions');
		}

		return PermissionSet::fromArray($permissions);
	}

	/** @phpstan-assert-if-true non-empty-string $presetId */
	private function isValidPresetId(mixed $presetId): bool {
		return is_string($presetId)
			&& $presetId !== ''
			&& strlen($presetId) <= self::MAX_PRESET_ID_LENGTH;
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

	/** @return JSONResponse<Http::STATUS_*, array<string, mixed>, array<string, mixed>> */
	private function presetDomainError(DomainException $exception): JSONResponse {
		return match ($exception->getMessage()) {
			PermissionSettingsService::ERROR_DUPLICATE_PRESET_NAME => $this->error(
				'duplicate_preset_name',
				$this->l10n->t('A preset with this name already exists.'),
				Http::STATUS_CONFLICT,
			),
			PermissionSettingsService::ERROR_PRESET_LIMIT_REACHED => $this->error(
				'preset_limit_reached',
				$this->l10n->t('The maximum number of custom presets has been reached.'),
				Http::STATUS_CONFLICT,
			),
			PermissionSettingsService::ERROR_PRESET_NOT_FOUND => $this->error(
				'preset_not_found',
				$this->l10n->t('The selected preset does not exist.'),
				Http::STATUS_NOT_FOUND,
			),
			PermissionSettingsService::ERROR_IMMUTABLE_PRESET => $this->error(
				'immutable_preset',
				$this->l10n->t('Built-in presets cannot be changed.'),
				Http::STATUS_CONFLICT,
			),
			default => $this->validationError($this->l10n->t('The preset is invalid.')),
		};
	}

	/**
	 * @template S of Http::STATUS_NOT_FOUND|Http::STATUS_UNPROCESSABLE_ENTITY|Http::STATUS_CONFLICT|Http::STATUS_INTERNAL_SERVER_ERROR
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
