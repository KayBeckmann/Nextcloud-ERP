<?php

declare(strict_types=1);

namespace OCA\ERP\Absence;

use OCA\ERP\TimeAccount\TimeAccountCalculator;

/**
 * Reine Resturlaub-Berechnung (ADR-0033) — keine DB-Zugriffe, analog zu
 * {@see TimeAccountCalculator}. Kein gespeicherter Zähler: der Verbrauch
 * wird bei jeder Abfrage live aus den genehmigten Abwesenheitsanträgen des
 * Jahres berechnet.
 */
final class VacationBalanceCalculator {
	/**
	 * @param float $entitlementDaysPerYear Jahresanspruch (VacationEntitlement).
	 * @param list<array{startDate: string, endDate: string}> $approvedVacationRequests
	 *        genehmigte Anträge eines urlaubswirksamen Abwesenheitstyps
	 *        (AbsenceType::affectsVacationBalance), bereits auf das
	 *        angefragte Jahr eingegrenzt.
	 * @return array{entitlementDaysPerYear: float, usedDays: int, remainingDays: float}
	 */
	public static function calculate(float $entitlementDaysPerYear, array $approvedVacationRequests): array {
		$usedDays = 0;
		foreach ($approvedVacationRequests as $request) {
			$usedDays += TimeAccountCalculator::countWorkdays($request['startDate'], $request['endDate']);
		}

		return [
			'entitlementDaysPerYear' => $entitlementDaysPerYear,
			'usedDays' => $usedDays,
			'remainingDays' => round($entitlementDaysPerYear - $usedDays, 2),
		];
	}
}
