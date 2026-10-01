<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\VacationEntitlementMapper;
use OCA\ERP\Service\VacationEntitlementService;
use OCP\IDBConnection;
use Test\TestCase;

/**
 * @group DB
 */
final class VacationEntitlementServiceTest extends TestCase {
	private const TEST_UID = 'phpunit-vacation-entitlement-user';

	private VacationEntitlementService $service;
	private VacationEntitlementMapper $mapper;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->mapper = new VacationEntitlementMapper($db);
		$this->service = new VacationEntitlementService($this->mapper);
	}

	protected function tearDown(): void {
		$existing = $this->mapper->findByUser(self::TEST_UID);
		if ($existing !== null) {
			$this->mapper->delete($existing);
		}
		parent::tearDown();
	}

	public function testGetForUserWithoutEntitlementReturnsStatutoryMinimumDefault(): void {
		$entitlement = $this->service->getForUser(self::TEST_UID);
		$this->assertSame(VacationEntitlementService::DEFAULT_DAYS_PER_YEAR, $entitlement->getDaysPerYear());
		$this->assertNull($entitlement->getId());
	}

	public function testSetForUserPersistsAndGetForUserReturnsIt(): void {
		$this->service->setForUser(self::TEST_UID, 28.0);
		$entitlement = $this->service->getForUser(self::TEST_UID);

		$this->assertSame(28.0, $entitlement->getDaysPerYear());
		$this->assertNotNull($entitlement->getId());
	}

	public function testSetForUserTwiceUpdatesExistingRowInsteadOfDuplicating(): void {
		$this->service->setForUser(self::TEST_UID, 25.0);
		$first = $this->mapper->findByUser(self::TEST_UID);

		$this->service->setForUser(self::TEST_UID, 30.0);
		$second = $this->mapper->findByUser(self::TEST_UID);

		$this->assertSame($first->getId(), $second->getId());
		$this->assertSame(30.0, $second->getDaysPerYear());
	}
}
