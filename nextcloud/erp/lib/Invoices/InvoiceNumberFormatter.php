<?php

declare(strict_types=1);

namespace OCA\ERP\Invoices;

/**
 * Reine Formatierung von Rechnungs-/Gutschriftnummern (ADR-0013). Die
 * eigentliche Sequenzvergabe (atomarer Zähler) liegt in InvoiceService/
 * CreditNoteService, weil sie eine DB-Transaktion braucht — diese Klasse
 * kennt nur das Format `{Prefix}-{Jahr}-{Sequenz:05d}`.
 */
final class InvoiceNumberFormatter {
	public const INVOICE_PREFIX = 'R';
	public const CREDIT_NOTE_PREFIX = 'G';

	public static function format(string $prefix, int $year, int $sequence): string {
		return sprintf('%s-%d-%05d', $prefix, $year, $sequence);
	}
}
