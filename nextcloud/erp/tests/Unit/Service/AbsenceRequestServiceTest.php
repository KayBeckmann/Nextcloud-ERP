<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\AbsenceRequestMapper;
use OCA\ERP\Db\AbsenceTypeMapper;
use OCA\ERP\Db\CalendarLinkMapper;
use OCA\ERP\Service\AbsenceRequestService;
use OCA\ERP\Service\AbsenceTypeService;
use OCA\ERP\Service\CalendarService;
use OCP\Calendar\IManager as ICalendarManager;
use OCP\IDBConnection;
use OCP\IUserManager;
use Test\TestCase;

/**
 * @group DB
 */
final class AbsenceRequestServiceTest extends TestCase {
	// Kein echter Nextcloud-User: tryCreateCalendarEvent() bricht dadurch
	// früh ab (IUserManager::get() liefert null) — die Kalenderverknüpfung
	// selbst wird end-to-end via curl gegen den echten 'kay'-Testuser
	// verifiziert (siehe Doku/status.md), hier geht es um die
	// Status-Workflow-Logik des Service.
	private const NON_EXISTENT_USER = 'phpunit-absence-user';

	private AbsenceRequestService $service;
	private AbsenceRequestMapper $mapper;
	private AbsenceTypeService $absenceTypeService;
	private AbsenceTypeMapper $absenceTypeMapper;
	private int $absenceTypeId;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->mapper = new AbsenceRequestMapper($db);
		$this->absenceTypeMapper = new AbsenceTypeMapper($db);
		$this->absenceTypeService = new AbsenceTypeService($this->absenceTypeMapper);
		$calendarService = new CalendarService(new CalendarLinkMapper($db), \OC::$server->get(ICalendarManager::class));
		$this->service = new AbsenceRequestService($this->mapper, $this->absenceTypeMapper, $calendarService, \OC::$server->get(IUserManager::class));

		$this->absenceTypeId = $this->absenceTypeService->create('phpunit-absence-type', true)->getId();
	}

	protected function tearDown(): void {
		foreach ($this->mapper->findByUser(self::NON_EXISTENT_USER) as $request) {
			$this->mapper->delete($request);
		}
		$this->absenceTypeMapper->delete($this->absenceTypeMapper->findById($this->absenceTypeId));
		parent::tearDown();
	}

	public function testCreateWithUnknownAbsenceTypeThrows(): void {
		$this->expectException(\OutOfBoundsException::class);
		$this->service->create(self::NON_EXISTENT_USER, 999999999, '2026-08-24', '2026-08-28', null);
	}

	public function testFullApprovalWorkflow(): void {
		$request = $this->service->create(self::NON_EXISTENT_USER, $this->absenceTypeId, '2026-08-24', '2026-08-28', 'Urlaub');
		$this->assertSame('requested', $request->getStatus());

		// Fehlender echter Nextcloud-User darf die Genehmigung nicht zum
		// Scheitern bringen — der Kalenderteil ist explizit optional.
		$approved = $this->service->approve($request->getId());
		$this->assertSame('approved', $approved->getStatus());
	}

	public function testApproveAlreadyApprovedRequestThrows(): void {
		$request = $this->service->create(self::NON_EXISTENT_USER, $this->absenceTypeId, '2026-08-24', '2026-08-28', null);
		$this->service->approve($request->getId());

		$this->expectException(\DomainException::class);
		$this->service->approve($request->getId());
	}

	public function testRejectRequestedRequest(): void {
		$request = $this->service->create(self::NON_EXISTENT_USER, $this->absenceTypeId, '2026-08-24', '2026-08-28', null);
		$rejected = $this->service->reject($request->getId());
		$this->assertSame('rejected', $rejected->getStatus());
	}

	public function testRejectAlreadyRejectedRequestThrows(): void {
		$request = $this->service->create(self::NON_EXISTENT_USER, $this->absenceTypeId, '2026-08-24', '2026-08-28', null);
		$this->service->reject($request->getId());

		$this->expectException(\DomainException::class);
		$this->service->reject($request->getId());
	}
}
