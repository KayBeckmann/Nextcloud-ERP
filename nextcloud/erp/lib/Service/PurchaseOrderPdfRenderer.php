<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\PurchaseOrder;

/** Fixed, server-owned HTML layout for supplier purchase-order PDFs. */
class PurchaseOrderPdfRenderer {
	public function __construct(private DocumentHtmlBuilder $htmlBuilder) {
	}

	/** @param list<array<string,mixed>> $positions */
	public function render(PurchaseOrder $order, array $positions, string $snapshot): string {
		$number = sprintf('PO-%05d', $order->getId());
		$html = $this->htmlBuilder->header('Bestellung', $number, 'Bestellung', $order->getCreatedAt(), null, $order->getSupplierContactUid(), 'purchase_order', null, $snapshot);
		$html .= '<p><strong>Lieferantenreferenz:</strong> ' . $this->escape($order->getSupplierReference() ?? '—') . '</p>';
		$html .= '<table border="1" cellspacing="0" cellpadding="4" width="100%"><thead><tr><th>Position</th><th>Artikel-Nr.</th><th>Menge</th><th>EP</th><th>Gesamt</th><th>Zuordnung</th></tr></thead><tbody>';
		$totals = [];
		foreach ($positions as $position) {
			$quantity = (float) $position['quantityOrdered'];
			$price = (float) $position['unitPurchasePrice'];
			$currency = $this->currency($position['currency'] ?? 'EUR');
			$total = round($quantity * $price, 2);
			$totals[$currency] = ($totals[$currency] ?? 0.0) + $total;
			$references = [];
			if (($position['projectId'] ?? null) !== null) $references[] = 'Projekt #' . (int) $position['projectId'];
			if (($position['warehouseId'] ?? null) !== null) $references[] = 'Lager #' . (int) $position['warehouseId'];
			$html .= '<tr><td>' . $this->escape((string) $position['description']) . '</td><td>' . $this->escape((string) ($position['supplierArticleNo'] ?? '—')) . '</td><td>' . $this->number($quantity) . ' ' . $this->escape((string) $position['unit']) . '</td><td>' . $this->money($price, $currency) . '</td><td>' . $this->money($total, $currency) . '</td><td>' . $this->escape($references === [] ? '—' : implode(' · ', $references)) . '</td></tr>';
		}
		$html .= '</tbody></table><div class="summary"><h3>Bestellsumme</h3>';
		foreach ($totals as $currency => $total) $html .= '<p><strong>' . $this->money($total, $currency) . '</strong></p>';
		$html .= '</div>';
		$html .= $this->htmlBuilder->footer('purchase_order', $number, 'Bestellung', $order->getCreatedAt(), null, $order->getSupplierContactUid(), null, $snapshot);
		return $this->htmlBuilder->wrap($number, $html);
	}

	private function escape(string $value): string {
		return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	private function currency(mixed $currency): string {
		$value = strtoupper((string) $currency);
		return preg_match('/^[A-Z]{3}$/', $value) === 1 ? $value : 'EUR';
	}

	private function number(float $value): string {
		return number_format($value, 2, ',', '.');
	}

	private function money(float $value, string $currency): string {
		return $this->number($value) . ' ' . $currency;
	}
}
