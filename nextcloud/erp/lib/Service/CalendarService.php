<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use DateTimeInterface;
use OCA\DAV\CalDAV\CalDavBackend;
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
 * Kollisionserkennung gegen bereits zugewiesene ERP-Termine. Seit ADR-0024
 * landen zugewiesene Termine gezielt im dedizierten, automatisch an
 * `erp-projektleiter` freigegebenen "ERP"-Kalender des Zielusers
 * ({@see CalendarProvisioningService}) statt im generischen "Personal"-
 * Kalender — löst den in ADR-0009 offen gelassenen Punkt zur Sichtbarkeit
 * fremder Terminkalender. Seit ADR-0031 können bereits angelegte Termine
 * auch bearbeitet/gelöscht werden — die öffentliche OCP\Calendar-API bietet
 * dafür keinen Weg (nur `ICreateFromString::createFromString()`, das bei
 * einer bereits existierenden `event_uri` mit einem Conflict fehlschlägt),
 * weshalb dafür bewusst `OCA\DAV\CalDAV\CalDavBackend` genutzt wird —
 * dieselbe interne Klasse, die auch der reguläre CalDAV-PUT/DELETE-Handler
 * von Nextcloud selbst beim Bearbeiten/Löschen eines Termins im
 * Kalender-Client nutzt. Kein Bestandteil der öffentlichen OCP-API, also
 * ohne Abwärtskompatibilitätsgarantie über Nextcloud-Hauptversionen hinweg
 * (siehe ADR-0031 für die Abwägung).
 */
