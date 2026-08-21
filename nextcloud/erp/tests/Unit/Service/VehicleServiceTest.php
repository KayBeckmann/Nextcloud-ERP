<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\VehicleFuelLogMapper;
use OCA\ERP\Db\VehicleMapper;
use OCA\ERP\Db\WarehouseMapper;
use OCA\ERP\Service\ErpFolderService;
use OCA\ERP\Service\VehicleService;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use Test\TestCase;

/**
 * @group DB
 */
final class VehicleServiceTest extends TestCase {
	private const TEST_UID = 'phpunit-vehicle-user';

	private VehicleService $service;
	private VehicleMapper $mapper;
	private VehicleFuelLogMapper $fuelLogMapper;
	private WarehouseMapper $warehouseMapper;
	private ErpFolderService $folderService;
	private IUser $user;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->mapper = new VehicleMapper($db);
		$this->fuelLogMapper = new VehicleFuelLogMapper($db);
		$this->warehouseMapper = new WarehouseMapper($db);
		$this->folderService = new ErpFolderService(\OC::$server->get(IRootFolder::class));
		$this->service = new VehicleService($this->mapper, $this->fuelLogMapper, $this->warehouseMapper, $this->folderService);

		$userManager = \OC::$server->get(IUserManager::class);
		if ($userManager->userExists(self::TEST_UID)) {
			$userManager->get(self::TEST_UID)->delete();
		}
		$this->user = $userManager->createUser(self::TEST_UID, 'Phpunit-Test-Pass-1!');
		self::loginAsUser(self::TEST_UID);
	}

	protected function tearDown(): void {
		foreach ($this->mapper->findAll() as $vehicle) {
			if (str_starts_with($vehicle->getLicensePlate(), 'PHPUNIT-')) {
				foreach ($this->fuelLogMapper->findByVehicle($vehicle->getId()) as $log) {
					$this->fuelLogMapper->delete($log);
				}
				$this->mapper->delete($vehicle);
			}
		}
		self::logout();
		$this->user->delete();
		parent::tearDown();
	}

	public function testCreateDefaultsToActiveWithZeroMileage(): void {
		$vehicle = $this->service->create('PHPUNIT-1', 'VW Transporter', 'van', null, null, null);
		$this->assertSame('active', $vehicle->getStatus());
		$this->assertSame(0, $vehicle->getCurrentMileageKm());
	}

	public function testCreateRejectsDuplicateLicensePlate(): void {
		$this->service->create('PHPUNIT-2', null, 'car', null, null, null);
		$this->expectException(\InvalidArgumentException::class);
		$this->service->create('PHPUNIT-2', null, 'car', null, null, null);
	}

	public function testCreateRejectsUnknownVehicleType(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->create('PHPUNIT-3', null, 'spaceship', null, null, null);
	}

	public function testCreateRejectsEmptyLicensePlate(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->create('  ', null, 'car', null, null, null);
	}

	public function testUpdateChangesStatusAndKeepsUnrelatedFields(): void {
		$vehicle = $this->service->create('PHPUNIT-4', 'Mercedes Sprinter', 'van', 'kay', '2027-05-01', null);
		$updated = $this->service->update($vehicle->getId(), 'PHPUNIT-4', 'Mercedes Sprinter', 'van', 'inactive', 'kay', '2027-05-01', 'Werkstatt');
		$this->assertSame('inactive', $updated->getStatus());
		$this->assertSame('Werkstatt', $updated->getNotes());
	}

	public function testUpdateUnknownVehicleThrows(): void {
		$this->expectException(\OutOfBoundsException::class);
		$this->service->update(999999999, 'PHPUNIT-X', null, 'car', 'active', null, null, null);
	}

	public function testAddFuelLogAdvancesMileageOnlyWhenHigher(): void {
		$vehicle = $this->service->create('PHPUNIT-5', null, 'car', null, null, null);
		$this->service->addFuelLog($vehicle->getId(), '2026-08-21', 40.0, 65.0, 10500, null);
		$reloaded = $this->service->get($vehicle->getId());
		$this->assertSame(10500, $reloaded->getCurrentMileageKm());

		// Ein niedrigerer nachgetragener Kilometerstand wird nicht übernommen.
		$this->service->addFuelLog($vehicle->getId(), '2026-07-01', 35.0, 55.0, 9800, null);
		$reloaded2 = $this->service->get($vehicle->getId());
		$this->assertSame(10500, $reloaded2->getCurrentMileageKm());
	}

	public function testAddFuelLogRejectsNegativeValues(): void {
		$vehicle = $this->service->create('PHPUNIT-6', null, 'car', null, null, null);
		$this->expectException(\InvalidArgumentException::class);
		$this->service->addFuelLog($vehicle->getId(), '2026-08-21', -1.0, 10.0, 100, null);
	}

	public function testRemoveFuelLog(): void {
		$vehicle = $this->service->create('PHPUNIT-7', null, 'car', null, null, null);
		$log = $this->service->addFuelLog($vehicle->getId(), '2026-08-21', 40.0, 65.0, 100, null);
		$this->service->removeFuelLog($vehicle->getId(), $log->getId());
		$full = $this->service->getFull($vehicle->getId());
		$this->assertCount(0, $full['fuelLogs']);
	}

	public function testGetFullIncludesLinkedWarehouses(): void {
		$vehicle = $this->service->create('PHPUNIT-8', null, 'car', null, null, null);
		$warehouseService = new \OCA\ERP\Service\WarehouseService($this->warehouseMapper);
		$warehouse = $warehouseService->create('phpunit-vehicle-warehouse', 'vehicle', null, null, $vehicle->getId());

		$full = $this->service->getFull($vehicle->getId());
		$this->assertCount(1, $full['warehouses']);
		$this->assertSame($warehouse->getId(), $full['warehouses'][0]->getId());

		$this->warehouseMapper->delete($warehouse);
	}

	public function testUploadReceiptStoresFileAndLinksIt(): void {
		$vehicle = $this->service->create('PHPUNIT-9', null, 'car', null, null, null);
		$log = $this->service->addFuelLog($vehicle->getId(), '2026-08-21', 40.0, 65.0, 100, null);

		$content = base64_encode('phpunit-fake-image-content');
		$updated = $this->service->uploadReceipt($vehicle->getId(), $log->getId(), $this->user, 'beleg.jpg', $content);

		$this->assertNotNull($updated->getReceiptFileId());

		$folder = $this->folderService->ensureVehicleReceiptFolder($this->user, 'PHPUNIT-9');
		$this->assertTrue($folder->nodeExists('beleg.jpg'));
	}

	public function testUploadReceiptRejectsInvalidBase64(): void {
		$vehicle = $this->service->create('PHPUNIT-10', null, 'car', null, null, null);
		$log = $this->service->addFuelLog($vehicle->getId(), '2026-08-21', 40.0, 65.0, 100, null);

		$this->expectException(\InvalidArgumentException::class);
		$this->service->uploadReceipt($vehicle->getId(), $log->getId(), $this->user, 'beleg.jpg', '@@@not-base64@@@');
	}
}
