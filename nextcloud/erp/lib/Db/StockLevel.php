<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getArticleId()
 * @method void setArticleId(int $articleId)
 * @method int getWarehouseId()
 * @method void setWarehouseId(int $warehouseId)
 * @method float getQuantityOnHand()
 * @method void setQuantityOnHand(float $quantityOnHand)
 * @method float getQuantityReserved()
 * @method void setQuantityReserved(float $quantityReserved)
 * @method float getMinQuantity()
 * @method void setMinQuantity(float $minQuantity)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class StockLevel extends Entity implements \JsonSerializable {
	protected int $articleId = 0;
	protected int $warehouseId = 0;
	protected float $quantityOnHand = 0.0;
	protected float $quantityReserved = 0.0;
	protected float $minQuantity = 0.0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('articleId', 'integer');
		$this->addType('warehouseId', 'integer');
		$this->addType('quantityOnHand', 'float');
		$this->addType('quantityReserved', 'float');
		$this->addType('minQuantity', 'float');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'articleId' => $this->getArticleId(),
			'warehouseId' => $this->getWarehouseId(),
			'quantityOnHand' => $this->getQuantityOnHand(),
			'quantityReserved' => $this->getQuantityReserved(),
			'minQuantity' => $this->getMinQuantity(),
			'sollQuantity' => round($this->getQuantityOnHand() - $this->getQuantityReserved(), 2),
		];
	}
}
