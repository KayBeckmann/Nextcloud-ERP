<?php

declare(strict_types=1);

namespace OCA\ERP\Warehouse;

/**
 * Reine Soll-Berechnung und Nachbestellbedarf (ADR-0014). Keine
 * DB-Zugriffe — die Soll-Menge ist bewusst kein gespeicherter Wert.
 */
final class StockCalculator {
	/** Soll-Menge = Ist-Menge − reservierte Menge. */
	public static function sollQuantity(float $quantityOnHand, float $quantityReserved): float {
		return round($quantityOnHand - $quantityReserved, 2);
	}

	/**
	 * Nachbestellbedarf besteht, wenn Ist- ODER Soll-Menge unter den
	 * Mindestbestand fällt (Brainstorming-Wortlaut, siehe ADR-0014).
	 */
	public static function needsReorder(float $quantityOnHand, float $quantityReserved, float $minQuantity): bool {
		$soll = self::sollQuantity($quantityOnHand, $quantityReserved);
		return $quantityOnHand < $minQuantity || $soll < $minQuantity;
	}
}
