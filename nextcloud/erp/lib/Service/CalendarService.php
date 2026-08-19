<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use DateTimeInterface;
use OCA\ERP\Db\CalendarLink;
use OCA\ERP\Db\CalendarLinkMapper;
use OCP\Calendar\ICreateFromString;
use OCP\Calendar\IManager as ICalendarManager;
use OCP\IUser;

/**
 * Wrapper um OCP\Calendar\IManager (ADR-0009). Termine werden im Kalender des
 * anlegenden Users erzeugt; die Verknüpfung zu einem ERP-Datensatz ist
 * generisch (resourceType/resourceId), weil es in Phase 3 noch keine
 * Projekt-/Auftragsentität gibt — Phase 4 nutzt dieselbe Tabelle.
 */
class CalendarService {
	public function __construct(
		private CalendarLinkMapper $mapper,
		private ICalendarManager $calendarManager,
	) {
	}

	private function principalUri(IUser $user): string {
		return 'principals/users/' . $user->getUID();
	}

	/** @return list<array{uri: string, displayName: string, writable: bool}> */
	public function listCalendars(IUser $user): array {
		$calendars = $this->calendarManager->getCalendarsForPrincipal($this->principalUri($user));
		return array_map(static fn ($calendar) => [
			'uri' => $calendar->getUri(),
			'displayName' => $calendar->getDisplayName() ?? $calendar->getUri(),
			'writable' => $calendar instanceof ICreateFromString,
		], $calendars);
	}

	/**
	 * @throws \OutOfBoundsException wenn der Kalender nicht existiert
	 * @throws \InvalidArgumentException wenn der Kalender nicht beschreibbar ist
	 */
	private function findWritableCalendar(IUser $user, string $calendarUri): ICreateFromString {
		foreach ($this->calendarManager->getCalendarsForPrincipal($this->principalUri($user)) as $calendar) {
			if ($calendar->getUri() !== $calendarUri) {
				continue;
			}
			if (!$calendar instanceof ICreateFromString) {
				throw new \InvalidArgumentException("Calendar '$calendarUri' is not writable");
			}
			return $calendar;
		}
		throw new \OutOfBoundsException("Calendar '$calendarUri' not found");
	}

	/**
	 * @throws \InvalidArgumentException|\OutOfBoundsException
	 */
	public function createEvent(
		IUser $user,
		string $calendarUri,
		string $resourceType,
		string $resourceId,
		string $summary,
		DateTimeInterface $start,
		DateTimeInterface $end,
		?string $description = null,
	): CalendarLink {
		$calendar = $this->findWritableCalendar($user, $calendarUri);

		$builder = $this->calendarManager->createEventBuilder()
			->setStartDate($start)
			->setEndDate($end)
			->setSummary($summary);
		if ($description !== null && $description !== '') {
			$builder->setDescription($description);
		}
		// Die Event-UID wird von Nextcloud intern erzeugt; createInCalendar()
		// liefert stattdessen den Dateinamen zurück, den ICalendar::search()
		// über die 'uri'-Option wiederfindet — deshalb wird der als
		// event_uri gespeichert (siehe ADR-0009).
		$eventUri = $builder->createInCalendar($calendar);

		$link = new CalendarLink();
		$link->setResourceType($resourceType);
		$link->setResourceId($resourceId);
		$link->setCalendarUri($calendarUri);
		$link->setEventUri($eventUri);
		$link->setSummary($summary);
		$link->setCreatedAt(time());
		return $this->mapper->insert($link);
	}

	/** @return CalendarLink[] */
	public function listLinks(string $resourceType, string $resourceId): array {
		return $this->mapper->findByResource($resourceType, $resourceId);
	}
}
