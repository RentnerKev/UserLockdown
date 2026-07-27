<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Tests\Unit\Controller;

use OCA\UserLockdown\Controller\PermissionSettingsApiController;
use OCA\UserLockdown\Policy\PermissionSet;
use OCA\UserLockdown\Service\PermissionSettingsService;
use OCP\AppFramework\Http;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PermissionSettingsApiControllerTest extends TestCase {
	/** @var array<string, array<string, mixed>> */
	private array $storedPresets = [];
	private int $defaultMask = 1;
	private PermissionSettingsApiController $controller;

	protected function setUp(): void {
		parent::setUp();

		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('searchKeys')->willReturnCallback(
			fn (string $app, string $prefix): array => array_values(array_filter(
				array_keys($this->storedPresets),
				static fn (string $key): bool => str_starts_with($key, $prefix),
			)),
		);
		$appConfig->method('getValueArray')->willReturnCallback(
			fn (string $app, string $key, array $default): array => $this->storedPresets[$key] ?? $default,
		);
		$appConfig->method('setValueArray')->willReturnCallback(
			function (string $app, string $key, array $value): bool {
				$this->storedPresets[$key] = $value;

				return true;
			},
		);
		$appConfig->method('deleteKey')->willReturnCallback(
			function (string $app, string $key): void {
				unset($this->storedPresets[$key]);
			},
		);
		$appConfig->method('getValueInt')->willReturnCallback(
			fn (): int => $this->defaultMask,
		);
		$appConfig->method('setValueInt')->willReturnCallback(
			function (string $app, string $key, int $value): bool {
				$this->defaultMask = $value;

				return true;
			},
		);

		$secureRandom = $this->createMock(ISecureRandom::class);
		$secureRandom->method('generate')->willReturn('0123456789abcdef');
		$service = new PermissionSettingsService($appConfig, $secureRandom);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text): string => $text);

		$this->controller = new PermissionSettingsApiController(
			'user_lockdown',
			$this->createMock(IRequest::class),
			$service,
			$l10n,
			$this->createMock(LoggerInterface::class),
		);
	}

	public function testIndexReturnsDefaultAndBuiltInPresets(): void {
		$response = $this->controller->index();

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		self::assertSame(PermissionSet::readOnly()->toArray(), $data['data']['defaultPermissions']);
		self::assertCount(6, $data['data']['presets']);
		self::assertSame('builtin:blocked', $data['data']['presets'][0]['id']);
		self::assertSame('Normal user', $data['data']['presets'][5]['name']);
	}

	public function testUpdateDefaultReturnsCompleteSettingsPayload(): void {
		$permissions = PermissionSet::fromMask(3);

		$response = $this->controller->updateDefault($permissions->toArray());

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame($permissions->toArray(), $response->getData()['data']['defaultPermissions']);
		self::assertCount(6, $response->getData()['data']['presets']);
	}

	public function testUpdateDefaultRejectsIncompletePayload(): void {
		$response = $this->controller->updateDefault([
			'viewFiles' => true,
		]);

		self::assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
		self::assertSame('validation_failed', $response->getData()['error']['code']);
		self::assertSame(1, $this->defaultMask);
	}

	public function testCustomPresetLifecycleUsesExactResponseShapes(): void {
		$permissions = PermissionSet::fromMask(5);

		$created = $this->controller->createPreset('Delete files', $permissions->toArray());

		self::assertSame(Http::STATUS_CREATED, $created->getStatus());
		self::assertSame([
			'data' => [
				'preset' => [
					'id' => 'custom:0123456789abcdef',
					'name' => 'Delete files',
					'builtIn' => false,
					'permissions' => $permissions->toArray(),
				],
			],
		], $created->getData());

		$updatedPermissions = PermissionSet::fromMask(17);
		$updated = $this->controller->updatePreset(
			'custom:0123456789abcdef',
			'Files and password',
			$updatedPermissions->toArray(),
		);
		self::assertSame(Http::STATUS_OK, $updated->getStatus());
		self::assertSame(
			$updatedPermissions->toArray(),
			$updated->getData()['data']['preset']['permissions'],
		);

		$deleted = $this->controller->destroyPreset('custom:0123456789abcdef');
		self::assertSame(Http::STATUS_OK, $deleted->getStatus());
		self::assertSame([
			'data' => ['presetId' => 'custom:0123456789abcdef'],
		], $deleted->getData());
	}

	public function testBuiltInPresetCannotBeUpdated(): void {
		$response = $this->controller->updatePreset(
			'builtin:read-only',
			'Renamed',
			PermissionSet::readOnly()->toArray(),
		);

		self::assertSame(Http::STATUS_CONFLICT, $response->getStatus());
		self::assertSame('immutable_preset', $response->getData()['error']['code']);
	}

	public function testMissingCustomPresetReturnsNotFound(): void {
		$response = $this->controller->destroyPreset('custom:0123456789abcdef');

		self::assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		self::assertSame('preset_not_found', $response->getData()['error']['code']);
	}

	public function testPresetNameCannotCollideWithBuiltInPreset(): void {
		$response = $this->controller->createPreset(
			'normal USER',
			PermissionSet::readOnly()->toArray(),
		);

		self::assertSame(Http::STATUS_CONFLICT, $response->getStatus());
		self::assertSame('duplicate_preset_name', $response->getData()['error']['code']);
	}
}
