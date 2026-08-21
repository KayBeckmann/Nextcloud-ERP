<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\CostEntryMapper;
use OCA\ERP\Db\CostSettingsMapper;
use OCA\ERP\Service\CostService;
use OCP\IDBConnection;
use Test\TestCase;

/**
 * @group DB
 */
final class CostServiceTest extends TestCase {
	private const TEST_YEAR = 999901;

	private CostService $service;
	private CostEntryMapper $entryMapper;
	private CostSettingsMapper $settingsMapper;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->entryMapper = new CostEntryMapper($db);
		$this->settingsMapper = new CostSettingsMapper($db);
		$this->service = new CostService($this->entryMapper, $this->settingsMapper);
	}

	protected function tearDown(): void {
		foreach ($this->entryMapper->findByYear(self::TEST_YEAR) as $entry) {
			$this->entryMapper->delete($entry);
		}
		$settings = $this->settingsMapper->findByYear(self::TEST_YEAR);
		if ($settings !== null) {
			$this->settingsMapper->delete($settings);
		}
		parent::tearDown();
	}

	public function testCreateEntryRejectsUnknownCategory(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->createEntry('spaceship_fuel', 'x', 10.0, self::TEST_YEAR, 1, null);
	}

	public function testCreateEntryRejectsMonthOutOfRange(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->createEntry('rent', 'Miete', 800.0, self::TEST_YEAR, 13, null);
	}

	public function testCreateAndListEntries(): void {
		$this->service->createEntry('rent', 'Miete Werkstatt', 800.0, self::TEST_YEAR, 1, null);
		$this->service->createEntry('software', 'Buchhaltungssoftware', 49.0, self::TEST_YEAR, 1, null);
		$this->assertCount(2, $this->service->listEntries(self::TEST_YEAR));
	}

	public function testUpdateEntry(): void {
		$entry = $this->service->createEntry('rent', 'Miete', 800.0, self::TEST_YEAR, 1, null);
		$updated = $this->service->updateEntry($entry->getId(), 'rent', 'Miete Werkstatt', 850.0, self::TEST_YEAR, 2, 'erhöht');
		$this->assertSame('Miete Werkstatt', $updated->getTitle());
		$this->assertSame(850.0, $updated->getMonthlyAmount());
		$this->assertSame(2, $updated->getMonth());
	}

	public function testUpdateUnknownEntryThrows(): void {
		$this->expectException(\OutOfBoundsException::class);
		$this->service->updateEntry(999999999, 'rent', 'x', 1.0, self::TEST_YEAR, 1, null);
	}

	public function testRemoveEntry(): void {
		$entry = $this->service->createEntry('rent', 'Miete', 800.0, self::TEST_YEAR, 1, null);
		$this->service->removeEntry($entry->getId());
		$this->assertCount(0, $this->service->listEntries(self::TEST_YEAR));
	}

	public function testGetSettingsCreatesDefaultsWhenMissing(): void {
		$settings = $this->service->getSettings(self::TEST_YEAR);
		$this->assertSame(self::TEST_YEAR, $settings->getYear());
		$this->assertSame(1600.0, $settings->getProductiveHoursPerYear());
		$this->assertSame(0.0, $settings->getMaterialSurchargePercent());
	}

	public function testUpdateSettingsRejectsNegativeValues(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->updateSettings(self::TEST_YEAR, -1.0, 0.0, 0.0);
	}

	public function testUpdateSettingsPersists(): void {
		$this->service->updateSettings(self::TEST_YEAR, 1500.0, 15.0, 30.0);
		$settings = $this->service->getSettings(self::TEST_YEAR);
		$this->assertSame(1500.0, $settings->getProductiveHoursPerYear());
		$this->assertSame(15.0, $settings->getMaterialSurchargePercent());
		$this->assertSame(30.0, $settings->getProductSurchargePercent());
	}

	public function testGetYearOverviewCalculatesInternalHourlyRate(): void {
		$this->service->createEntry('rent', 'Miete', 4000.0, self::TEST_YEAR, 1, null);
		$this->service->updateSettings(self::TEST_YEAR, 200.0, 0.0, 0.0);

		$overview = $this->service->getYearOverview(self::TEST_YEAR);
		$this->assertSame(4000.0, $overview['annualCosts']);
		$this->assertSame(20.0, $overview['internalHourlyRate']);
		$this->assertSame(['rent' => 4000.0], $overview['costsByCategory']);
	}
}
