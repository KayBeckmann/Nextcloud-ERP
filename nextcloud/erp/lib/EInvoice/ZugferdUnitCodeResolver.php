<?php

declare(strict_types=1);

namespace OCA\ERP\EInvoice;

use horstoeko\zugferd\codelists\ZugferdUnitCodes;

/**
 * Bildet die in diesem Projekt frei als Text erfassten Einheiten
 * (`QuotePosition::unit` etc., z. B. "Stk", "Std", "psch.") auf die von
 * EN16931/XRechnung geforderten UN/ECE-Rec.-20-Einheitencodes ab
 * (ADR-0040). Reine, DB-freie Zuordnung — analog zu den anderen kleinen
 * Calculator-Klassen dieses Projekts (z. B. TimeAccountCalculator).
 *
 * **Bewusst nur eine kleine, auf die in diesem Projekt tatsächlich
 * vorkommenden deutschen Alltagsbegriffe zugeschnittene Zuordnung** —
 * keine vollständige Rec.-20-Abdeckung. Unbekannte/custom Einheiten
 * fallen auf den generischen Code "C62" (Stück/Einheit) zurück, statt
 * die XML-Erzeugung abzulehnen.
 */
final class ZugferdUnitCodeResolver {
	private const MAP = [
		'stk' => ZugferdUnitCodes::REC20_PIECE,
		'stk.' => ZugferdUnitCodes::REC20_PIECE,
		'stück' => ZugferdUnitCodes::REC20_PIECE,
		'stueck' => ZugferdUnitCodes::REC20_PIECE,
		'std' => ZugferdUnitCodes::REC20_HOUR,
		'std.' => ZugferdUnitCodes::REC20_HOUR,
		'stunde' => ZugferdUnitCodes::REC20_HOUR,
		'stunden' => ZugferdUnitCodes::REC20_HOUR,
		'h' => ZugferdUnitCodes::REC20_HOUR,
		'kg' => ZugferdUnitCodes::REC20_KILOGRAM,
		'm' => ZugferdUnitCodes::REC20_METRE,
		'm²' => ZugferdUnitCodes::REC20_SQUARE_METRE,
		'qm' => ZugferdUnitCodes::REC20_SQUARE_METRE,
		'km' => ZugferdUnitCodes::REC20_KILOMETRE,
		'l' => ZugferdUnitCodes::REC20_LITRE,
		'psch' => ZugferdUnitCodes::REC20_LUMP_SUM,
		'psch.' => ZugferdUnitCodes::REC20_LUMP_SUM,
		'pauschal' => ZugferdUnitCodes::REC20_LUMP_SUM,
		'tag' => ZugferdUnitCodes::REC20_DAY,
		'tage' => ZugferdUnitCodes::REC20_DAY,
		'monat' => ZugferdUnitCodes::REC20_MONTH,
		'monate' => ZugferdUnitCodes::REC20_MONTH,
	];

	public static function resolve(string $unit): string {
		return self::MAP[mb_strtolower(trim($unit))] ?? ZugferdUnitCodes::REC20_ONE;
	}
}
