<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use DateTimeImmutable;
use OCA\ERP\Db\CalendarLinkMapper;
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

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->mapper = new CalendarLinkMapper($db);

		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')->willReturn('phpunit-cal-user');

		$this->calendarManager = $this->createMock(ICalendarManager::class);
		$this->service = new CalendarService($this->mapper, $this->calendarManager);
	}

	protected function tearDown(): void {
		foreach ($this->mapper->findByResource('phpunit-resource', '1') as $link) {
			$this->mapper->delete($link);
		}
		parent::tearDown();
	}

	private function mockWritableCalendar(string $uri): ICreateFromString&MockObject {
		$calendar = $this->createMock(ICreateFromString::class);
		$calendar->method('getUri')->willReturn($uri);
		$calendar->method('getDisplayName')->willReturn(ucfirst($uri));
		return $calendar;
	}

	public function testListCalendarsReportsWritability(): void {
		$writable = $this->mockWritableCalendar('personal');
		$this->calendarManager->method('getCalendarsForPrincipal')
			->with('principals/users/phpunit-cal-user')
			->willReturn([$writable]);

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
}
