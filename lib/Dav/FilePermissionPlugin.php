<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Dav;

use OCA\UserLockdown\Policy\Permission;
use OCA\UserLockdown\Policy\PermissionSet;
use OCA\UserLockdown\Service\RestrictionContext;
use OCP\IRequest;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

final class FilePermissionPlugin extends ServerPlugin {
	private const FILES_COLLECTION = 'files';
	private const UPLOADS_COLLECTION = 'uploads';

	/** @var list<string> */
	private const READ_METHODS = [
		'GET',
		'HEAD',
		'OPTIONS',
		'PROPFIND',
		'REPORT',
	];

	/** @var list<string> */
	private const WRITE_METHODS = [
		'PUT',
		'POST',
		'PATCH',
		'MKCOL',
		'MOVE',
		'COPY',
		'PROPPATCH',
		'LOCK',
		'UNLOCK',
	];

	private ?Server $server = null;

	public function __construct(
		private readonly RestrictionContext $restrictionContext,
		private readonly IRequest $request,
	) {
	}

	public function initialize(Server $server): void {
		$this->server = $server;
		$server->on('beforeMethod:*', $this->guardRequest(...), 15);
	}

	public function getPluginName(): string {
		return 'user-lockdown-file-permissions';
	}

	public function guardRequest(
		RequestInterface $request,
		ResponseInterface $response,
	): void {
		$permissionSet = $this->restrictionContext->getPermissionSet();
		$userId = $this->restrictionContext->getRestrictedUserId();
		if ($permissionSet === null || $userId === null) {
			return;
		}

		$method = strtoupper($request->getMethod());
		$path = ltrim($request->getPath(), '/');
		if ($method === 'OPTIONS' && $path === '') {
			return;
		}

		$legacyFilesEndpoint = $this->isLegacyFilesEndpoint();
		$collection = $legacyFilesEndpoint
			? self::FILES_COLLECTION
			: $this->getOwnCollection($path, $userId);
		if ($collection === null) {
			throw $this->forbidden();
		}

		if (in_array($method, self::READ_METHODS, true)) {
			$this->requirePermission($permissionSet, Permission::ViewFiles);
			return;
		}

		if ($method === 'DELETE') {
			$permission = $collection === self::UPLOADS_COLLECTION
				? Permission::WriteFiles
				: Permission::DeleteFiles;
			$this->requirePermission($permissionSet, $permission);
			return;
		}

		if (!in_array($method, self::WRITE_METHODS, true)) {
			throw $this->forbidden();
		}

		$this->requirePermission($permissionSet, Permission::WriteFiles);
		if ($method === 'MOVE' || $method === 'COPY') {
			$this->guardDestination($request, $permissionSet, $userId, $legacyFilesEndpoint);
		}
	}

	private function guardDestination(
		RequestInterface $request,
		PermissionSet $permissionSet,
		string $userId,
		bool $legacyFilesEndpoint,
	): void {
		$destination = $request->getHeader('Destination');
		if (!is_string($destination) || $destination === '' || $this->server === null) {
			throw $this->forbidden();
		}

		$destinationPath = $this->server->calculateUri($destination);
		if (
			!$legacyFilesEndpoint
			&& $this->getOwnCollection($destinationPath, $userId) === null
		) {
			throw $this->forbidden();
		}

		$overwrite = strtoupper((string)($request->getHeader('Overwrite') ?? 'T'));
		if (
			$overwrite !== 'F'
			&& $this->server->tree->nodeExists($destinationPath)
			&& !$permissionSet->allows(Permission::DeleteFiles)
		) {
			throw $this->forbidden();
		}
	}

	private function requirePermission(
		PermissionSet $permissionSet,
		Permission $permission,
	): void {
		if (!$permissionSet->allows($permission)) {
			throw $this->forbidden();
		}
	}

	private function forbidden(): Forbidden {
		return new Forbidden('This action has been disabled by your administrator.');
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

	private function getOwnCollection(string $path, string $userId): ?string {
		$segments = explode('/', ltrim($path, '/'));
		if (count($segments) < 2) {
			return null;
		}
		if (in_array('.', $segments, true) || in_array('..', $segments, true)) {
			return null;
		}

		if (
			$segments[0] !== self::FILES_COLLECTION
			&& $segments[0] !== self::UPLOADS_COLLECTION
		) {
			return null;
		}

		return $segments[1] === $userId ? $segments[0] : null;
	}
}
