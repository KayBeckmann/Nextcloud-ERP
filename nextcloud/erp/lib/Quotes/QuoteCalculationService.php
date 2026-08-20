<?php

declare(strict_types=1);

namespace OCA\ERP\Quotes;

/**
 * Reine Netto-/MwSt.-Berechnung ohne DB-Abhängigkeit (ADR-0011) — analog zu
 * PermissionResolver (ADR-0008): schnell testbar ohne Server-Bootstrap.
 * QuoteService lädt die Rohdaten, diese Klasse rechnet.
 */
final class QuoteCalculationService {
	/**
	 * @param list<array{id: int, title: string}> $groups
	 * @param list<array{id: int, groupId: ?int, quantity: float, unitPriceNet: float, vatRatePercent: float}> $positions
	 * @return array{
	 *     groups: list<array{groupId: ?int, title: ?string, netTotal: float}>,
	 *     netSubtotal: float,
	 *     vatBreakdown: list<array{ratePercent: float, netBase: float, vatAmount: float}>,
	 *     grossTotal: float
	 * }
	 */
	public static function calculate(array $groups, array $positions): array {
		$groupTitles = [];
		foreach ($groups as $g) {
			$groupTitles[$g['id']] = $g['title'];
		}

		// 0 steht für "ungruppierte Positionen" — echte Gruppen-IDs sind immer > 0.
		$groupTotals = [];
		$vatBase = [];

		foreach ($positions as $p) {
			$netTotal = round($p['quantity'] * $p['unitPriceNet'], 2);
			$groupKey = $p['groupId'] ?? 0;
			$groupTotals[$groupKey] = ($groupTotals[$groupKey] ?? 0.0) + $netTotal;

			$rateKey = number_format($p['vatRatePercent'], 2, '.', '');
			$vatBase[$rateKey] = ($vatBase[$rateKey] ?? 0.0) + $netTotal;
		}

		$groupsOut = [];
		foreach ($groupTotals as $groupId => $total) {
			$groupsOut[] = [
				'groupId' => $groupId === 0 ? null : $groupId,
				'title' => $groupId === 0 ? null : ($groupTitles[$groupId] ?? null),
				'netTotal' => round($total, 2),
			];
		}

		$netSubtotal = round(array_sum($groupTotals), 2);

		$vatBreakdown = [];
		$vatTotal = 0.0;
		foreach ($vatBase as $rateKey => $base) {
			$rate = (float) $rateKey;
			$amount = round($base * $rate / 100, 2);
			$vatTotal += $amount;
			$vatBreakdown[] = ['ratePercent' => $rate, 'netBase' => round($base, 2), 'vatAmount' => $amount];
		}

		return [
			'groups' => $groupsOut,
			'netSubtotal' => $netSubtotal,
			'vatBreakdown' => $vatBreakdown,
			'grossTotal' => round($netSubtotal + $vatTotal, 2),
		];
	}
}
