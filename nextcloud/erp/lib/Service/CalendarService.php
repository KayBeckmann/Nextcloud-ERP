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
 * Wrapper um OCP\Calendar\IManager (ADR-0009). Termine werden standardmäßig im
 * Kalender des anlegenden Users erzeugt; die Verknüpfung zu einem
 * ERP-Datensatz ist generisch (resourceType/resourceId), weil es in Phase 3
 * noch keine Projekt-/Auftragsentität gibt — Phase 4 nutzt dieselbe Tabelle.
 * Seit ADR-0020 kann ein Termin stattdessen einem Mitarbeiter zugewiesen
 * werden — er landet dann in dessen eigenem Kalender, inkl.
 * Kollisionserkennung gegen bereits zugewiesene ERP-Termine.
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

	/**
	 * Kalender eines Users rein über die Principal-URI, ohne dessen
	 * IUser-Objekt/Session zu benötigen (ADR-0020) — Grundlage dafür, dass
	 * ein Termin im Kalender eines *fremden* Users angelegt werden kann.
	 */
	private function principalUriForUserId(string $userId): string {
		return 'principals/users/' . $userId;
	}

	/**
	 * Standardkalender ('personal', von Nextcloud für jeden User automatisch
	 * angelegt) des zugewiesenen Users, sonst der erste beschreibbare
	 * Kalender (ADR-0020). Der anlegende User wählt hier bewusst keinen
	 * Kalender aus — er kennt die Kalenderliste des Zielusers nicht.
	 *
	 * @throws \OutOfBoundsException wenn der User keinen beschreibbaren Kalender hat
	 */
	private function findAssigneeCalendar(string $assignedUserId): ICreateFromString {
		$personal = null;
		$firstWritable = null;
		foreach ($this->calendarManager->getCalendarsForPrincipal($this->principalUriForUserId($assignedUserId)) as $calendar) {
			if (!$calendar instanceof ICreateFromString) {
				continue;
			}
			$firstWritable ??= $calendar;
			if ($calendar->getUri() === 'personal') {
				$personal = $calendar;
				break;
			}
		}
		$target = $personal ?? $firstWritable;
		if ($target === null) {
			throw new \OutOfBoundsException("No writable calendar found for user '$assignedUserId'");
		}
		return $target;
	}

	/**
	 * @throws \DomainException wenn sich der Zeitraum mit einem bereits
	 *         zugewiesenen ERP-Termin desselben Users überschneidet
	 */
	private function assertNoCollision(string $assignedUserId, DateTimeInterface $start, DateTimeInterface $end): void {
		$overlapping = $this->mapper->findOverlapping($assignedUserId, $start->getTimestamp(), $end->getTimestamp());
		if ($overlapping === []) {
			return;
		}
		$conflict = $overlapping[0];
		$conflictStart = $conflict->getStartAt() !== null ? date('d.m.Y H:i', $conflict->getStartAt()) : '?';
		$conflictEnd = $conflict->getEndAt() !== null ? date('d.m.Y H:i', $conflict->getEndAt()) : '?';
		throw new \DomainException(sprintf(
			"User '%s' is already assigned to '%s' from %s to %s",
			$assignedUserId,
			$conflict->getSummary() ?? "Termin #{$conflict->getId()}",
			$conflictStart,
			$conflictEnd,
		));
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
	 * @throws \InvalidArgumentException|\OutOfBoundsException wenn der Kalender
	 *         (eigener oder des zugewiesenen Users) nicht nutzbar ist
	 * @throws \DomainException wenn der zugewiesene User im Zeitraum bereits
	 *         einem anderen ERP-Termin zugewiesen ist (ADR-0020)
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
		?string $assignedUserId = null,
	): CalendarLink {
		if ($assignedUserId !== null && $assignedUserId !== '') {
			// Kollisionsprüfung vor jedem Kalender-Backend-Zugriff, damit bei
			// einer Ablehnung kein verwaistes Event angelegt wird.
			$this->assertNoCollision($assignedUserId, $start, $end);
			$calendar = $this->findAssigneeCalendar($assignedUserId);
			$targetCalendarUri = $calendar->getUri();
		} else {
			$assignedUserId = null;
			$calendar = $this->findWritableCalendar($user, $calendarUri);
			$targetCalendarUri = $calendarUri;
		}

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
		$link->setCalendarUri($targetCalendarUri);
		$link->setEventUri($eventUri);
		$link->setSummary($summary);
		$link->setAssignedUserId($assignedUserId);
		$link->setStartAt($start->getTimestamp());
		$link->setEndAt($end->getTimestamp());
		$link->setCreatedAt(time());
		return $this->mapper->insert($link);
	}

	/** @return CalendarLink[] */
	public function listLinks(string $resourceType, string $resourceId): array {
		return $this->mapper->findByResource($resourceType, $resourceId);
	}
}
