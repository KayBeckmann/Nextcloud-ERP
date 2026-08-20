<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getProductId()
 * @method void setProductId(int $productId)
 * @method int getWorkTypeId()
 * @method void setWorkTypeId(int $workTypeId)
 * @method float getHours()
 * @method void setHours(float $hours)
 */
class ProductLabor extends Entity implements \JsonSerializable {
	protected int $productId = 0;
	protected int $workTypeId = 0;
	protected float $hours = 0.0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('productId', 'integer');
		$this->addType('workTypeId', 'integer');
		$this->addType('hours', 'float');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'productId' => $this->getProductId(),
			'workTypeId' => $this->getWorkTypeId(),
			'hours' => $this->getHours(),
		];
	}
}
