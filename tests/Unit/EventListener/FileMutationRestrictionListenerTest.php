<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Tests\Unit\EventListener;

use OCA\UserLockdown\EventListener\FileMutationRestrictionListener;
use OCA\UserLockdown\Service\RestrictedUserService;
use OCA\UserLockdown\Service\RestrictionContext;
use OCP\Files\Events\Node\BeforeNodeRenamedEvent;
use OCP\Files\Events\Node\BeforeNodeWrittenEvent;
use OCP\Files\Node;
use OCP\Files\NotPermittedException;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FileMutationRestrictionListenerTest extends TestCase {
	#[DataProvider('ownFilePathProvider')]
	public function testMutationInOwnFilesIsBlocked(string $path): void {
		$listener = $this->createListenerForRestrictedUser('alice');
		$event = new BeforeNodeWrittenEvent($this->createNode($path));

		$this->expectException(NotPermittedException::class);
		$this->expectExceptionMessage('File changes are disabled.');

		$listener->handle($event);
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function ownFilePathProvider(): array {
		return [
			'absolute user files root' => ['/alice/files'],
			'absolute user file' => ['/alice/files/Documents/report.pdf'],
			'user-root relative files root' => ['/files'],
			'user-root relative file' => ['/files/Documents/report.pdf'],
		];
	}

	#[DataProvider('foreignPathProvider')]
	public function testMutationOutsideOwnFilesIsAllowed(string $path): void {
		$listener = $this->createListenerForRestrictedUser('alice');
		$event = new BeforeNodeWrittenEvent($this->createNode($path));

		$listener->handle($event);

		self::assertSame($path, $event->getNode()->getPath());
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function foreignPathProvider(): array {
		return [
			'another users files' => ['/bob/files/Documents/report.pdf'],
			'user metadata' => ['/alice/cache/thumbnail.png'],
			'similar files prefix' => ['/alice/files-backup/report.pdf'],
		];
	}

	public function testRenameIsBlockedWhenSourceIsInOwnFiles(): void {
		$listener = $this->createListenerForRestrictedUser('alice');
		$event = new BeforeNodeRenamedEvent(
			$this->createNode('/alice/files/source.txt'),
			$this->createNode('/bob/files/target.txt'),
		);

		$this->expectException(NotPermittedException::class);

		$listener->handle($event);
	}

	public function testRenameIsBlockedWhenTargetIsInOwnFiles(): void {
		$listener = $this->createListenerForRestrictedUser('alice');
		$event = new BeforeNodeRenamedEvent(
			$this->createNode('/bob/files/source.txt'),
			$this->createNode('/alice/files/target.txt'),
		);

		$this->expectException(NotPermittedException::class);

		$listener->handle($event);
	}

	public function testRenameBetweenForeignPathsIsAllowed(): void {
		$listener = $this->createListenerForRestrictedUser('alice');
		$event = new BeforeNodeRenamedEvent(
			$this->createNode('/bob/files/source.txt'),
			$this->createNode('/carol/files/target.txt'),
		);

		$listener->handle($event);

		self::assertSame('/bob/files/source.txt', $event->getSource()->getPath());
		self::assertSame('/carol/files/target.txt', $event->getTarget()->getPath());
	}

	private function createListenerForRestrictedUser(string $userId): FileMutationRestrictionListener {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isAdmin')->with($userId)->willReturn(false);
		$restrictedUserService = $this->createMock(RestrictedUserService::class);
		$restrictedUserService->method('isRestricted')->with($userId)->willReturn(true);
		$restrictionContext = new RestrictionContext(
			$userSession,
			$groupManager,
			$restrictedUserService,
		);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')
			->with('This action has been disabled by your administrator.')
			->willReturn('File changes are disabled.');

		return new FileMutationRestrictionListener($restrictionContext, $l10n);
	}

	private function createNode(string $path): Node {
		$node = $this->createMock(Node::class);
		$node->method('getPath')->willReturn($path);

		return $node;
	}
}
