<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\ArticleMapper;
use OCA\ERP\Db\ArticleSupplierPriceMapper;
use OCA\ERP\Db\StockLevel;
use OCA\ERP\Db\StockLevelMapper;
use OCA\ERP\Db\WarehouseMapper;
use OCA\ERP\Warehouse\PurchaseSuggestionCalculator;
use OCA\ERP\Warehouse\StockCalculator;

/**
 * Bestellvorschläge (Roadmap Phase 8, ADR-0014) — reiner Lesevorgang, nie
 * gespeichert. Jede Abfrage berechnet live, welche Artikel/Lagerort-
 * Kombinationen unter ihrem Mindestbestand liegen.
 */
class PurchaseSuggestionService {
	public function __construct(
		private StockLevelMapper $levelMapper,
		private ArticleMapper $articleMapper,
		private ArticleSupplierPriceMapper $supplierPriceMapper,
		private WarehouseMapper $warehouseMapper,
	) {
	}

	/** @return list<array> */
	public function suggestions(?int $warehouseId = null): array {
		$levels = $warehouseId !== null ? $this->levelMapper->findByWarehouse($warehouseId) : $this->levelMapper->findAll();

		$result = [];
		foreach ($levels as $level) {
			if (!StockCalculator::needsReorder($level->getQuantityOnHand(), $level->getQuantityReserved(), $level->getMinQuantity())) {
				continue;
			}
			$result[] = $this->buildSuggestion($level);
		}
		return $result;
	}

	private function buildSuggestion(StockLevel $level): array {
		$article = $this->articleMapper->findById($level->getArticleId());
		$warehouse = $this->warehouseMapper->findById($level->getWarehouseId());
		$supplierPrices = $this->supplierPriceMapper->findByArticle($level->getArticleId());

		return [
			'articleId' => $level->getArticleId(),
			'articleName' => $article?->getName() ?? "Artikel #{$level->getArticleId()}",
			'warehouseId' => $level->getWarehouseId(),
			'warehouseName' => $warehouse?->getName() ?? "Lager #{$level->getWarehouseId()}",
			'quantityOnHand' => $level->getQuantityOnHand(),
			'minQuantity' => $level->getMinQuantity(),
			'sollQuantity' => StockCalculator::sollQuantity($level->getQuantityOnHand(), $level->getQuantityReserved()),
			'suggestedQuantity' => PurchaseSuggestionCalculator::suggestedQuantity($level->getQuantityOnHand(), $level->getMinQuantity()),
			// erp_article_supplier_prices ist bereits nach purchase_price ASC
			// sortiert (siehe ArticleSupplierPriceMapper, ADR-0011) — der
			// erste Eintrag ist der günstigste.
			'supplierOptions' => array_map(static fn ($p) => [
				'supplierContactUid' => $p->getSupplierContactUid(),
				'purchasePrice' => $p->getPurchasePrice(),
				'currency' => $p->getCurrency(),
				'minOrderQuantity' => $p->getMinOrderQuantity(),
				'deliveryTime' => $p->getDeliveryTime(),
			], $supplierPrices),
		];
	}
}
