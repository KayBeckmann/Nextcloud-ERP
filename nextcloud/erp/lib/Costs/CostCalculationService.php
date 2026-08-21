<?php

declare(strict_types=1);

namespace OCA\ERP\Costs;

/**
 * Reine Kalkulationslogik ohne DB-Abhängigkeit (ADR-0018) — analog zu
 * PermissionResolver (ADR-0008), QuoteCalculationService (ADR-0011) und
 * RateResolutionService (ADR-0012). CostService lädt die Rohdaten, diese
 * Klasse rechnet.
 */
final class CostCalculationService {
	/**
	 * Summe aller erfassten Kostenposten eines Jahres — die Jahressumme
	 * ist damit immer auf die einzelnen Posten zurückführbar (ADR-0018,
	 * Prüfkriterium "nachvollziehbar").
	 *
	 * @param list<array{monthlyAmount: float}> $entries
	 */
	public static function sumAnnualCosts(array $entries): float {
		return round(array_sum(array_map(static fn (array $e) => $e['monthlyAmount'], $entries)), 2);
	}

	/**
	 * Kostenaufschlüsselung je Kategorie — für die Nachvollziehbarkeit im
	 * Web-UI, nicht nur eine Gesamtsumme.
	 *
	 * @param list<array{category: string, monthlyAmount: float}> $entries
	 * @return array<string, float>
	 */
	public static function sumByCategory(array $entries): array {
		$sums = [];
		foreach ($entries as $entry) {
			$sums[$entry['category']] = round(($sums[$entry['category']] ?? 0.0) + $entry['monthlyAmount'], 2);
		}
		return $sums;
	}

	/**
	 * Interner Stundensatz = Jahreskosten / produktive Stunden. Rein
	 * informativ (ADR-0018) — fließt nicht automatisch in
	 * Verrechnungssätze (ADR-0012) ein. Liefert 0.0, wenn keine
	 * produktiven Stunden hinterlegt sind (Division durch 0 vermeiden,
	 * kein Fehlerfall — ein frisches Kalkulationsjahr hat ggf. noch keine
	 * sinnvollen Werte).
	 */
	public static function calculateInternalHourlyRate(float $annualCosts, float $productiveHoursPerYear): float {
		if ($productiveHoursPerYear <= 0.0) {
			return 0.0;
		}
		return round($annualCosts / $productiveHoursPerYear, 2);
	}

	/**
	 * Empfohlener Verkaufspreis = Basiskosten * (1 + Aufschlag%). Reines
	 * Rechenwerkzeug (ADR-0018) — schreibt nichts auf Artikel/Produkte.
	 */
	public static function calculateSurchargedPrice(float $baseCost, float $surchargePercent): float {
		return round($baseCost * (1 + $surchargePercent / 100), 2);
	}
}