class CalendarService {
	public function __construct(
		private CalendarLinkMapper $mapper,
		private ICalendarManager $calendarManager,
		private CalendarProvisioningService $provisioning,
		private CalDavBackend $calDavBackend,
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
	 * Dedizierter "ERP"-Kalender des zugewiesenen Users (ADR-0024) — wird bei
	 * Bedarf automatisch angelegt und an `erp-projektleiter` freigegeben
	 * ({@see CalendarProvisioningService}), damit die Projektleitung auch
	 * Termine sieht, die sie nicht selbst angelegt hat (Personalplanung,
	 * ADR-0020). Der anlegende User wählt hier bewusst keinen Kalender aus
	 * — er kennt die Kalenderliste des Zielusers nicht.
	 *
	 * @throws \OutOfBoundsException wenn der frisch sichergestellte Kalender
	 *         danach überraschend nicht auffindbar/beschreibbar ist
	 */
	private function findAssigneeCalendar(string $assignedUserId): ICreateFromString {
		$erpCalendarUri = $this->provisioning->ensureErpCalendarUri($assignedUserId);
		foreach ($this->calendarManager->getCalendarsForPrincipal($this->principalUriForUserId($assignedUserId)) as $calendar) {
			if ($calendar->getUri() === $erpCalendarUri && $calendar instanceof ICreateFromString) {
				return $calendar;
			}
		}
		throw new \OutOfBoundsException("ERP calendar for user '$assignedUserId' not found after provisioning");
	}

	/**
	 * @throws \DomainException wenn sich der Zeitraum mit einem bereits
	 *         zugewiesenen ERP-Termin desselben Users überschneidet
	 */
	private function assertNoCollision(string $assignedUserId, DateTimeInterface $start, DateTimeInterface $end, ?int $excludeLinkId = null): void {
		$overlapping = $this->mapper->findOverlapping($assignedUserId, $start->getTimestamp(), $end->getTimestamp(), $excludeLinkId);
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

	/**
	 * @return list<array{uri: string, displayName: string, writable: bool}>
	 */
	public function listCalendars(IUser $user): array {
		// Stellt den eigenen "ERP"-Kalender sicher (ADR-0024), damit er auch
		// dann in der Liste auftaucht, wenn der User zuvor noch nie selbst
		// einen Termin angelegt hat (createEvent() ruft das sonst nur für
		// den *zugewiesenen* User auf, nicht für den anlegenden).
		$this->provisioning->ensureErpCalendarUri($user->getUID());
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
		$link->setCreatedByUserId($user->getUID());
		$link->setCreatedAt(time());
		return $this->mapper->insert($link);
	}

	/** @return CalendarLink[] */
	public function listLinks(string $resourceType, string $resourceId): array {
		return $this->mapper->findByResource($resourceType, $resourceId);
	}

	/** @throws \OutOfBoundsException wenn der Termin nicht existiert */
	public function getLink(int $id): CalendarLink {
		$link = $this->mapper->findById($id);
		if ($link === null) {
			throw new \OutOfBoundsException("Calendar link $id not found");
		}
		return $link;
	}

	/**
	 * Besitzer-Principal des Kalenders, in dem der Termin tatsächlich liegt
	 * (ADR-0031) — bei Zuweisung der zugewiesene User (ADR-0020), sonst der
	 * Ersteller (seit ADR-0031 gespeichert).
	 *
	 * @throws \OutOfBoundsException wenn der Termin vor ADR-0031 angelegt
	 *         wurde und weder zugewiesen noch der Ersteller bekannt ist
	 */
	private function ownerUserId(CalendarLink $link): string {
		$owner = $link->getAssignedUserId() ?? $link->getCreatedByUserId();
		if ($owner === null) {
			throw new \OutOfBoundsException("Calendar link {$link->getId()} predates ADR-0031 and has no known owner — cannot be edited or deleted");
		}
		return $owner;
	}

	/**
	 * @throws \OutOfBoundsException wenn der Termin, sein Besitzer (siehe
	 *         ownerUserId()) oder sein Kalender nicht (mehr) auffindbar ist
	 */
	private function calendarRowForLink(CalendarLink $link): array {
		$calendarRow = $this->calDavBackend->getCalendarByUri($this->principalUriForUserId($this->ownerUserId($link)), $link->getCalendarUri());
		if ($calendarRow === null) {
			throw new \OutOfBoundsException("Calendar '{$link->getCalendarUri()}' for calendar link {$link->getId()} not found");
		}
		return $calendarRow;
	}

	/**
	 * Bearbeitet/verschiebt einen bereits angelegten ERP-Termin (ADR-0031,
	 * siehe Klassen-Doc für die Begründung, warum das über CalDavBackend
	 * statt die öffentliche OCP\Calendar-API läuft).
	 *
	 * @throws \OutOfBoundsException siehe calendarRowForLink()
	 * @throws \DomainException wenn die neue Zeit mit einem anderen
	 *         ERP-Termin desselben zugewiesenen Users kollidiert (ADR-0020)
	 */
	public function updateEvent(int $id, string $summary, DateTimeInterface $start, DateTimeInterface $end, ?string $description = null): CalendarLink {
		$link = $this->getLink($id);
		if ($link->getAssignedUserId() !== null) {
			$this->assertNoCollision($link->getAssignedUserId(), $start, $end, $id);
		}
		$calendarRow = $this->calendarRowForLink($link);

		$builder = $this->calendarManager->createEventBuilder()
			->setStartDate($start)
			->setEndDate($end)
			->setSummary($summary);
		if ($description !== null && $description !== '') {
			$builder->setDescription($description);
		}
		// toIcs() statt createInCalendar(), weil wir das Schreiben selbst
		// über CalDavBackend::updateCalendarObject() steuern — die neue
		// ICS-Nachricht bekommt dabei zwangsläufig eine neue interne
		// VEVENT-UID (ICalendarEventBuilder hat keinen UID-Setter); die
		// Datei-URI (und damit die ERP-Verknüpfung) bleibt aber stabil, nur
		// die UID in den Kalenderdaten ändert sich mit — siehe ADR-0031
		// "Nicht Teil dieser Phase" für die Einordnung dieser Einschränkung.
		$calendarData = $builder->toIcs();

		$this->calDavBackend->updateCalendarObject($calendarRow['id'], $link->getEventUri(), $calendarData);

		$link->setSummary($summary);
		$link->setStartAt($start->getTimestamp());
		$link->setEndAt($end->getTimestamp());
		return $this->mapper->update($link);
	}

	/**
	 * Löscht einen bereits angelegten ERP-Termin inkl. ERP-Verknüpfung
	 * (ADR-0031, siehe Klassen-Doc).
	 *
	 * @throws \OutOfBoundsException siehe calendarRowForLink()
	 */
	public function deleteEvent(int $id): void {
		$link = $this->getLink($id);
		$calendarRow = $this->calendarRowForLink($link);
		$this->calDavBackend->deleteCalendarObject($calendarRow['id'], $link->getEventUri());
		$this->mapper->delete($link);
	}
}
