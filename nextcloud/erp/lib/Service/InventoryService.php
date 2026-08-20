<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\Inventory;
use OCA\ERP\Db\InventoryCount;
use OCA\ERP\Db\InventoryCountMapper;
use OCA\ERP\Db\InventoryMapper;
use OCA\ERP\Db\StockLevelMapper;

/**
 * Inventurablauf (Roadmap Phase 8, ADR-0014). Eine Zählung snapshotet den
 * erwarteten Bestand zum Zählzeitpunkt (nicht zum Inventurstart) —
 * vermeidet verfälschte Differenzen bei parallelen Bewegungen während
 * einer länger laufenden Inventur.
 */
class InventoryService {
	public function __construct(
		private InventoryMapper $mapper,
		private InventoryCountMapper $countMapper,
		private StockLevelMapper $levelMapper,
		private StockService $stockService,
	) {
	}

	/** @return Inventory[] */
	public function listForWarehouse(int $warehouseId): array {
		return $this->mapper->findByWarehouse($warehouseId);
	}

	/** @throws \OutOfBoundsException */
	public function get(int $id): Inventory {
		$inventory = $this->mapper->findById($id);
		if ($inventory === null) {
			throw new \OutOfBoundsException("Inventory $id not found");
		}
		return $inventory;
	}

	public function getFull(int $id): array {
		$inventory = $this->get($id);
		return [
			...$inventory->jsonSerialize(),
			'counts' => $this->countMapper->findByInventory($id),
		];
	}

	public function start(int $warehouseId, string $userId, ?string $notes): Inventory {
		$now = time();
		$inventory = new Inventory();
		$inventory->setWarehouseId($warehouseId);
		$inventory->setStatus('open');
		$inventory->setStartedAt($now);
		$inventory->setNotes($notes);
		$inventory->setCreatedBy($userId);
		$inventory->setCreatedAt($now);
		$inventory->setUpdatedAt($now);
		return $this->mapper->insert($inventory);
	}

	/**
	 * Erfasst/aktualisiert die Zählung für einen Artikel. expected_quantity
	 * wird bei jedem Aufruf frisch aus dem aktuellen Bestand übernommen.
	 *
	 * @throws \OutOfBoundsException
	 * @throws \DomainException wenn die Inventur nicht mehr offen ist
	 */
	public function recordCount(int $inventoryId, int $articleId, float $countedQuantity): InventoryCount {
		$inventory = $this->get($inventoryId);
		if ($inventory->getStatus() !== 'open') {
			throw new \DomainException("Inventory $inventoryId is not in status 'open'");
		}

		$level = $this->levelMapper->findOne($articleId, $inventory->getWarehouseId());
		$expected = $level?->getQuantityOnHand() ?? 0.0;

		$existing = $this->countMapper->findOne($inventoryId, $articleId);
		if ($existing !== null) {
			$existing->setCountedQuantity($countedQuantity);
			$existing->setExpectedQuantity($expected);
			return $this->countMapper->update($existing);
		}

		$count = new InventoryCount();
		$count->setInventoryId($inventoryId);
		$count->setArticleId($articleId);
		$count->setCountedQuantity($countedQuantity);
		$count->setExpectedQuantity($expected);
		$count->setCreatedAt(time());
		return $this->countMapper->insert($count);
	}

	/**
	 * Schließt die Inventur ab und bucht für jede Zählung mit Differenz ≠ 0
	 * eine Korrekturbewegung (`inventory_correction`), die den Bestand auf
	 * die gezählte Menge bringt.
	 *
	 * @throws \OutOfBoundsException
	 * @throws \DomainException wenn die Inventur nicht mehr offen ist
	 */
	public function close(int $id, string $userId): Inventory {
		$inventory = $this->get($id);
		if ($inventory->getStatus() !== 'open') {
			throw new \DomainException("Inventory $id is not in status 'open'");
		}

		foreach ($this->countMapper->findByInventory($id) as $count) {
			$diff = round($count->getCountedQuantity() - $count->getExpectedQuantity(), 2);
			if ($diff === 0.0) {
				continue;
			}
			$this->stockService->recordMovement(
				$count->getArticleId(),
				$inventory->getWarehouseId(),
				$diff,
				'inventory_correction',
				'inventory',
				$id,
				$userId,
				'Inventurkorrektur',
			);
		}

		$inventory->setStatus('closed');
		$inventory->setClosedAt(time());
		$inventory->setUpdatedAt(time());
		return $this->mapper->update($inventory);
	}
}
