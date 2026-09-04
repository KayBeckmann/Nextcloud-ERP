<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Service\ErpFolderService;
use OCP\Files\IRootFolder;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use Test\TestCase;

/**
 * Integrationstest gegen die echte Files-API mit einem eigens angelegten,
 * am Ende wieder gelöschten Testuser. Seit ADR-0024 leben die Ordner im
 * gemeinsamen Group Folder "ERP-Firma" statt im User-Home (ADR-0009s
 * "bekannte Einschränkung") — Voraussetzung: `groupfolders`-App installiert
 * und der Group Folder bereits angelegt/den ERP-Gruppen zugewiesen (siehe
 * `docker/README.md` bzw. `.github/workflows/ci.yml`). Der Testuser wird
 * hier nur der Gruppe zugeordnet, das Anlegen des Group Folders selbst ist
 * bewusst einmalige Infrastruktur-Provisionierung, keine Testverantwortung.
 *
 * @group DB
 */
final class ErpFolderServiceTest extends TestCase {
	private const TEST_UID = 'phpunit-erp-folder-user';
	private const TEST_GROUP = 'erp-projektleiter';

	private ErpFolderService $service;
	private IUser $user;
	private IGroupManager $groupManager;

	protected function setUp(): void {
		parent::setUp();
		$userManager = \OC::$server->get(IUserManager::class);
		if ($userManager->userExists(self::TEST_UID)) {
			$userManager->get(self::TEST_UID)->delete();
		}
		$this->user = $userManager->createUser(self::TEST_UID, 'Phpunit-Test-Pass-1!');

		$this->groupManager = \OC::$server->get(IGroupManager::class);
		$group = $this->groupManager->get(self::TEST_GROUP) ?? $this->groupManager->createGroup(self::TEST_GROUP);
		$group->addUser($this->user);

		if (!$this->groupManager->get(self::TEST_GROUP)?->inGroup($this->user)) {
			self::markTestSkipped(
				'Konnte Testuser nicht zu "' . self::TEST_GROUP . '" hinzufügen — '
				. 'ADR-0024-Provisionierung (Group Folder "ERP-Firma") prüfen.'
			);
		}

		self::loginAsUser(self::TEST_UID);

		$this->service = new ErpFolderService(\OC::$server->get(IRootFolder::class));
	}

	protected function tearDown(): void {
		self::logout();
		$this->groupManager->get(self::TEST_GROUP)?->removeUser($this->user);
		$this->user->delete();
		parent::tearDown();
	}

	public function testEnsureStructureCreatesAllFolders(): void {
		$result = $this->service->ensureStructure($this->user);

		$names = array_column($result, 'name');
		$this->assertSame(
			['ERP', 'Projekte', 'Artikel', 'Produkte', 'Lieferanten', 'Fuhrpark', 'Kosten', 'Vorlagen', 'Archiv'],
			$names,
		);
		foreach ($result as $folder) {
			$this->assertGreaterThan(0, $folder['fileId']);
		}
	}

	public function testEnsureStructureIsIdempotent(): void {
		$first = $this->service->ensureStructure($this->user);
		$second = $this->service->ensureStructure($this->user);

		$this->assertSame(array_column($first, 'fileId'), array_column($second, 'fileId'));
	}
}
