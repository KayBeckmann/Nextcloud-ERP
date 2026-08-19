<?php

declare(strict_types=1);

namespace OCA\ERP\Permissions;

/**
 * Die 16 Hauptbereiche aus der Web-Navigation (siehe src/router/index.js) —
 * bewusst identische Slugs, damit Frontend und Rechte-Matrix nicht
 * auseinanderlaufen.
 */
enum ResourceType: string {
	case Dashboard = 'dashboard';
	case Projekte = 'projekte';
	case KalenderPersonal = 'kalender-personal';
	case Kunden = 'kunden';
	case Lieferanten = 'lieferanten';
	case Artikel = 'artikel';
	case Produkte = 'produkte';
	case Angebote = 'angebote';
	case Auftraege = 'auftraege';
	case Rechnungen = 'rechnungen';
	case Lager = 'lager';
	case Fuhrpark = 'fuhrpark';
	case KostenKalkulation = 'kosten-kalkulation';
	case StundenZeitkonto = 'stunden-zeitkonto';
	case BerechtigungenSaetze = 'berechtigungen-saetze';
	case ApiSync = 'api-sync';
	case Einstellungen = 'einstellungen';

	/** @return list<string> */
	public static function values(): array {
		return array_map(static fn (self $c) => $c->value, self::cases());
	}
}
