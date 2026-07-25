<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Dav;

use OCA\UserLockdown\Service\RestrictionContext;
use OCP\IRequest;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

final class ReadOnlyPlugin extends ServerPlugin {
	/** @var list<string> */
	private const READ_METHODS = [
		'GET',
		'HEAD',
		'OPTIONS',
		'PROPFIND',
		'REPORT',
	];

	public function __construct(
		private readonly RestrictionContext $restrictionContext,
		private readonly IRequest $request,
	) {
	}

	public function initialize(Server $server): void {
		// Authentication uses priority 10 and ACL priority 20. Run between both.
		$server->on('beforeMethod:*', $this->guardRequest(...), 15);
	}

	public function getPluginName(): string {
		return 'user-lockdown-read-only';
	}

	public function guardRequest(
		RequestInterface $request,
		ResponseInterface $response,
	): void {
		$userId = $this->restrictionContext->getRestrictedUserId();
		if ($userId === null) {
			return;
		}

		$method = strtoupper($request->getMethod());
		if (!in_array($method, self::READ_METHODS, true)) {
			throw new Forbidden('This action has been disabled by your administrator.');
		}

		if ($this->isLegacyFilesEndpoint()) {
			return;
		}

		$path = ltrim(rawurldecode($request->getPath()), '/');
		if ($method === 'OPTIONS' && $path === '') {
			return;
		}

		if (!$this->isUserFilesPath($path, $userId)) {
			throw new Forbidden('This action has been disabled by your administrator.');
		}
	}

	private function isLegacyFilesEndpoint(): bool {
		$requestPath = parse_url($this->request->getRequestUri(), PHP_URL_PATH);
		if (!is_string($requestPath)) {
			return false;
		}

		$marker = '/remote.php/webdav';
		$position = strpos($requestPath, $marker);
		if ($position === false) {
			return false;
		}

		$suffix = substr($requestPath, $position + strlen($marker));
		return $suffix === '' || str_starts_with($suffix, '/');
	}

	private function isUserFilesPath(string $path, string $userId): bool {
		$segments = explode('/', $path);
		if (count($segments) < 2) {
			return false;
		}

		if ($segments[0] !== 'files' && $segments[0] !== 'uploads') {
			return false;
		}

		return rawurldecode($segments[1]) === $userId;
	}
}
