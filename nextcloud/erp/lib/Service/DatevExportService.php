<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\Invoice;

/**
 * DATEV-Buchungsstapel-Export (EXTF-Format, Formatversion 700, Daten-
 * kategorie 21 "Buchungsstapel") für ausgestellte Rechnungen — schließt
 * den in `status.md` seit Phase 7 offen dokumentierten Punkt "Kein
 * Steuerberater-Exportformat (z. B. DATEV) implementiert."
 *
 * WICHTIG — vor produktivem Einsatz mit dem Steuerberater abstimmen
 * (siehe ADR-0026): Dieses ERP kennt aktuell keinen eigenen
 * Kontenrahmen/Kontenplan (keine individuellen Debitorenkonten je
 * Kunde, keine frei konfigurierbaren Erlöskonten). Der Export nutzt
 * deshalb pauschale SKR03-Standardkonten mit automatischer
 * USt.-Verbuchung (8400/8300/8120) und ein generisches
 * Debitoren-Sammelkonto. Rechnungen mit einem MwSt.-Satz außerhalb von
 * 19 %/7 %/0 % landen auf einem Platzhalterkonto, der Buchungstext
 * markiert das explizit zur manuellen Prüfung.
 *
 * Spaltenstruktur/Kopfzeilen 1:1 aus einer echten DATEV-EXTF-Beispieldatei
 * übernommen (125 Spalten), nicht aus der Erinnerung rekonstruiert.
 */
class DatevExportService {
	private const DEBTOR_ACCOUNT_DEFAULT = 10000;
	private const FALLBACK_REVENUE_ACCOUNT = 8400;
	private const COLUMN_COUNT = 125;

	public function __construct(private InvoiceService $invoiceService) {
	}

