<?php

declare(strict_types=1);

namespace OCA\ERP\Rates;

/**
 * Reine 6-stufige Satzpriorisierung ohne DB-Abhängigkeit (ADR-0012) — analog
 * zu PermissionResolver (ADR-0008) und QuoteCalculationService (ADR-0011).
 *
 * $contractRates enthält bereits nur die Sätze des für den Kunden
 * zutreffenden Vertrags (Vertragsauswahl inkl. Gültigkeitszeitraum ist
 * Aufgabe von RateService, nicht dieser Klasse).
 */
final class RateResolutionService {
	/**
	 * @param list<array{workTypeId: int, principalType: ?string, principalId: ?string, rate: float}> $contractRates
	 * @param list<array{workTypeId: int, principalType: ?string, principalId: ?string, rate: float}> $standardRates
	 * @param list<string> $groupIds
	 */
	public static function resolve(
		array $contractRates,
		array $standardRates,
		int $workTypeId,
		string $userId,
		array $groupIds,
		?float $workTypeDefaultRate,
	): float {
		// 1. Kundenvertragssatz für Arbeitsart + konkreten User
		$rate = self::find($contractRates, $workTypeId, 'user', $userId);
		if ($rate !== null) {
			return $rate;
		}

		// 2. Kundenvertragssatz für Arbeitsart + Gruppe
		foreach ($groupIds as $groupId) {
			$rate = self::find($contractRates, $workTypeId, 'group', $groupId);
			if ($rate !== null) {
				return $rate;
			}
		}

		// 3. Standardsatz für Arbeitsart + konkreten User
		$rate = self::find($standardRates, $workTypeId, 'user', $userId);
		if ($rate !== null) {
			return $rate;
		}

		// 4. Standardsatz für Arbeitsart + Gruppe
		foreach ($groupIds as $groupId) {
			$rate = self::find($standardRates, $workTypeId, 'group', $groupId);
			if ($rate !== null) {
				return $rate;
			}
		}

		// 5. Globaler Standardsatz für die Arbeitsart (kein Principal), sonst
		//    der Arbeitsart-Default aus Phase 5 (erp_work_types.hourly_rate).
		$rate = self::find($standardRates, $workTypeId, null, null);
		if ($rate !== null) {
			return $rate;
		}
		if ($workTypeDefaultRate !== null) {
			return $workTypeDefaultRate;
		}

		// 6. Harter Fallback, falls nicht einmal die Arbeitsart einen Satz hat.
		return 0.0;
	}

	/** @param list<array{workTypeId: int, principalType: ?string, principalId: ?string, rate: float}> $rates */
	private static function find(array $rates, int $workTypeId, ?string $principalType, ?string $principalId): ?float {
		foreach ($rates as $r) {
			if ($r['workTypeId'] === $workTypeId
				&& $r['principalType'] === $principalType
				&& $r['principalId'] === $principalId) {
				return $r['rate'];
			}
		}
		return null;
	}
}
