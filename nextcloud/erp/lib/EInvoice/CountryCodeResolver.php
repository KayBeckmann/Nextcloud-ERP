<?php

declare(strict_types=1);

namespace OCA\ERP\EInvoice;

/**
 * Bildet das in diesem Projekt frei als Text erfasste Land
 * (`CompanyProfile::country`, Kontakt-Adresse aus vCard `ADR`) auf einen
 * zweistelligen ISO-3166-1-Alpha-2-Code ab, wie ihn EN16931/XRechnung
 * zwingend verlangt (ADR-0040).
 *
 * **Bewusst keine vollständige ISO-3166-Zuordnung** — dieses ERP ist auf
 * den deutschen Markt zugeschnitten. Erkennt gängige deutsche/englische
 * Landesnamen sowie bereits korrekte 2-Buchstaben-Codes; alles andere
 * (unbekannt, leer) fällt auf "DE" zurück, statt die XML-Erzeugung
 * abzulehnen. Vor internationalem Einsatz erweitern/verifizieren.
 */
final class CountryCodeResolver {
	private const MAP = [
		'deutschland' => 'DE',
		'germany' => 'DE',
		'österreich' => 'AT',
		'oesterreich' => 'AT',
		'austria' => 'AT',
		'schweiz' => 'CH',
		'switzerland' => 'CH',
		'frankreich' => 'FR',
		'france' => 'FR',
		'italien' => 'IT',
		'italy' => 'IT',
		'niederlande' => 'NL',
		'netherlands' => 'NL',
		'belgien' => 'BE',
		'belgium' => 'BE',
		'luxemburg' => 'LU',
		'luxembourg' => 'LU',
		'polen' => 'PL',
		'poland' => 'PL',
		'spanien' => 'ES',
		'spain' => 'ES',
	];

	public static function resolve(?string $country): string {
		$normalized = trim((string) $country);
		if ($normalized === '') {
			return 'DE';
		}

		if (preg_match('/^[a-zA-Z]{2}$/', $normalized) === 1) {
			return strtoupper($normalized);
		}

		return self::MAP[mb_strtolower($normalized)] ?? 'DE';
	}
}
