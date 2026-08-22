<?php

declare(strict_types=1);

namespace OCA\ERP\Reporting;

/**
 * Reine Soll/Ist- und Gewinn/Verlust-Berechnung für ein Projekt
 * (Roadmap Phase 11, ADR-0019). Keine DB-Zugriffe — ReportingService lädt
 * die Rohdaten (Auftrags-/Angebotssumme, Rechnungssummen, Personal- und
 * Materialkosten) und übergibt sie hier zur Berechnung, analog zu
 * CostCalculationService (ADR-0018) und StockCalculator (ADR-0014).
 */
final class ProjectProfitLossCalculationService {
	/**
	 * @param float|null $sollNet Netto-Auftrags-/Angebotssumme, oder null wenn keines existiert
	 *        (bewusst kein 0.0-Platzhalter — "kein Soll erfasst" ist kein "Soll ist 0 €").
	 * @param float $invoicedNet Netto-Summe aller ausgestellten Rechnungen des Projekts.
	 * @param float $laborCost Personalkosten (Zeiterfassung × interner Stundensatz je Jahr).
	 * @param float $materialCost Materialkosten (Artikel-Positionen × günstigster Einkaufspreis, Approximation).
	 * @return array{sollNet: ?float, invoicedNet: float, laborCost: float, materialCost: float, totalCost: float, result: float}
	 */
	public static function calculate(?float $sollNet, float $invoicedNet, float $laborCost, float $materialCost): array {
		$totalCost = round($laborCost + $materialCost, 2);

		return [
			'sollNet' => $sollNet !== null ? round($sollNet, 2) : null,
			'invoicedNet' => round($invoicedNet, 2),
			'laborCost' => round($laborCost, 2),
			'materialCost' => round($materialCost, 2),
			'totalCost' => $totalCost,
			'result' => round($invoicedNet - $totalCost, 2),
		];
	}
}
