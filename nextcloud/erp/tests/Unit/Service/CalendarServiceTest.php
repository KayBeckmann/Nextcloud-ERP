<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use DateTimeImmutable;
use OCA\DAV\CalDAV\CalDavBackend;
use OCA\ERP\Db\CalendarLinkMapper;
use OCA\ERP\Service\CalendarProvisioningService;
use OCA\ERP\Service\CalendarService;
use OCP\Calendar\ICalendarEventBuilder;
use OCP\Calendar\ICreateFromString;
use OCP\Calendar\IManager as ICalendarManager;
use OCP\IDBConnection;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

/**
 * @group DB
 */
final class CalendarServiceTest extends TestCase {
	private CalendarService $service;
	private CalendarLinkMapper $mapper;
	private IUser $user;
	private ICalendarManager&MockObject $calendarManager;
	private CalendarProvisioningService&MockObject $provisioning;
	private CalDavBackend&MockObject $calDavBackend;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->mapper = new CalendarLinkMapper($db);

		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')->willReturn('phpunit-cal-user');

		$this->calendarManager = $this->createMock(ICalendarManager::class);
		$this->provisioning = $this->createMock(CalendarProvisioningService::class);
		// Realitätsnaher Default (ADR-0024): die URI ist konstant 'erp',
		// unabhängig davon, für welchen User provisioniert wird — einzelne
		// Tests überschreiben das bei Bedarf mit einer abweichenden URI.
		$this->provisioning->method('ensureErpCalendarUri')->willReturn('erp');
		// CalDavBackend (ADR-0031) wird gemockt statt real aufgelöst — wie
		// die anderen Kalender-Abhängigkeiten hier, damit dieser Test ohne
		// echten CalDAV-Unterbau läuft.
		$this->calDavBackend = $this->createMock(CalDavBackend::class);
		$this->service = new CalendarService($this->mapper, $this->calendarManager, $this->provisioning, $this->calDavBackend);
	}

	protected function tearDown(): void {
		foreach ($this->mapper->findByResource('phpunit-resource', '1') as $link) {
			$this->mapper->delete($link);
		}
		foreach (['10', '11', '12', '13'] as $resourceId) {
			foreach ($this->mapper->findByResource('phpunit-resource-assign', $resourceId) as $link) {
				$this->mapper->delete($link);
			}
		}
		foreach (['20', '21', '22', '23'] as $resourceId) {
			foreach ($this->mapper->findByResource('phpunit-resource-edit', $resourceId) as $link) {
				$this->mapper->delete($link);
			}
		}
		parent::tearDown();
	}

	private function mockWritableCalendar(string $uri): ICreateFromString&MockObject {
		$calendar = $this->createMock(ICreateFromString::class);
		$calendar->method('getUri')->willReturn($uri);
		$calendar->method('getDisplayName')->willReturn(ucfirst($uri));
		return $calendar;
	}

	public function testListCalendarsEnsuresErpCalendarAndReportsWritability(): void {
		$writable = $this->mockWritableCalendar('personal');
		$this->calendarManager->method('getCalendarsForPrincipal')
			->with('principals/users/phpunit-cal-user')
			->willReturn([$writable]);

		$this->provisioning->expects($this->once())
			->method('ensureErpCalendarUri')
			->with('phpunit-cal-user');

		$result = $this->service->listCalendars($this->user);
		$this->assertSame([['uri' => 'personal', 'displayName' => 'Personal', 'writable' => true]], $result);
	}

	public function testCreateEventOnUnknownCalendarThrows(): void {
		$this->calendarManager->method('getCalendarsForPrincipal')->willReturn([]);
		$this->expectException(\OutOfBoundsException::class);
		$this->service->createEvent(
			$this->user,
			'nope',
			'phpunit-resource',
			'1',
			'Test',
			new DateTimeImmutable(),
			new DateTimeImmutable(),
		);
	}

	public function testCreateEventPersistsLinkWithReturnedEventUri(): void {
		$calendar = $this->mockWritableCalendar('personal');
		$this->calendarManager->method('getCalendarsForPrincipal')->willReturn([$calendar]);

		$builder = $this->createMock(ICalendarEventBuilder::class);
		$builder->method('setStartDate')->willReturnSelf();
		$builder->method('setEndDate')->willReturnSelf();
		$builder->method('setSummary')->willReturnSelf();
		$builder->method('setDescription')->willReturnSelf();
		$builder->method('createInCalendar')->with($calendar)->willReturn('phpunit-event.ics');
		$this->calendarManager->method('createEventBuilder')->willReturn($builder);

		$link = $this->service->createEvent(
			$this->user,
			'personal',
			'phpunit-resource',
			'1',
			'Testtermin',
			new DateTimeImmutable('2026-09-01T10:00:00'),
			new DateTimeImmutable('2026-09-01T11:00:00'),
			'Beschreibung',
		);

		$this->assertSame('phpunit-event.ics', $link->getEventUri());
		$this->assertCount(1, $this->service->listLinks('phpunit-resource', '1'));
	}

	private function mockEventBuilder(string $eventUri): ICalendarEventBuilder&MockObject {
		$builder = $this->createMock(ICalendarEventBuilder::class);
		$builder->method('setStartDate')->willReturnSelf();
		$builder->method('setEndDate')->willReturnSelf();
		$builder->method('setSummary')->willReturnSelf();
		$builder->method('setDescription')->willReturnSelf();
		$builder->method('createInCalendar')->willReturn($eventUri);
		return $builder;
	}

	public function testCreateEventForAssignedUserUsesTheirProvisionedErpCalendar(): void {
		$assigneeErpCalendar = $this->mockWritableCalendar('erp');
		$this->calendarManager->method('getCalendarsForPrincipal')
			->with('principals/users/mitarbeiter-x')
			->willReturn([$assigneeErpCalendar]);
		$this->calendarManager->method('createEventBuilder')->willReturn($this->mockEventBuilder('assigned-event.ics'));

		$this->provisioning->expects($this->once())
			->method('ensureErpCalendarUri')
			->with('mitarbeiter-x')
			->willReturn('erp');

		$link = $this->service->createEvent(
			$this->user,
			'personal',
			'phpunit-resource-assign',
			'10',
			'Baustelle A',
			new DateTimeImmutable('2026-09-01T08:00:00'),
			new DateTimeImmutable('2026-09-01T12:00:00'),
			null,
			'mitarbeiter-x',
		);

		$this->assertSame('mitarbeiter-x', $link->getAssignedUserId());
		$this->assertSame('erp', $link->getCalendarUri());
	}

	public function testCreateEventForAssignedUserPicksErpCalendarAmongOthers(): void {
		// Der Zielkalender ist gezielt der per URI provisionierte "erp"-
		// Kalender, nicht irgendein anderer beschreibbarer Kalender, den der
		// User zufällig sonst noch hat (ADR-0024 — anders als das alte
		// "erster beschreibbarer Kalender"-Fallback-Verhalten).
		$other = $this->mockWritableCalendar('baustellen');
		$erpCalendar = $this->mockWritableCalendar('erp');
		$this->calendarManager->method('getCalendarsForPrincipal')
			->with('principals/users/mitarbeiter-y')
			->willReturn([$other, $erpCalendar]);
		$this->calendarManager->method('createEventBuilder')->willReturn($this->mockEventBuilder('fallback-event.ics'));

		$link = $this->service->createEvent(
			$this->user,
			'personal',
			'phpunit-resource-assign',
			'11',
			'Baustelle B',
			new DateTimeImmutable('2026-09-02T08:00:00'),
			new DateTimeImmutable('2026-09-02T12:00:00'),
			null,
			'mitarbeiter-y',
		);

		$this->assertSame('erp', $link->getCalendarUri());
	}

	public function testCreateEventForAssignedUserThrowsIfErpCalendarNotFoundAfterProvisioning(): void {
		// Verteidigungs-Fall: ensureErpCalendarUri() lief durch, aber die
		// URI taucht überraschend nicht in getCalendarsForPrincipal() auf.
		$this->calendarManager->method('getCalendarsForPrincipal')->willReturn([]);
		$this->expectException(\OutOfBoundsException::class);
		$this->service->createEvent(
			$this->user,
			'personal',
			'phpunit-resource-assign',
			'12',
			'Baustelle C',
			new DateTimeImmutable('2026-09-03T08:00:00'),
			new DateTimeImmutable('2026-09-03T12:00:00'),
			null,
			'mitarbeiter-z',
		);
	}

	public function testCreateEventRejectsOverlappingAssignmentForSameUser(): void {
		$calendar = $this->mockWritableCalendar('erp');
		$this->calendarManager->method('getCalendarsForPrincipal')->willReturn([$calendar]);
		$this->calendarManager->method('createEventBuilder')->willReturn($this->mockEventBuilder('first-event.ics'));

		$this->service->createEvent(
			$this->user,
			'personal',
			'phpunit-resource-assign',
			'13',
			'Baustelle Vormittag',
			new DateTimeImmutable('2026-09-04T08:00:00'),
			new DateTimeImmutable('2026-09-04T12:00:00'),
			null,
			'mitarbeiter-kollision',
		);

		$this->expectException(\DomainException::class);
		$this->expectExceptionMessageMatches('/Baustelle Vormittag/');
		// Überlappt um eine Stunde (11:00–13:00 vs. bestehendem 08:00–12:00).
		$this->service->createEvent(
			$this->user,
			'personal',
			'phpunit-resource-assign',
			'13',
			'Baustelle Mittag',
			new DateTimeImmutable('2026-09-04T11:00:00'),
			new DateTimeImmutable('2026-09-04T13:00:00'),
			null,
			'mitarbeiter-kollision',
		);
	}

	public function testCreateEventAllowsAdjacentAssignmentsForSameUser(): void {
		$calendar = $this->mockWritableCalendar('erp');
		$this->calendarManager->method('getCalendarsForPrincipal')->willReturn([$calendar]);
		$this->calendarManager->method('createEventBuilder')->willReturn($this->mockEventBuilder('adjacent-event.ics'));

		$this->service->createEvent(
			$this->user,
			'personal',
			'phpunit-resource-assign',
			'13',
			'Baustelle Vormittag',
			new DateTimeImmutable('2026-09-05T08:00:00'),
			new DateTimeImmutable('2026-09-05T12:00:00'),
			null,
			'mitarbeiter-adjazent',
		);

		// Startet exakt, wenn der erste Termin endet — keine Kollision
		// (offenes Intervall, ADR-0020).
		$link = $this->service->createEvent(
			$this->user,
			'personal',
			'phpunit-resource-assign',
			'13',
			'Baustelle Nachmittag',
			new DateTimeImmutable('2026-09-05T12:00:00'),
			new DateTimeImmutable('2026-09-05T16:00:00'),
			null,
			'mitarbeiter-adjazent',
		);

		$this->assertSame('mitarbeiter-adjazent', $link->getAssignedUserId());
	}

	private function mockEventBuilderForUpdate(string $ics): ICalendarEventBuilder&MockObject {
		$builder = $this->createMock(ICalendarEventBuilder::class);
		$builder->method('setStartDate')->willReturnSelf();
		$builder->method('setEndDate')->willReturnSelf();
		$builder->method('setSummary')->willReturnSelf();
		$builder->method('setDescription')->willReturnSelf();
		$builder->method('toIcs')->willReturn($ics);
		return $builder;
	}

	public function testUpdateEventRewritesCalendarObjectOfCreatorForUnassignedLink(): void {
		$calendar = $this->mockWritableCalendar('personal');
		$this->calendarManager->method('getCalendarsForPrincipal')->willReturn([$calendar]);
		// Erster Aufruf von createEventBuilder() während createEvent(),
		// zweiter während updateEvent() — willReturnOnConsecutiveCalls()
		// macht die Reihenfolge explizit, statt sich auf die
		// Override-Reihenfolge mehrerer method()-Stubs zu verlassen.
		$this->calendarManager->method('createEventBuilder')->willReturnOnConsecutiveCalls(
			$this->mockEventBuilder('edit-event.ics'),
			$this->mockEventBuilderForUpdate('BEGIN:VCALENDAR...'),
		);

		$link = $this->service->createEvent(
			$this->user,
			'personal',
			'phpunit-resource-edit',
			'20',
			'Alter Titel',
			new DateTimeImmutable('2026-09-10T08:00:00'),
			new DateTimeImmutable('2026-09-10T09:00:00'),
		);

		$this->calDavBackend->method('getCalendarByUri')
			->with('principals/users/phpunit-cal-user', 'personal')
			->willReturn(['id' => 42]);
		$this->calDavBackend->expects($this->once())
			->method('updateCalendarObject')
			->with(42, 'edit-event.ics', 'BEGIN:VCALENDAR...');

		$updated = $this->service->updateEvent(
			$link->getId(),
			'Neuer Titel',
			new DateTimeImmutable('2026-09-10T10:00:00'),
			new DateTimeImmutable('2026-09-10T11:00:00'),
		);

		$this->assertSame('Neuer Titel', $updated->getSummary());
		$this->assertSame((new DateTimeImmutable('2026-09-10T10:00:00'))->getTimestamp(), $updated->getStartAt());
	}

	public function testUpdateEventRejectsCollisionWithOtherAssignedTerm(): void {
		$calendar = $this->mockWritableCalendar('erp');
		$this->calendarManager->method('getCalendarsForPrincipal')->willReturn([$calendar]);
		$this->calendarManager->method('createEventBuilder')->willReturnOnConsecutiveCalls(
			$this->mockEventBuilder('blocker-event.ics'),
			$this->mockEventBuilder('movable-event.ics'),
		);

		$this->service->createEvent(
			$this->user,
			'personal',
			'phpunit-resource-edit',
			'21',
			'Blockierender Termin',
			new DateTimeImmutable('2026-09-11T08:00:00'),
			new DateTimeImmutable('2026-09-11T12:00:00'),
			null,
			'mitarbeiter-update-kollision',
		);

		$movable = $this->service->createEvent(
			$this->user,
			'personal',
			'phpunit-resource-edit',
			'21',
			'Verschiebbarer Termin',
			new DateTimeImmutable('2026-09-12T08:00:00'),
			new DateTimeImmutable('2026-09-12T12:00:00'),
			null,
			'mitarbeiter-update-kollision',
		);

		$this->expectException(\DomainException::class);
		// In den Zeitraum des blockierenden Termins verschieben.
		$this->service->updateEvent(
			$movable->getId(),
			'Verschiebbarer Termin',
			new DateTimeImmutable('2026-09-11T10:00:00'),
			new DateTimeImmutable('2026-09-11T14:00:00'),
		);
	}

	public function testUpdateEventAllowsMovingWithinOwnUnchangedSlot(): void {
		// Verschieben auf denselben Zeitraum, den der Termin selbst schon
		// belegt, darf nicht an der eigenen (jetzt veralteten) DB-Zeile
		// scheitern — findOverlapping() muss den bearbeiteten Termin selbst
		// ausschließen (ADR-0031).
		$calendar = $this->mockWritableCalendar('erp');
		$this->calendarManager->method('getCalendarsForPrincipal')->willReturn([$calendar]);
		$this->calendarManager->method('createEventBuilder')->willReturnOnConsecutiveCalls(
			$this->mockEventBuilder('self-event.ics'),
			$this->mockEventBuilderForUpdate('BEGIN:VCALENDAR...'),
		);

		$link = $this->service->createEvent(
			$this->user,
			'personal',
			'phpunit-resource-edit',
			'22',
			'Eigener Termin',
			new DateTimeImmutable('2026-09-13T08:00:00'),
			new DateTimeImmutable('2026-09-13T12:00:00'),
			null,
			'mitarbeiter-update-selbst',
		);

		$this->calDavBackend->method('getCalendarByUri')->willReturn(['id' => 7]);

		$updated = $this->service->updateEvent(
			$link->getId(),
			'Eigener Termin',
			new DateTimeImmutable('2026-09-13T08:00:00'),
			new DateTimeImmutable('2026-09-13T12:00:00'),
		);

		$this->assertSame($link->getId(), $updated->getId());
	}

	public function testUpdateEventOnLegacyLinkWithoutKnownOwnerThrows(): void {
		// Simuliert eine Zeile aus der Zeit vor ADR-0031: weder
		// assignedUserId noch createdByUserId bekannt.
		$link = new \OCA\ERP\Db\CalendarLink();
		$link->setResourceType('phpunit-resource-edit');
		$link->setResourceId('23');
		$link->setCalendarUri('personal');
		$link->setEventUri('legacy-event.ics');
		$link->setCreatedAt(time());
		$link = $this->mapper->insert($link);

		$this->expectException(\OutOfBoundsException::class);
		$this->service->updateEvent($link->getId(), 'Titel', new DateTimeImmutable(), new DateTimeImmutable('+1 hour'));
	}

	public function testDeleteEventRemovesCalendarObjectAndLink(): void {
		$calendar = $this->mockWritableCalendar('personal');
		$this->calendarManager->method('getCalendarsForPrincipal')->willReturn([$calendar]);
		$this->calendarManager->method('createEventBuilder')->willReturn($this->mockEventBuilder('delete-event.ics'));

		$link = $this->service->createEvent(
			$this->user,
			'personal',
			'phpunit-resource-edit',
			'20',
			'Zu löschen',
			new DateTimeImmutable('2026-09-14T08:00:00'),
			new DateTimeImmutable('2026-09-14T09:00:00'),
		);

		$this->calDavBackend->method('getCalendarByUri')
			->with('principals/users/phpunit-cal-user', 'personal')
			->willReturn(['id' => 42]);
		$this->calDavBackend->expects($this->once())
			->method('deleteCalendarObject')
			->with(42, 'delete-event.ics');

		$this->service->deleteEvent($link->getId());

		$this->expectException(\OutOfBoundsException::class);
		$this->service->getLink($link->getId());
	}
}
