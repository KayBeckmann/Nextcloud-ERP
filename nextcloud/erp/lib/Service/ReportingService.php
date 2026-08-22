<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\ArticleSupplierPriceMapper;
use OCA\ERP\Db\InvoicePosition;
use OCA\ERP\Db\TimeEntryMapper;
use OCA\ERP\Db\VehicleFuelLogMapper;
use OCA\ERP\Projects\ProjectStatus;
use OCA\ERP\Reporting\ProjectProfitLossCalculationService;
use OCA\ERP\Warehouse\StockCalculator;

/**
 * Auswertungen, Dashboard, Exporte (Roadmap Phase 11, ADR-0019). Reiner
 * Lesevorgang, kein eigenes Datenmodell — aggregiert aus den bestehenden
 * Services/Mappern der Phasen 4–10. Die Gewinn/Verlust-Rechnung selbst
 * delegiert an ProjectProfitLossCalculationService (pure, DB-frei).
 */
class ReportingService {
	// Angebote gelten als "offen", solange noch keine Entscheidung
	// gefallen ist (weder angenommen noch abgelehnt/abgelaufen).
	private const OPEN_QUOTE_STATUSES = ['draft', 'sent'];
	// Rechnungen gelten als "offen", solange sie ausgestellt aber noch
	// nicht vollständig bezahlt sind (siehe InvoiceService::isOverdue()).
	private const OPEN_INVOICE_STATUSES = ['issued', 'partially_paid'];
	private const VEHICLE_DUE_SOON_DAYS = 30;

	public function __construct(
		private QuoteService $quoteService,
		private OrderService $orderService,
		private InvoiceService $invoiceService,
		private ProjectService $projectService,
		private StockService $stockService,
		private PurchaseSuggestionService $purchaseSuggestionService,
		private VehicleService $vehicleService,
		private VehicleFuelLogMapper $fuelLogMapper,
		private CostService $costService,
		private TimeAccountService $timeAccountService,
		private TimeEntryMapper $timeEntryMapper,
		private ArticleSupplierPriceMapper $supplierPriceMapper,
		private AbsenceRequestService $absenceRequestService,
		private OvertimeActionService $overtimeActionService,
	) {
	}

	/**
	 * Alle Dashboard-Kacheln aus DashboardView.vue (seit Phase 1
	 * spezifiziert) in einem Aufruf. Die Zeitkonto-Kachel zeigt bewusst
	 * nur die Daten des übergebenen Users (ADR-0019, kein firmenweites
	 * Zeitkonto über dieses Gate).
	 */
	public function dashboardSummary(string $userId): array {
		$openQuotes = array_values(array_filter(
			$this->quoteService->listQuotes(),
			static fn ($q) => in_array($q->getStatus(), self::OPEN_QUOTE_STATUSES, true),
		));
		$openQuotesNet = 0.0;
		foreach ($openQuotes as $quote) {
			$openQuotesNet += $this->quoteService->getFullQuote($quote->getId())['calculation']['netSubtotal'];
		}

		$openInvoices = array_values(array_filter(
			$this->invoiceService->listInvoices(),
			static fn ($i) => in_array($i->getStatus(), self::OPEN_INVOICE_STATUSES, true),
		));
		$openInvoicesGross = 0.0;
		$overdueCount = 0;
		$overdueGross = 0.0;
		foreach ($openInvoices as $invoice) {
			$full = $this->invoiceService->getFullInvoice($invoice->getId());
			$openInvoicesGross += $full['calculation']['grossTotal'];
			if ($full['isOverdue']) {
				$overdueCount++;
				$overdueGross += $full['calculation']['grossTotal'];
			}
		}

		$lowStockCount = 0;
		foreach ($this->stockService->listAllLevels() as $level) {
			if (StockCalculator::needsReorder($level->getQuantityOnHand(), $level->getQuantityReserved(), $level->getMinQuantity())) {
				$lowStockCount++;
			}
		}

		$today = new \DateTimeImmutable();
		$dueSoonCutoff = $today->modify('+' . self::VEHICLE_DUE_SOON_DAYS . ' days')->format('Y-m-d');
		$vehiclesDueSoon = 0;
		$fuelCostsThisMonth = 0.0;
		$monthStart = $today->format('Y-m-01');
		foreach ($this->vehicleService->listAll() as $vehicle) {
			$due = $vehicle->getNextInspectionDate();
			if ($due !== null && $due <= $dueSoonCutoff) {
				$vehiclesDueSoon++;
			}
			foreach ($this->fuelLogMapper->findByVehicle($vehicle->getId()) as $log) {
				if ($log->getEntryDate() >= $monthStart) {
					$fuelCostsThisMonth += $log->getAmount();
				}
			}
		}

		$currentYear = (int)$today->format('Y');
		$internalHourlyRate = $this->costService->getYearOverview($currentYear)['internalHourlyRate'];

		$timeAccount = $this->timeAccountService->getAccount($userId, $monthStart, $today->format('Y-m-d'));
		$ownPendingRequests = count(array_filter(
			$this->absenceRequestService->listForUser($userId),
			static fn ($r) => $r->getStatus() === 'requested',
		)) + count(array_filter(
			$this->overtimeActionService->listForUser($userId),
			static fn ($a) => $a->getStatus() === 'requested',
		));

		return [
			'openQuotes' => ['count' => count($openQuotes), 'netTotal' => round($openQuotesNet, 2)],
			'openInvoices' => [
				'count' => count($openInvoices),
				'grossTotal' => round($openInvoicesGross, 2),
				'overdueCount' => $overdueCount,
				'overdueGrossTotal' => round($overdueGross, 2),
			],
			'projectsInProgress' => count($this->projectService->listProjects(ProjectStatus::InProgress)),
			'lowStockCount' => $lowStockCount,
			'purchaseSuggestionCount' => count($this->purchaseSuggestionService->suggestions()),
			'vehiclesDueSoon' => $vehiclesDueSoon,
			'fuelCostsThisMonth' => round($fuelCostsThisMonth, 2),
			'internalHourlyRate' => $internalHourlyRate,
			'timeAccount' => $timeAccount,
			'ownPendingRequests' => $ownPendingRequests,
		];
	}

