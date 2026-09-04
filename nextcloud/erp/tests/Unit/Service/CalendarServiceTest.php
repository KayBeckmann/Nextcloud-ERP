<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use DateTimeImmutable;
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
		$this->service = new CalendarService($this->mapper, $this->calendarManager, $this->provisioning);
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
}
