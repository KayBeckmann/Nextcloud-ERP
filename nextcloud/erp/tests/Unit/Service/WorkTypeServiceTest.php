<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\WorkTypeMapper;
use OCA\ERP\Service\WorkTypeService;
use OCP\IDBConnection;
use Test\TestCase;

/**
 * @group DB
 */
final class WorkTypeServiceTest extends TestCase {
	private WorkTypeService $service;
	private WorkTypeMapper $mapper;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->mapper = new WorkTypeMapper($db);
		$this->service = new WorkTypeService($this->mapper);
	}

	protected function tearDown(): void {
		foreach ($this->mapper->findAll() as $wt) {
			if (str_starts_with($wt->getName(), 'phpunit-')) {
				$this->mapper->delete($wt);
			}
		}
		parent::tearDown();
	}

	public function testCreateAndUpdate(): void {
		$workType = $this->service->create('phpunit-monteur', 55.5, null, true);
		$this->assertSame(55.5, $workType->getHourlyRate());

		$updated = $this->service->update($workType->getId(), 'phpunit-monteur', 60.0, null, true);
		$this->assertSame(60.0, $updated->getHourlyRate());
	}

	public function testUpdateUnknownThrows(): void {
		$this->expectException(\OutOfBoundsException::class);
		$this->service->update(999999999, 'x', 1.0, null, true);
	}
}
