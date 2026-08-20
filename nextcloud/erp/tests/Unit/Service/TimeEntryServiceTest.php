<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\CustomerContractMapper;
use OCA\ERP\Db\CustomerContractRateMapper;
use OCA\ERP\Db\ProjectMapper;
use OCA\ERP\Db\StandardRateMapper;
use OCA\ERP\Db\TimeEntryMapper;
use OCA\ERP\Db\WorkType;
use OCA\ERP\Db\WorkTypeMapper;
use OCA\ERP\Service\RateService;
use OCA\ERP\Service\TimeEntryService;
use OCP\IDBConnection;
use Test\TestCase;

/**
 * @group DB
 */
final class TimeEntryServiceTest extends TestCase {
	private TimeEntryService $service;
	private TimeEntryMapper $mapper;
	private WorkTypeMapper $workTypeMapper;
	private StandardRateMapper $standardRateMapper;
	private WorkType $workType;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->mapper = new TimeEntryMapper($db);
		$this->workTypeMapper = new WorkTypeMapper($db);
		$this->standardRateMapper = new StandardRateMapper($db);
		$projectMapper = new ProjectMapper($db);
		$rateService = new RateService($this->standardRateMapper, new CustomerContractMapper($db), new CustomerContractRateMapper($db));
		$this->service = new TimeEntryService($this->mapper, $projectMapper, $this->workTypeMapper, $rateService);

		$workType = new WorkType();
		$workType->setName('phpunit-worktype-timeentry');
		$workType->setHourlyRate(42.0);
		$workType->setActive(true);
		$workType->setCreatedAt(time());
		$workType->setUpdatedAt(time());
		$this->workType = $this->workTypeMapper->insert($workType);
	}

	protected function tearDown(): void {
		foreach ($this->mapper->findByUser('phpunit-timeentry-user') as $entry) {
			$this->mapper->delete($entry);
		}
		foreach ($this->standardRateMapper->findAll() as $rate) {
			if ($rate->getWorkTypeId() === $this->workType->getId()) {
				$this->standardRateMapper->delete($rate);
			}
		}
		$this->workTypeMapper->delete($this->workType);
		parent::tearDown();
	}

	public function testCreateFallsBackToWorkTypeDefaultRateWhenNoStandardRateExists(): void {
		$entry = $this->service->create('phpunit-timeentry-user', [], $this->workType->getId(), null, null, '2026-08-20', 90, 15, true, 'Testeintrag');

		$this->assertSame(42.0, $entry->getRateSnapshot());
		$this->assertSame(90, $entry->getDurationMinutes());
		$this->assertSame(15, $entry->getBreakMinutes());
		$this->assertTrue($entry->getBillable());
	}

	/**
	 * Regressionstest analog zum QuotePosition-Fund aus Phase 5: billable=false
	 * ist ein legitimer Wert, der trotz Entity-Dirty-Tracking wirklich
	 * persistiert werden muss (siehe TimeEntry::$billable-Kommentar).
	 */
	public function testBillableFalseIsPersistedCorrectly(): void {
		$entry = $this->service->create('phpunit-timeentry-user', [], $this->workType->getId(), null, null, '2026-08-20', 30, 0, false, null);
		$this->assertFalse($entry->getBillable());

		$reloaded = (new TimeEntryMapper(\OC::$server->get(IDBConnection::class)))->findById($entry->getId());
		$this->assertNotNull($reloaded);
		$this->assertFalse($reloaded->getBillable());
	}

	public function testRateSnapshotUsesStandardRateOverWorkTypeDefault(): void {
		$rate = new \OCA\ERP\Db\StandardRate();
		$rate->setWorkTypeId($this->workType->getId());
		$rate->setPrincipalType(null);
		$rate->setPrincipalId(null);
		$rate->setRate(50.0);
		$rate->setCreatedAt(time());
		$rate->setUpdatedAt(time());
		$this->standardRateMapper->insert($rate);

		$entry = $this->service->create('phpunit-timeentry-user', [], $this->workType->getId(), null, null, '2026-08-20', 45, 0, true, null);
		$this->assertSame(50.0, $entry->getRateSnapshot());
	}

	public function testCreateWithUnknownWorkTypeThrows(): void {
		$this->expectException(\OutOfBoundsException::class);
		$this->service->create('phpunit-timeentry-user', [], 999999999, null, null, '2026-08-20', 30, 0, true, null);
	}

	public function testCreateWithUnknownProjectThrows(): void {
		$this->expectException(\OutOfBoundsException::class);
		$this->service->create('phpunit-timeentry-user', [], $this->workType->getId(), 999999999, null, '2026-08-20', 30, 0, true, null);
	}

	public function testUpdateDoesNotChangeRateSnapshot(): void {
		$entry = $this->service->create('phpunit-timeentry-user', [], $this->workType->getId(), null, null, '2026-08-20', 60, 0, true, null);
		$this->assertSame(42.0, $entry->getRateSnapshot());

		$updated = $this->service->update($entry->getId(), '2026-08-21', 120, 10, false, 'geändert');
		$this->assertSame(42.0, $updated->getRateSnapshot());
		$this->assertSame(120, $updated->getDurationMinutes());
		$this->assertSame('2026-08-21', $updated->getEntryDate());
	}

	public function testDeleteRemovesEntry(): void {
		$entry = $this->service->create('phpunit-timeentry-user', [], $this->workType->getId(), null, null, '2026-08-20', 30, 0, true, null);
		$this->service->delete($entry->getId());

		$this->expectException(\OutOfBoundsException::class);
		$this->service->get($entry->getId());
	}
}
