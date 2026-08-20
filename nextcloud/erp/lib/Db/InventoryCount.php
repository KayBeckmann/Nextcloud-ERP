<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getInventoryId()
 * @method void setInventoryId(int $inventoryId)
 * @method int getArticleId()
 * @method void setArticleId(int $articleId)
 * @method float getCountedQuantity()
 * @method void setCountedQuantity(float $countedQuantity)
 * @method float getExpectedQuantity()
 * @method void setExpectedQuantity(float $expectedQuantity)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 */
class InventoryCount extends Entity implements \JsonSerializable {
	protected int $inventoryId = 0;
	protected int $articleId = 0;
	protected float $countedQuantity = 0.0;
	protected float $expectedQuantity = 0.0;
	protected int $createdAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('inventoryId', 'integer');
		$this->addType('articleId', 'integer');
		$this->addType('countedQuantity', 'float');
		$this->addType('expectedQuantity', 'float');
		$this->addType('createdAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'inventoryId' => $this->getInventoryId(),
			'articleId' => $this->getArticleId(),
			'countedQuantity' => $this->getCountedQuantity(),
			'expectedQuantity' => $this->getExpectedQuantity(),
			'difference' => round($this->getCountedQuantity() - $this->getExpectedQuantity(), 2),
		];
	}
}