	/**
	 * @throws \InvalidArgumentException bei ungültigen Datums-/Kontenangaben
	 *         (Beraternummer/Mandantennummer sind DATEV-Pflichtfelder ohne
	 *         sinnvollen Default — bewusst keine Platzhalterwerte, die
	 *         unbemerkt in einer echten Kanzlei-Mandantenakte landen
	 *         könnten).
	 */
	public function exportBuchungsstapel(
		string $from,
		string $to,
		int $beraternummer,
		int $mandantennummer,
		string $exportedBy,
		?int $debtorAccount = null,
		?string $wjBeginn = null,
		int $sachkontenlaenge = 4,
	): string {
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
			throw new \InvalidArgumentException('from/to must be ISO dates (YYYY-MM-DD)');
		}
		if ($from > $to) {
			throw new \InvalidArgumentException('from must not be after to');
		}
		if ($beraternummer < 1 || $beraternummer > 9999999) {
			throw new \InvalidArgumentException('beraternummer must be between 1 and 9999999');
		}
		if ($mandantennummer < 1 || $mandantennummer > 99999) {
			throw new \InvalidArgumentException('mandantennummer must be between 1 and 99999');
		}
		if ($wjBeginn !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $wjBeginn)) {
			throw new \InvalidArgumentException('wjBeginn must be an ISO date (YYYY-MM-DD)');
		}
		if ($sachkontenlaenge < 4 || $sachkontenlaenge > 8) {
			throw new \InvalidArgumentException('sachkontenlaenge must be between 4 and 8');
		}
		$debtorAccount ??= self::DEBTOR_ACCOUNT_DEFAULT;
		$wjBeginn ??= substr($from, 0, 4) . '-01-01';

		$lines = [];
		$lines[] = self::csvLine($this->headerRow($from, $to, $beraternummer, $mandantennummer, $exportedBy, $wjBeginn, $sachkontenlaenge));
		$lines[] = self::csvLine(self::columnHeaders());

		foreach ($this->invoiceService->listInvoices(null, null) as $invoice) {
			if ($invoice->getInvoiceNumber() === null || $invoice->getIssuedAt() === null) {
				continue; // Entwürfe: keine Rechnungsnummer/kein Ausstellungsdatum, kein Buchungsbeleg.
			}
			if ($invoice->getStatus() === 'cancelled') {
				continue; // Vollstornierte Rechnungen: die Gutschrift bildet den Gegenwert, kein doppelter Posten.
			}
			$issuedDate = date('Y-m-d', $invoice->getIssuedAt());
			if ($issuedDate < $from || $issuedDate > $to) {
				continue;
			}

			$full = $this->invoiceService->getFullInvoice($invoice->getId());
			foreach ($full['calculation']['vatBreakdown'] as $vatBucket) {
				$gross = round($vatBucket['netBase'] + $vatBucket['vatAmount'], 2);
				if ($gross <= 0) {
					continue;
				}
				$lines[] = self::csvLine($this->bookingRow($invoice, $gross, (float) $vatBucket['ratePercent'], $debtorAccount));
			}
		}

		// Bewusst keine fputcsv()-Automatik: deren eingebaute "Feld braucht
		// Anführungszeichen?"-Heuristik quotet uneinheitlich (z.B. Klammern
		// lösen es aus, ein einzelnes "S" nicht) und ihr Standard-Zeilenende
		// ist "\n" statt des von DATEV erwarteten "\r\n". Stattdessen jede
		// Zeile manuell gebaut (siehe csvLine()): Text immer in
		// Anführungszeichen, Zahlen/Leerfelder nie — exakt das Muster der
		// echten DATEV-EXTF-Beispieldatei, an der dieser Export entwickelt
		// wurde.
		$csv = implode("\r\n", $lines) . "\r\n";
		// DATEV-Altsysteme erwarten klassisch Windows-1252 (ANSI); laut
		// DATEV-Dokumentation akzeptiert DATEV-Rechnungswesen ab der hier
		// gesetzten Formatversion 700 auch UTF-8 mit BOM. Vor dem ersten
		// echten Import beim Steuerberater verifizieren (siehe ADR-0026) —
		// im Zweifel dort gezielt nach dem bevorzugten Zeichensatz fragen.
		return "\u{FEFF}" . $csv;
	}

	/**
	 * Baut eine DATEV-Zeile. Jedes Feld ist entweder ein nackter Wert
	 * (Zahl, Datum, Leerfeld — nie in Anführungszeichen, siehe `raw()`)
	 * oder ein Textfeld (immer in Anführungszeichen). Bewusst NICHT am
	 * PHP-Typ unterschieden (Belegdatum/Umsatz sind selbst als String
	 * unterwegs — "0110" würde als int die führende Null verlieren,
	 * "119,00" ist wegen des Kommas ohnehin kein valider PHP-Zahlentyp) —
	 * stattdessen markiert jeder Aufrufer über `raw()`/`text()` explizit,
	 * welche Quotierung ein Feld braucht, exakt wie im echten
	 * DATEV-EXTF-Beispiel beobachtet.
	 *
	 * @param list<DatevField> $fields
	 */
	private static function csvLine(array $fields): string {
		return implode(';', array_map(static function (DatevField $field): string {
			if ($field->value === '' || !$field->quoted) {
				return $field->value;
			}
			// Enthaltene '"' verdoppelt (Standard-CSV-Escaping, DATEV folgt
			// derselben Konvention).
			return '"' . str_replace('"', '""', $field->value) . '"';
		}, $fields));
	}

	private static function raw(int|string $value): DatevField {
		return new DatevField((string) $value, false);
	}

	private static function text(string $value): DatevField {
		return new DatevField($value, true);
	}

	/** @return list<DatevField> */
	private function headerRow(
		string $from,
		string $to,
		int $beraternummer,
		int $mandantennummer,
		string $exportedBy,
		string $wjBeginn,
		int $sachkontenlaenge,
	): array {
		return [
			self::text('EXTF'), self::raw(700), self::raw(21), self::text('Buchungsstapel'), self::raw(13),
			self::raw(date('YmdHis') . '000'),
			self::raw(''), self::text('EX'), self::text($exportedBy), self::raw(''),
			self::raw($beraternummer), self::raw($mandantennummer),
			self::raw(str_replace('-', '', $wjBeginn)),
			self::raw($sachkontenlaenge),
			self::raw(str_replace('-', '', $from)),
			self::raw(str_replace('-', '', $to)),
			self::text("Rechnungen $from bis $to"),
			self::raw(''), self::raw(1), self::raw(''), self::raw(0), self::text('EUR'),
			self::raw(''), self::raw(''), self::raw(''), self::raw(''), self::raw(''), self::raw(''), self::raw(''), self::raw(''),
		];
	}

	/** @return list<DatevField> Exakt 125 Spalten, 1:1 aus einer echten DATEV-EXTF-Beispieldatei übernommen. */
	private static function columnHeaders(): array {
		return [
			self::text('Umsatz (ohne Soll/Haben-Kz)'), self::text('Soll/Haben-Kennzeichen'), self::text('WKZ Umsatz'),
			self::text('Kurs'), self::text('Basisumsatz'), self::text('WKZ Basisumsatz'),
			self::text('Konto'), self::text('Gegenkonto (ohne BU-Schlüssel)'), self::text('BU-Schlüssel'),
			self::text('Belegdatum'), self::text('Belegfeld 1'), self::text('Belegfeld 2'), self::text('Skonto'), self::text('Buchungstext'),
			...array_fill(0, self::COLUMN_COUNT - 14, self::raw('')),
		];
	}

	/** @return list<DatevField> */
	private function bookingRow(Invoice $invoice, float $gross, float $ratePercent, int $debtorAccount): array {
		$known = self::isStandardRate($ratePercent);
		$revenueAccount = $known ? self::revenueAccountFor($ratePercent) : self::FALLBACK_REVENUE_ACCOUNT;
		$text = trim(($invoice->getCustomerContactUid() ?? '') . ' ' . $invoice->getTitle());
		if (!$known) {
			$text = "PRÜFEN {$ratePercent}%: $text";
		}
		$text = mb_substr($text, 0, 60);

		return [
			self::raw(number_format($gross, 2, ',', '')),
			self::text('S'),
			self::raw(''), self::raw(''), self::raw(''), self::raw(''),
			self::raw($debtorAccount),
			self::raw($revenueAccount),
			self::raw(''),
			self::raw(date('dm', $invoice->getIssuedAt())),
			self::text((string) $invoice->getInvoiceNumber()),
			self::raw(''), self::raw(''),
			self::text($text),
			...array_fill(0, self::COLUMN_COUNT - 14, self::raw('')),
		];
	}

	/**
	 * Ob [ratePercent] einem der drei deutschen Standard-MwSt.-Sätze
	 * entspricht, für die SKR03 ein automatisches USt.-Erlöskonto kennt.
	 * Bewusst mit Toleranz statt exaktem Float-Vergleich/Array-Key (ein
	 * Float als PHP-Array-Key würde stillschweigend auf int gekürzt, z.B.
	 * 19.5 -> 19 -> fälschlich als "Standard 19%" erkannt).
	 */
	private static function isStandardRate(float $ratePercent): bool {
		foreach ([19.0, 7.0, 0.0] as $standard) {
			if (abs($ratePercent - $standard) < 0.01) {
				return true;
			}
		}
		return false;
	}

	private static function revenueAccountFor(float $ratePercent): int {
		return match (true) {
			abs($ratePercent - 19.0) < 0.01 => 8400,
			abs($ratePercent - 7.0) < 0.01 => 8300,
			default => 8120,
		};
	}
}