	/**
	 * Soll/Ist-Vergleich und Gewinn/Verlust eines Projekts (ADR-0019).
	 * Approximationen: Materialkosten nutzen den aktuell günstigsten
	 * Einkaufspreis (keine historische Preis-Momentaufnahme), Personal-
	 * kosten nutzen den internen Stundensatz des jeweiligen
	 * Erfassungsjahres. Siehe ADR-0019 für die Begründung.
	 *
	 * @throws \OutOfBoundsException wenn das Projekt nicht existiert
	 */
	public function projectProfitLoss(int $projectId): array {
		$this->projectService->getProject($projectId);

		$sollNet = $this->resolveSollNet($projectId);

		$invoices = array_filter(
			$this->invoiceService->listInvoices(null, $projectId),
			static fn ($i) => $i->getIssuedAt() !== null,
		);
		$invoicedNet = 0.0;
		$materialCost = 0.0;
		foreach ($invoices as $invoice) {
			$full = $this->invoiceService->getFullInvoice($invoice->getId());
			$invoicedNet += $full['calculation']['netSubtotal'];
			foreach ($full['positions'] as $position) {
				$materialCost += $this->materialCostForPosition($position);
			}
		}

		$laborCost = $this->laborCostForProject($projectId);

		return [
			'projectId' => $projectId,
			...ProjectProfitLossCalculationService::calculate($sollNet, $invoicedNet, $laborCost, $materialCost),
		];
	}

	private function resolveSollNet(int $projectId): ?float {
		$orders = $this->orderService->listOrders($projectId);
		if ($orders !== []) {
			// OrderMapper::findByProject sortiert bereits nach created_at DESC.
			return $this->orderService->getFullOrder($orders[0]->getId())['calculation']['netSubtotal'];
		}

		$sentQuotes = array_values(array_filter(
			$this->quoteService->listQuotes(null, $projectId),
			static fn ($q) => $q->getSentAt() !== null,
		));
		if ($sentQuotes !== []) {
			// QuoteMapper::findAll sortiert bereits nach created_at DESC.
			return $this->quoteService->getFullQuote($sentQuotes[0]->getId())['calculation']['netSubtotal'];
		}

		return null;
	}

	private function materialCostForPosition(InvoicePosition $position): float {
		if ($position->getPositionType() !== 'article' || $position->getReferenceId() === null) {
			// product/labor/custom-Positionen haben keine (product) bzw.
			// keine sinnvolle (labor/custom) Einkaufspreis-Beziehung —
			// dokumentierte Einschränkung, siehe ADR-0019.
			return 0.0;
		}
		$prices = $this->supplierPriceMapper->findByArticle($position->getReferenceId());
		if ($prices === []) {
			return 0.0;
		}
		$cheapest = min(array_map(static fn ($p) => $p->getPurchasePrice(), $prices));
		return $cheapest * $position->getQuantity();
	}

	private function laborCostForProject(int $projectId): float {
		$hoursByYear = [];
		foreach ($this->timeEntryMapper->findByProject($projectId) as $entry) {
			$year = (int)substr($entry->getEntryDate(), 0, 4);
			$hoursByYear[$year] = ($hoursByYear[$year] ?? 0.0) + $entry->getDurationMinutes() / 60;
		}

		$laborCost = 0.0;
		foreach ($hoursByYear as $year => $hours) {
			$rate = $this->costService->getYearOverview($year)['internalHourlyRate'];
			$laborCost += $hours * $rate;
		}
		return $laborCost;
	}

	/**
	 * CSV für Steuerberater/Buchhaltung (ADR-0019) — nur ausgestellte
	 * Rechnungen (Entwürfe haben keine Rechnungsnummer). Semikolon als
	 * Trenner statt Komma, damit die Datei in deutscher Excel-Locale
	 * (Komma = Dezimaltrennzeichen) korrekt in Spalten fällt.
	 */
	public function exportInvoicesCsv(?string $from, ?string $to, ?string $status): string {
		$handle = fopen('php://temp', 'r+');
		fputcsv($handle, ['Rechnungsnummer', 'Datum', 'Kunde', 'Netto', 'MwSt', 'Brutto', 'Status', 'Bezahlt'], ';');

		foreach ($this->invoiceService->listInvoices($status, null) as $invoice) {
			if ($invoice->getInvoiceNumber() === null || $invoice->getIssuedAt() === null) {
				continue;
			}
			$issuedDate = date('Y-m-d', $invoice->getIssuedAt());
			if (($from !== null && $issuedDate < $from) || ($to !== null && $issuedDate > $to)) {
				continue;
			}

			$full = $this->invoiceService->getFullInvoice($invoice->getId());
			$net = $full['calculation']['netSubtotal'];
			$gross = $full['calculation']['grossTotal'];
			fputcsv($handle, [
				$invoice->getInvoiceNumber(),
				$issuedDate,
				$invoice->getCustomerContactUid() ?? '',
				number_format($net, 2, ',', ''),
				number_format($gross - $net, 2, ',', ''),
				number_format($gross, 2, ',', ''),
				$invoice->getStatus(),
				number_format($invoice->getPaidAmount(), 2, ',', ''),
			], ';');
		}

		rewind($handle);
		$csv = stream_get_contents($handle);
		fclose($handle);
		return $csv;
	}
}
