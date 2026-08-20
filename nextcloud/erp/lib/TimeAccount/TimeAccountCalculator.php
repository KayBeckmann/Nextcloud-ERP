<?php

declare(strict_types=1);

namespace OCA\ERP\TimeAccount;

/**
 * Reine Soll/Ist-Berechnung für das Zeitkonto (ADR-0012). Keine DB-Zugriffe —
 * das Zeitkonto ist bewusst kein gespeicherter Saldo, sondern wird bei jeder
 * Abfrage live aus Arbeitszeitmodell + Zeitbuchungen berechnet.
 */
final class TimeAccountCalculator {
	/**
	 * @param float $weeklyHours Wochensoll aus dem Arbeitszeitmodell des Users.
	 * @param string $fromDate YYYY-MM-DD, inklusive.
	 * @param string $toDate YYYY-MM-DD, inklusive.
	 * @param int $actualMinutes Summe von erp_time_entries.duration_minutes im Zeitraum.
	 * @return array{workdays: int, sollHours: float, istHours: float, balanceHours: float}
	 */
	public static function calculate(float $weeklyHours, string $fromDate, string $toDate, int $actualMinutes): array {
		$workdays = self::countWorkdays($fromDate, $toDate);
		$sollHours = round($weeklyHours / 5 * $workdays, 2);
		$istHours = round($actualMinutes / 60, 2);

		return [
			'workdays' => $workdays,
			'sollHours' => $sollHours,
			'istHours' => $istHours,
			'balanceHours' => round($istHours - $sollHours, 2),
		];
	}

	/** Zählt Werktage (Mo–Fr) im Zeitraum, ohne Feiertagskalender (siehe ADR-0012, "Nicht Teil dieser Phase"). */
	private static function countWorkdays(string $fromDate, string $toDate): int {
		$from = new \DateTimeImmutable($fromDate);
		$to = new \DateTimeImmutable($toDate);
		if ($from > $to) {
			return 0;
		}

		$count = 0;
		$cursor = $from;
		while ($cursor <= $to) {
			$weekday = (int)$cursor->format('N'); // 1 = Montag ... 7 = Sonntag
			if ($weekday <= 5) {
				$count++;
			}
			$cursor = $cursor->modify('+1 day');
		}
		return $count;
	}
}
