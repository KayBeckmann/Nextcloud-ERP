<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\DAV\CalDAV\CalDavBackend;
use OCA\DAV\CalDAV\Sharing\Service as CalendarSharingService;
use OCA\DAV\DAV\Sharing\Backend as SharingBackend;

/**
 * Legt pro User einen dedizierten "ERP"-Kalender an (statt des generischen
 * "Personal"-Kalenders) und gibt ihn automatisch an die Gruppe
 * `erp-projektleiter` frei (ADR-0024) — löst den in ADR-0009 offen
 * gelassenen Punkt "Verknüpfung mit fremden Benutzerkalendern bleibt eine
 * offene Frage". Bewusst nicht mit `erp-monteure` geteilt: ein Monteur muss
 * nicht die Termine aller anderen Monteure sehen, nur die Projektleitung
 * braucht die Gesamtübersicht für die Personalplanung (ADR-0020).
 *
 * **Technische Abweichung von der OCP-only-Regel aus ADR-0009:**
 * `OCP\Calendar\IManager` bietet keine öffentliche API zum Anlegen oder
 * Freigeben von Kalendern (nur zum Anlegen von Terminen in bereits
 * existierenden Kalendern über `createEventBuilder()`). Diese Klasse nutzt
 * deshalb bewusst die internen (nicht `OCP`-)Klassen
 * `OCA\DAV\CalDAV\CalDavBackend` und `OCA\DAV\CalDAV\Sharing\Service` —
 * dieselben, die auch Nextclouds eigene Kalender-App intern für Sharing
 * verwendet. Siehe ADR-0024 für die vollständige Begründung.
 */
class CalendarProvisioningService {
	/**
	 * Bewusst nicht 'personal' — das ist Nextclouds generischer
	 * Standardkalender für private Termine, den soll die ERP-App nicht
	 * mitbenutzen/überschreiben.
	 */
	private const ERP_CALENDAR_URI = 'erp';
	private const ERP_CALENDAR_DISPLAY_NAME = 'ERP';
	private const SHARE_WITH_GROUP = 'erp-projektleiter';

	public function __construct(
		private CalDavBackend $calDavBackend,
		private CalendarSharingService $sharingService,
	) {
	}

	/**
	 * Stellt sicher, dass der User einen "ERP"-Kalender hat und dieser mit
	 * {@see self::SHARE_WITH_GROUP} geteilt ist — idempotent, sicher
	 * wiederholt aufrufbar. Gibt die URI des Kalenders zurück (für
	 * `OCP\Calendar\IManager::getCalendarsForPrincipal()`/
	 * `ICreateFromString`-Auflösung durch den Aufrufer).
	 */
	public function ensureErpCalendarUri(string $userId): string {
		$principalUri = 'principals/users/' . $userId;

		$existing = $this->calDavBackend->getCalendarByUri($principalUri, self::ERP_CALENDAR_URI);
		if ($existing !== null) {
			$calendarId = (int) $existing['id'];
		} else {
			$calendarId = (int) $this->calDavBackend->createCalendar(
				$principalUri,
				self::ERP_CALENDAR_URI,
				['{DAV:}displayname' => self::ERP_CALENDAR_DISPLAY_NAME],
			);
		}

		// shareWith() räumt intern bereits bestehende Shares desselben
		// Principals ab und legt sie neu an (siehe SharingService::shareWith)
		// — dieser Aufruf ist also ohne vorherigen "ist schon geteilt?"-Check
		// gefahrlos wiederholbar.
		$this->sharingService->shareWith(
			$calendarId,
			'principal:principals/groups/' . self::SHARE_WITH_GROUP,
			SharingBackend::ACCESS_READ_WRITE,
		);

		return self::ERP_CALENDAR_URI;
	}
}
