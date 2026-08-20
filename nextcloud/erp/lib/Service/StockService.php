<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\StockLevel;
use OCA\ERP\Db\StockLevelMapper;
use OCA\ERP\Db\StockMovement;
use OCA\ERP\Db\StockMovementMapper;

/**
 * Bestandsführung (Roadmap Phase 8, ADR-0014). Jede Mengenänderung läuft
 * über recordMovement() — nie eine direkte Bestandsänderung ohne
 * Bewegungssatz (Audit-Trail).
 */
class StockService {
	private const VALID_MOVEMENT_TYPES = ['receipt', 'consumption', 'adjustment', 'transfer_in', 'transfer_out', 'inventory_correction'];

	public function __construct(
		private StockLevelMapper $levelMapper,
		private StockMovementMapper $movementMapper,
	) {
	}

	/** @return StockLevel[] */
	public function listForWarehouse(int $warehouseId): array {
		return $this->levelMapper->findByWarehouse($warehouseId);
	}

	/** @return StockLevel[] */
	public function listForArticle(int $articleId): array {
		return $this->levelMapper->findByArticle($articleId);
	}

	/** @return StockLevel[] Grundlage für PurchaseSuggestionService. */
	public function listAllLevels(): array {
		return $this->levelMapper->findAll();
	}

	/** @return StockMovement[] */
	public function listMovements(int $articleId, int $warehouseId): array {
		return $this->movementMapper->findByArticleAndWarehouse($articleId, $warehouseId);
	}

	private function getOrCreateLevel(int $articleId, int $warehouseId): StockLevel {
		$level = $this->levelMapper->findOne($articleId, $warehouseId);
		if ($level !== null) {
			return $level;
		}
		$level = new StockLevel();
		$level->setArticleId($articleId);
		$level->setWarehouseId($warehouseId);
		$level->setUpdatedAt(time());
		return $this->levelMapper->insert($level);
	}

	public function setMinQuantity(int $articleId, int $warehouseId, float $minQuantity): StockLevel {
		if ($minQuantity < 0) {
			throw new \InvalidArgumentException('minQuantity must not be negative');
		}
		$level = $this->getOrCreateLevel($articleId, $warehouseId);
		$level->setMinQuantity($minQuantity);
		$level->setUpdatedAt(time());
		return $this->levelMapper->update($level);
	}

	/**
	 * @throws \InvalidArgumentException wenn movementType unbekannt ist
	 * @throws \DomainException wenn die Buchung den Bestand negativ machen würde
	 */
	public function recordMovement(
		int $articleId,
		int $warehouseId,
		float $quantityDelta,
		string $movementType,
		?string $referenceType,
		?int $referenceId,
		string $userId,
		?string $notes,
	): StockMovement {
		if (!in_array($movementType, self::VALID_MOVEMENT_TYPES, true)) {
			throw new \InvalidArgumentException('movementType must be one of: ' . implode(', ', self::VALID_MOVEMENT_TYPES));
		}

		$level = $this->getOrCreateLevel($articleId, $warehouseId);
		$newOnHand = round($level->getQuantityOnHand() + $quantityDelta, 2);
		if ($newOnHand < 0) {
			throw new \DomainException("Movement would result in negative stock for article $articleId in warehouse $warehouseId");
		}
		$level->setQuantityOnHand($newOnHand);
		$level->setUpdatedAt(time());
		$this->levelMapper->update($level);

		$movement = new StockMovement();
		$movement->setArticleId($articleId);
		$movement->setWarehouseId($warehouseId);
		$movement->setQuantityDelta($quantityDelta);
		$movement->setMovementType($movementType);
		$movement->setReferenceType($referenceType);
		$movement->setReferenceId($referenceId);
		$movement->setUserId($userId);
		$movement->setNotes($notes);
		$movement->setCreatedAt(time());
		return $this->movementMapper->insert($movement);
	}

	/**
	 * Verschiebt Bestand zwischen zwei Lagerorten über ein Bewegungspaar
	 * (transfer_out/transfer_in), z. B. Zentrallager -> Baustellenlager.
	 *
	 * @throws \InvalidArgumentException|\DomainException
	 */
	public function transfer(int $articleId, int $fromWarehouseId, int $toWarehouseId, float $quantity, string $userId, ?string $notes): void {
		if ($fromWarehouseId === $toWarehouseId) {
			throw new \InvalidArgumentException('fromWarehouseId and toWarehouseId must differ');
		}
		if ($quantity <= 0) {
			throw new \InvalidArgumentException('quantity must be greater than 0');
		}

		$this->recordMovement($articleId, $fromWarehouseId, -$quantity, 'transfer_out', 'warehouse', $toWarehouseId, $userId, $notes);
		$this->recordMovement($articleId, $toWarehouseId, $quantity, 'transfer_in', 'warehouse', $fromWarehouseId, $userId, $notes);
	}

	/** @throws \InvalidArgumentException */
	public function reserve(int $articleId, int $warehouseId, float $quantity): StockLevel {
		if ($quantity <= 0) {
			throw new \InvalidArgumentException('quantity must be greater than 0');
		}
		$level = $this->getOrCreateLevel($articleId, $warehouseId);
		$level->setQuantityReserved(round($level->getQuantityReserved() + $quantity, 2));
		$level->setUpdatedAt(time());
		return $this->levelMapper->update($level);
	}

	/** @throws \InvalidArgumentException|\DomainException */
	public function release(int $articleId, int $warehouseId, float $quantity): StockLevel {
		if ($quantity <= 0) {
			throw new \InvalidArgumentException('quantity must be greater than 0');
		}
		$level = $this->getOrCreateLevel($articleId, $warehouseId);
		if ($quantity > $level->getQuantityReserved()) {
			throw new \DomainException("Cannot release more than reserved for article $articleId in warehouse $warehouseId");
		}
		$level->setQuantityReserved(round($level->getQuantityReserved() - $quantity, 2));
		$level->setUpdatedAt(time());
		return $this->levelMapper->update($level);
	}
}
