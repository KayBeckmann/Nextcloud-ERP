<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\VatRateMapper;
use OCA\ERP\Service\VatRateService;
use OCP\IDBConnection;
use Test\TestCase;

/**
 * @group DB
 */
final class VatRateServiceTest extends TestCase {
	private VatRateService $service;
	private VatRateMapper $mapper;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->mapper = new VatRateMapper($db);
		$this->service = new VatRateService($this->mapper);
	}

	protected function tearDown(): void {
		foreach ($this->mapper->findAll() as $rate) {
			if (str_starts_with($rate->getName(), 'phpunit-')) {
				$this->mapper->delete($rate);
			}
		}
		parent::tearDown();
	}

	public function testOnlyOneRateCanBeDefaultAtATime(): void {
		$first = $this->service->create('phpunit-19', 19.0, true, true);
		$second = $this->service->create('phpunit-7', 7.0, true, true);

		$reloadedFirst = array_values(array_filter($this->mapper->findAll(), fn ($r) => $r->getId() === $first->getId()))[0];
		$this->assertFalse($reloadedFirst->getIsDefault());
		$this->assertTrue($second->getIsDefault());
	}

	public function testUpdateUnknownRateThrows(): void {
		$this->expectException(\OutOfBoundsException::class);
		$this->service->update(999999999, 'x', 19.0, false, true);
	}

	public function testUpdateCanChangePercentage(): void {
		$rate = $this->service->create('phpunit-update', 19.0, false, true);
		$updated = $this->service->update($rate->getId(), 'phpunit-update', 16.0, false, true);
		$this->assertSame(16.0, $updated->getPercentage());
	}
}
