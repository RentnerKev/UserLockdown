<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Kevin Sträßler
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserLockdown\Tests\Unit\EventListener;

use OCA\UserLockdown\EventListener\FileMutationRestrictionListener;
use OCA\UserLockdown\Policy\PermissionSet;
use OCA\UserLockdown\Service\RestrictedUserService;
use OCA\UserLockdown\Service\RestrictionContext;
use OCP\Files\Events\Node\BeforeNodeCopiedEvent;
use OCP\Files\Events\Node\BeforeNodeCreatedEvent;
use OCP\Files\Events\Node\BeforeNodeDeletedEvent;
use OCP\Files\Events\Node\BeforeNodeReadEvent;
use OCP\Files\Events\Node\BeforeNodeRenamedEvent;
use OCP\Files\Events\Node\BeforeNodeTouchedEvent;
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
	public function testReadRequiresViewPermission(): void {
		$listener = $this->createListener(PermissionSet::blocked());

		$this->expectException(NotPermittedException::class);
		$this->expectExceptionMessage('File changes are disabled.');
		$listener->handle(new BeforeNodeReadEvent(
			$this->createNode('/alice/files/report.txt'),
		));
	}

	public function testViewPermissionAllowsRead(): void {
		$event = new BeforeNodeReadEvent($this->createNode('/alice/files/report.txt'));

		$this->createListener(PermissionSet::readOnly())->handle($event);

		self::assertSame('/alice/files/report.txt', $event->getNode()->getPath());
	}

	/**
	 * @return iterable<string, array{class-string<BeforeNodeCopiedEvent|BeforeNodeCreatedEvent|BeforeNodeRenamedEvent|BeforeNodeTouchedEvent|BeforeNodeWrittenEvent>}>
	 */
	public static function writeEventProvider(): iterable {
		yield 'created' => [BeforeNodeCreatedEvent::class];
		yield 'written' => [BeforeNodeWrittenEvent::class];
		yield 'touched' => [BeforeNodeTouchedEvent::class];
		yield 'renamed' => [BeforeNodeRenamedEvent::class];
		yield 'copied' => [BeforeNodeCopiedEvent::class];
	}

	/**
	 * @param class-string<BeforeNodeCopiedEvent|BeforeNodeCreatedEvent|BeforeNodeRenamedEvent|BeforeNodeTouchedEvent|BeforeNodeWrittenEvent> $eventClass
	 */
	#[DataProvider('writeEventProvider')]
	public function testWriteOperationsRequireWritePermission(string $eventClass): void {
		$listener = $this->createListener(PermissionSet::readOnly());
		$event = $this->createWriteEvent($eventClass);

		$this->expectException(NotPermittedException::class);
		$listener->handle($event);
	}

	/**
	 * @param class-string<BeforeNodeCopiedEvent|BeforeNodeCreatedEvent|BeforeNodeRenamedEvent|BeforeNodeTouchedEvent|BeforeNodeWrittenEvent> $eventClass
	 */
	#[DataProvider('writeEventProvider')]
	public function testWritePermissionAllowsWriteOperations(string $eventClass): void {
		$event = $this->createWriteEvent($eventClass);

		$this->createListener($this->permissions(write: true))->handle($event);

		$this->addToAssertionCount(1);
	}

	public function testDeleteRequiresDeletePermission(): void {
		$listener = $this->createListener($this->permissions(write: true));

		$this->expectException(NotPermittedException::class);
		$listener->handle(new BeforeNodeDeletedEvent(
			$this->createNode('/alice/files/report.txt'),
		));
	}

	public function testDeletePermissionAllowsDeletionWithoutWritePermission(): void {
		$event = new BeforeNodeDeletedEvent($this->createNode('/alice/files/report.txt'));

		$this->createListener($this->permissions(delete: true))->handle($event);

		self::assertSame('/alice/files/report.txt', $event->getNode()->getPath());
	}

	/** @return array<string, array{string}> */
	public static function foreignPathProvider(): array {
		return [
			'another users files' => ['/bob/files/Documents/report.pdf'],
			'user metadata' => ['/alice/cache/thumbnail.png'],
			'similar files prefix' => ['/alice/files-backup/report.pdf'],
		];
	}

	#[DataProvider('foreignPathProvider')]
	public function testOperationsOutsideOwnFilesAreIgnored(string $path): void {
		$event = new BeforeNodeWrittenEvent($this->createNode($path));

		$this->createListener(PermissionSet::blocked())->handle($event);

		self::assertSame($path, $event->getNode()->getPath());
	}

	public function testRenameChecksBothSourceAndTarget(): void {
		$listener = $this->createListener(PermissionSet::readOnly());
		$event = new BeforeNodeRenamedEvent(
			$this->createNode('/bob/files/source.txt'),
			$this->createNode('/alice/files/target.txt'),
		);

		$this->expectException(NotPermittedException::class);
		$listener->handle($event);
	}

	public function testFullAccessPolicyBypassesFileEventRestrictions(): void {
		$event = new BeforeNodeDeletedEvent($this->createNode('/alice/files/report.txt'));

		$this->createListener(PermissionSet::fullAccess())->handle($event);

		self::assertSame('/alice/files/report.txt', $event->getNode()->getPath());
	}

	private function createListener(PermissionSet $permissionSet): FileMutationRestrictionListener {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isAdmin')->with('alice')->willReturn(false);
		$restrictedUserService = $this->createMock(RestrictedUserService::class);
		$restrictedUserService->method('getPermissionSet')
			->with('alice')
			->willReturn($permissionSet);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')
			->with('This action has been disabled by your administrator.')
			->willReturn('File changes are disabled.');

		return new FileMutationRestrictionListener(
			new RestrictionContext($userSession, $groupManager, $restrictedUserService),
			$l10n,
		);
	}

	/**
	 * @param class-string<BeforeNodeCopiedEvent|BeforeNodeCreatedEvent|BeforeNodeRenamedEvent|BeforeNodeTouchedEvent|BeforeNodeWrittenEvent> $eventClass
	 */
	private function createWriteEvent(
		string $eventClass,
	): BeforeNodeCopiedEvent|BeforeNodeCreatedEvent|BeforeNodeRenamedEvent|BeforeNodeTouchedEvent|BeforeNodeWrittenEvent {
		$source = $this->createNode('/alice/files/source.txt');

		return match ($eventClass) {
			BeforeNodeCopiedEvent::class => new BeforeNodeCopiedEvent(
				$source,
				$this->createNode('/alice/files/target.txt'),
			),
			BeforeNodeRenamedEvent::class => new BeforeNodeRenamedEvent(
				$source,
				$this->createNode('/alice/files/target.txt'),
			),
			BeforeNodeCreatedEvent::class => new BeforeNodeCreatedEvent($source),
			BeforeNodeTouchedEvent::class => new BeforeNodeTouchedEvent($source),
			BeforeNodeWrittenEvent::class => new BeforeNodeWrittenEvent($source),
			default => throw new \InvalidArgumentException('Unsupported write event class.'),
		};
	}

	private function createNode(string $path): Node {
		$node = $this->createMock(Node::class);
		$node->method('getPath')->willReturn($path);

		return $node;
	}

	private function permissions(bool $write = false, bool $delete = false): PermissionSet {
		return PermissionSet::fromArray([
			'viewFiles' => true,
			'writeFiles' => $write,
			'deleteFiles' => $delete,
			'shareFiles' => false,
			'changePassword' => false,
			'fullAccess' => false,
		]);
	}
}
