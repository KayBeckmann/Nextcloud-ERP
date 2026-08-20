<?php

declare(strict_types=1);

namespace OCA\ERP\Warehouse;

/**
 * Reine Bestellmengen-Berechnung (ADR-0014, Mindestbestand-basierte Regel).
 * Keine DB-Zugriffe — PurchaseSuggestionService lädt die Rohdaten, diese
 * Klasse rechnet.
 */
final class PurchaseSuggestionCalculator {
	/**
	 * Bestellmenge = Mindestbestand − Ist-Menge, nie negativ. Bringt den
	 * Bestand exakt auf den Mindestbestand zurück (nicht auf einen höheren
	 * Zielbestand — siehe ADR-0014, "Nicht Teil dieser Phase").
	 */
	public static function suggestedQuantity(float $quantityOnHand, float $minQuantity): float {
		return round(max($minQuantity - $quantityOnHand, 0.0), 2);
	}
}
