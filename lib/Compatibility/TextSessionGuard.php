<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Compatibility;

use JsonException;
use OCA\Text\Controller\ISessionAwareController;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;
use Throwable;

/**
 * Compatibility adapter for Nextcloud Text's token-authenticated sessions.
 *
 * Text validates PublicPage session tokens in its own middleware and exposes the
 * resolved user through ISessionAwareController. Persisted token hashes provide
 * a fail-safe fallback if cross-app middleware ordering changes.
 */
final class TextSessionGuard {
	private const APP_ID = 'user_lockdown';
	private const CLEANUP_INTERVAL = 86_400;
	private const CLEANUP_KEY = 'text_cleanup_at';
	private const CONFIG_PREFIX = 'text_ro_';
	private const MAX_IDLE_AGE = 15_552_000;
	private const REFRESH_INTERVAL = 3_600;

	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly ITimeFactory $timeFactory,
	) {
	}

	public function getControllerUserId(Controller $controller): ?string {
		if (!$controller instanceof ISessionAwareController) {
			return null;
		}

		try {
			$userId = trim($controller->getUserId());
		} catch (Throwable) {
			return null;
		}

		return $userId !== '' ? $userId : null;
	}

	public function remember(string $sessionToken, string $userId): void {
		$sessionToken = trim($sessionToken);
		$userId = trim($userId);
		if ($sessionToken === '' || $userId === '') {
			return;
		}

		$key = $this->tokenKey($sessionToken);
		$now = $this->timeFactory->getTime();
		$current = $this->decodeMarker($this->appConfig->getValueString(
			self::APP_ID,
			$key,
			'',
			lazy: true,
		));
		if (
			$current !== null
			&& $current['userId'] === $userId
			&& $current['seenAt'] >= $now - self::REFRESH_INTERVAL
		) {
			return;
		}

		$this->appConfig->setValueString(
			self::APP_ID,
			$key,
			json_encode(
				['userId' => $userId, 'seenAt' => $now],
				JSON_THROW_ON_ERROR,
			),
			lazy: true,
			sensitive: true,
		);
		$this->pruneExpiredMarkers($now);
	}

	public function getRememberedUserId(string $sessionToken): ?string {
		$sessionToken = trim($sessionToken);
		if ($sessionToken === '') {
			return null;
		}

		$marker = $this->decodeMarker($this->appConfig->getValueString(
			self::APP_ID,
			$this->tokenKey($sessionToken),
			'',
			lazy: true,
		));

		return $marker['userId'] ?? null;
	}

	public function forget(string $sessionToken): void {
		$sessionToken = trim($sessionToken);
		if ($sessionToken === '') {
			return;
		}

		$this->appConfig->deleteKey(self::APP_ID, $this->tokenKey($sessionToken));
	}

	public function forgetUser(string $userId): void {
		foreach ($this->appConfig->searchKeys(self::APP_ID, self::CONFIG_PREFIX, true) as $key) {
			$marker = $this->decodeMarker($this->appConfig->getValueString(
				self::APP_ID,
				$key,
				'',
				lazy: true,
			));
			if (($marker['userId'] ?? null) === $userId) {
				$this->appConfig->deleteKey(self::APP_ID, $key);
			}
		}
	}

	/**
	 * @return array{userId: string, seenAt: int}|null
	 */
	private function decodeMarker(string $value): ?array {
		try {
			$decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return null;
		}

		if (
			!is_array($decoded)
			|| !isset($decoded['userId'], $decoded['seenAt'])
			|| !is_string($decoded['userId'])
			|| !is_int($decoded['seenAt'])
			|| trim($decoded['userId']) === ''
		) {
			return null;
		}

		return [
			'userId' => trim($decoded['userId']),
			'seenAt' => $decoded['seenAt'],
		];
	}

	private function pruneExpiredMarkers(int $now): void {
		$lastCleanup = $this->appConfig->getValueInt(
			self::APP_ID,
			self::CLEANUP_KEY,
			0,
			lazy: true,
		);
		if ($lastCleanup >= $now - self::CLEANUP_INTERVAL) {
			return;
		}

		$this->appConfig->setValueInt(
			self::APP_ID,
			self::CLEANUP_KEY,
			$now,
			lazy: true,
			sensitive: false,
		);
		foreach ($this->appConfig->searchKeys(self::APP_ID, self::CONFIG_PREFIX, true) as $key) {
			$marker = $this->decodeMarker($this->appConfig->getValueString(
				self::APP_ID,
				$key,
				'',
				lazy: true,
			));
			if ($marker === null || $marker['seenAt'] < $now - self::MAX_IDLE_AGE) {
				$this->appConfig->deleteKey(self::APP_ID, $key);
			}
		}
	}

	private function tokenKey(string $sessionToken): string {
		return self::CONFIG_PREFIX . substr(hash('sha256', $sessionToken), 0, 56);
	}
}
