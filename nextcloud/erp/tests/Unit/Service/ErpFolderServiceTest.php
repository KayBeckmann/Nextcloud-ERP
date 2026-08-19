<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Service\ErpFolderService;
use OCP\Files\IRootFolder;
use OCP\IUser;
use OCP\IUserManager;
use Test\TestCase;

/**
 * Integrationstest gegen die echte Files-API mit einem eigens angelegten,
 * am Ende wieder gelöschten Testuser (ADR-0009: Ordner leben im User-Home).
 *
 * @group DB
 */
final class ErpFolderServiceTest extends TestCase {
	private const TEST_UID = 'phpunit-erp-folder-user';

	private ErpFolderService $service;
	private IUser $user;

	protected function setUp(): void {
		parent::setUp();
		$userManager = \OC::$server->get(IUserManager::class);
		if ($userManager->userExists(self::TEST_UID)) {
			$userManager->get(self::TEST_UID)->delete();
		}
		$this->user = $userManager->createUser(self::TEST_UID, 'Phpunit-Test-Pass-1!');
		self::loginAsUser(self::TEST_UID);

		$this->service = new ErpFolderService(\OC::$server->get(IRootFolder::class));
	}

	protected function tearDown(): void {
		self::logout();
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
