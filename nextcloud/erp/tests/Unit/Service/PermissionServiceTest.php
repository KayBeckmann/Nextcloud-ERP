<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\PermissionMapper;
use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Service\PermissionService;
use OCP\IDBConnection;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use Test\TestCase;

/**
 * Integrationstest gegen die echte DB (Tabelle existiert bereits durch die
 * reale `occ app:enable`-Migration, siehe docs/status.md) — User/Gruppen sind
 * gemockt, damit der Test keine echten Nextcloud-Accounts anlegen muss.
 *
 * @group DB
 */
final class PermissionServiceTest extends TestCase {
	private PermissionService $service;
	private PermissionMapper $mapper;
	private IUser $user;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->mapper = new PermissionMapper($db);

		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')->willReturn('phpunit-test-user');

		$group = $this->createMock(IGroup::class);
		$group->method('getGID')->willReturn('phpunit-test-group');

		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('getUserGroups')->willReturn([$group]);
		$groupManager->method('isAdmin')->willReturn(false);

		$userManager = $this->createMock(IUserManager::class);

		$this->service = new PermissionService($this->mapper, $userManager, $groupManager);
	}

	protected function tearDown(): void {
		foreach ($this->mapper->findAll() as $entry) {
			if (str_starts_with($entry->getPrincipalId(), 'phpunit-')) {
				$this->mapper->delete($entry);
			}
		}
		parent::tearDown();
	}

	public function testNoEntryResolvesToNone(): void {
		$level = $this->service->getEffectivePermission($this->user, ResourceType::Projekte);
		$this->assertSame(PermissionLevel::None, $level);
	}

	public function testSetPermissionOnGroupIsResolvedForMember(): void {
		$this->service->setPermission('group', 'phpunit-test-group', ResourceType::Projekte, PermissionLevel::Write);

		$level = $this->service->getEffectivePermission($this->user, ResourceType::Projekte);
		$this->assertSame(PermissionLevel::Write, $level);

		$all = $this->service->getEffectivePermissions($this->user);
		$this->assertSame('write', $all['projekte']);
		$this->assertSame('none', $all['rechnungen']);
	}

	public function testUpdatingAnExistingEntryOverwritesInsteadOfDuplicating(): void {
		$this->service->setPermission('user', 'phpunit-test-user', ResourceType::Angebote, PermissionLevel::Read);
		$this->service->setPermission('user', 'phpunit-test-user', ResourceType::Angebote, PermissionLevel::Approve);

		$matching = array_filter(
			$this->mapper->findAll(),
			static fn ($e) => $e->getPrincipalId() === 'phpunit-test-user' && $e->getResourceType() === 'angebote',
		);
		$this->assertCount(1, $matching);
		$this->assertSame(PermissionLevel::Approve->value, array_values($matching)[0]->getPermission());
	}

	public function testSettingNoneDeletesTheEntry(): void {
		$this->service->setPermission('user', 'phpunit-test-user', ResourceType::Lager, PermissionLevel::Write);
		$this->service->setPermission('user', 'phpunit-test-user', ResourceType::Lager, PermissionLevel::None);

		$matching = array_filter(
			$this->mapper->findAll(),
			static fn ($e) => $e->getPrincipalId() === 'phpunit-test-user' && $e->getResourceType() === 'lager',
		);
		$this->assertCount(0, $matching);
	}

	public function testInvalidPrincipalTypeIsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->setPermission('robot', 'phpunit-test-x', ResourceType::Lager, PermissionLevel::Read);
	}
}
