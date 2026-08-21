<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getYear()
 * @method void setYear(int $year)
 * @method float getProductiveHoursPerYear()
 * @method void setProductiveHoursPerYear(float $productiveHoursPerYear)
 * @method float getMaterialSurchargePercent()
 * @method void setMaterialSurchargePercent(float $materialSurchargePercent)
 * @method float getProductSurchargePercent()
 * @method void setProductSurchargePercent(float $productSurchargePercent)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class CostSettings extends Entity implements \JsonSerializable {
	protected int $year = 0;
	protected float $productiveHoursPerYear = 1600.0;
	protected float $materialSurchargePercent = 0.0;
	protected float $productSurchargePercent = 0.0;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('year', 'integer');
		$this->addType('productiveHoursPerYear', 'float');
		$this->addType('materialSurchargePercent', 'float');
		$this->addType('productSurchargePercent', 'float');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'year' => $this->getYear(),
			'productiveHoursPerYear' => $this->getProductiveHoursPerYear(),
			'materialSurchargePercent' => $this->getMaterialSurchargePercent(),
			'productSurchargePercent' => $this->getProductSurchargePercent(),
		];
	}
}
