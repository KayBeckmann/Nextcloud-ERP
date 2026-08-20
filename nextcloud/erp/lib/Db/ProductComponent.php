<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getProductId()
 * @method void setProductId(int $productId)
 * @method int getArticleId()
 * @method void setArticleId(int $articleId)
 * @method float getQuantity()
 * @method void setQuantity(float $quantity)
 * @method string getUnit()
 * @method void setUnit(string $unit)
 */
class ProductComponent extends Entity implements \JsonSerializable {
	protected int $productId = 0;
	protected int $articleId = 0;
	protected float $quantity = 1.0;
	protected string $unit = 'Stk';

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('productId', 'integer');
		$this->addType('articleId', 'integer');
		$this->addType('quantity', 'float');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'productId' => $this->getProductId(),
			'articleId' => $this->getArticleId(),
			'quantity' => $this->getQuantity(),
			'unit' => $this->getUnit(),
		];
	}
}
