<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\AbsenceRequestMapper;
use OCA\ERP\Db\AbsenceTypeMapper;
use OCA\ERP\Db\VacationEntitlementMapper;
use OCA\ERP\Service\AbsenceRequestService;
use OCA\ERP\Service\AbsenceTypeService;
use OCA\ERP\Service\CalendarProvisioningService;
use OCA\ERP\Service\CalendarService;
use OCA\ERP\Service\VacationBalanceService;
use OCA\ERP\Service\VacationEntitlementService;
use OCP\Calendar\IManager as ICalendarManager;
use OCP\IDBConnection;
use OCP\IUserManager;
use Test\TestCase;

/**
 * @group DB
 */
final class VacationBalanceServiceTest extends TestCase {
	private const TEST_UID = 'phpunit-vacation-balance-user';

	private VacationBalanceService $service;
	private VacationEntitlementService $entitlementService;
	private VacationEntitlementMapper $entitlementMapper;
	private AbsenceRequestService $absenceRequestService;
	private AbsenceRequestMapper $absenceRequestMapper;
	private AbsenceTypeService $absenceTypeService;
	private AbsenceTypeMapper $absenceTypeMapper;
	private int $vacationTypeId;
	private int $nonVacationTypeId;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->entitlementMapper = new VacationEntitlementMapper($db);
		$this->entitlementService = new VacationEntitlementService($this->entitlementMapper);
		$this->absenceRequestMapper = new AbsenceRequestMapper($db);
		$this->absenceTypeMapper = new AbsenceTypeMapper($db);
		$this->absenceTypeService = new AbsenceTypeService($this->absenceTypeMapper);
		$calendarService = new CalendarService(
			new \OCA\ERP\Db\CalendarLinkMapper($db),
			\OC::$server->get(ICalendarManager::class),
			\OC::$server->get(CalendarProvisioningService::class),
			\OC::$server->get(\OCA\DAV\CalDAV\CalDavBackend::class),
		);
		$this->absenceRequestService = new AbsenceRequestService($this->absenceRequestMapper, $this->absenceTypeMapper, $calendarService, \OC::$server->get(IUserManager::class));
		$this->service = new VacationBalanceService($this->entitlementService, $this->absenceRequestMapper, $this->absenceTypeMapper);

		$this->vacationTypeId = $this->absenceTypeService->create('phpunit-vacation-balance-type', true)->getId();
		$this->nonVacationTypeId = $this->absenceTypeService->create('phpunit-vacation-balance-sick-type', false)->getId();
	}

	protected function tearDown(): void {
		foreach ($this->absenceRequestMapper->findByUser(self::TEST_UID) as $request) {
			$this->absenceRequestMapper->delete($request);
		}
		$entitlement = $this->entitlementMapper->findByUser(self::TEST_UID);
		if ($entitlement !== null) {
			$this->entitlementMapper->delete($entitlement);
		}
		$this->absenceTypeMapper->delete($this->absenceTypeMapper->findById($this->vacationTypeId));
		$this->absenceTypeMapper->delete($this->absenceTypeMapper->findById($this->nonVacationTypeId));
		parent::tearDown();
	}

	public function testBalanceWithoutRequestsEqualsFullEntitlement(): void {
		$this->entitlementService->setForUser(self::TEST_UID, 24.0);

		$balance = $this->service->getForUser(self::TEST_UID, 2026);

		$this->assertSame(24.0, $balance['entitlementDaysPerYear']);
		$this->assertSame(0, $balance['usedDays']);
		$this->assertSame(24.0, $balance['remainingDays']);
	}

	public function testApprovedVacationRequestReducesRemainingDays(): void {
		$this->entitlementService->setForUser(self::TEST_UID, 24.0);
		$request = $this->absenceRequestService->create(self::TEST_UID, $this->vacationTypeId, '2026-08-17', '2026-08-21', null);
		$this->absenceRequestService->approve($request->getId());

		$balance = $this->service->getForUser(self::TEST_UID, 2026);

		$this->assertSame(5, $balance['usedDays']);
		$this->assertSame(19.0, $balance['remainingDays']);
	}

	public function testOnlyRequestedStatusDoesNotCountAsUsed(): void {
		$this->entitlementService->setForUser(self::TEST_UID, 24.0);
		$this->absenceRequestService->create(self::TEST_UID, $this->vacationTypeId, '2026-08-17', '2026-08-21', null);

		$balance = $this->service->getForUser(self::TEST_UID, 2026);

		$this->assertSame(0, $balance['usedDays']);
	}

	public function testNonVacationAbsenceTypeDoesNotCountAsUsed(): void {
		$this->entitlementService->setForUser(self::TEST_UID, 24.0);
		$request = $this->absenceRequestService->create(self::TEST_UID, $this->nonVacationTypeId, '2026-08-17', '2026-08-21', null);
		$this->absenceRequestService->approve($request->getId());

		$balance = $this->service->getForUser(self::TEST_UID, 2026);

		$this->assertSame(0, $balance['usedDays']);
	}

	public function testRequestOutsideQueriedYearDoesNotCount(): void {
		$this->entitlementService->setForUser(self::TEST_UID, 24.0);
		$request = $this->absenceRequestService->create(self::TEST_UID, $this->vacationTypeId, '2025-08-18', '2025-08-22', null);
		$this->absenceRequestService->approve($request->getId());

		$balance = $this->service->getForUser(self::TEST_UID, 2026);

		$this->assertSame(0, $balance['usedDays']);
	}

	public function testWithoutExplicitEntitlementUsesStatutoryDefault(): void {
		$balance = $this->service->getForUser(self::TEST_UID, 2026);
		$this->assertSame(VacationEntitlementService::DEFAULT_DAYS_PER_YEAR, $balance['entitlementDaysPerYear']);
	}
}
