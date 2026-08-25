<?php

declare(strict_types=1);

namespace OCA\ERP\Quotes;

/**
 * Reine Netto-/MwSt.-Berechnung ohne DB-Abhängigkeit (ADR-0011) — analog zu
 * PermissionResolver (ADR-0008): schnell testbar ohne Server-Bootstrap.
 * QuoteService lädt die Rohdaten, diese Klasse rechnet. Rabatte pro
 * Position und pro Beleg (ADR-0022) kamen nachträglich dazu.
 */
final class QuoteCalculationService {
	/**
	 * @param list<array{id: int, title: string}> $groups
	 * @param list<array{id: int, groupId: ?int, quantity: float, unitPriceNet: float, vatRatePercent: float, discountPercent?: float}> $positions
	 * @param float $documentDiscountPercent Rabatt auf den gesamten Beleg (0–100), wirkt zusätzlich zum Positionsrabatt.
	 * @return array{
	 *     groups: list<array{groupId: ?int, title: ?string, netTotal: float}>,
	 *     netSubtotalBeforeDiscount: float,
	 *     documentDiscountAmount: float,
	 *     netSubtotal: float,
	 *     vatBreakdown: list<array{ratePercent: float, netBase: float, vatAmount: float}>,
	 *     grossTotal: float
	 * }
	 */
	public static function calculate(array $groups, array $positions, float $documentDiscountPercent = 0.0): array {
		$groupTitles = [];
		foreach ($groups as $g) {
			$groupTitles[$g['id']] = $g['title'];
		}

		// 0 steht für "ungruppierte Positionen" — echte Gruppen-IDs sind immer > 0.
		$groupTotals = [];
		$vatBase = [];

		foreach ($positions as $p) {
			// Positionsrabatt wirkt vor der MwSt.-Berechnung, direkt auf die Zeile.
			$positionDiscount = $p['discountPercent'] ?? 0.0;
			$netTotal = round($p['quantity'] * $p['unitPriceNet'] * (1 - $positionDiscount / 100), 2);
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

		$netSubtotalBeforeDiscount = round(array_sum($groupTotals), 2);

		// Beleg-Rabatt wirkt anteilig je MwSt.-Satz-Bucket, nicht erst auf die
		// fertige Bruttosumme — sonst würde die MwSt.-Aufteilung bei
		// gemischten Steuersätzen nicht mehr zur tatsächlichen
		// Bemessungsgrundlage passen (ADR-0022).
		$discountFactor = 1 - $documentDiscountPercent / 100;
		$vatBreakdown = [];
		$vatTotal = 0.0;
		$netSubtotal = 0.0;
		foreach ($vatBase as $rateKey => $base) {
			$discountedBase = round($base * $discountFactor, 2);
			$netSubtotal += $discountedBase;
			$rate = (float) $rateKey;
			$amount = round($discountedBase * $rate / 100, 2);
			$vatTotal += $amount;
			$vatBreakdown[] = ['ratePercent' => $rate, 'netBase' => $discountedBase, 'vatAmount' => $amount];
		}
		$netSubtotal = round($netSubtotal, 2);

		return [
			'groups' => $groupsOut,
			'netSubtotalBeforeDiscount' => $netSubtotalBeforeDiscount,
			'documentDiscountAmount' => round($netSubtotalBeforeDiscount - $netSubtotal, 2),
			'netSubtotal' => $netSubtotal,
			'vatBreakdown' => $vatBreakdown,
			'grossTotal' => round($netSubtotal + $vatTotal, 2),
		];
	}
}
