<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getName()
 * @method void setName(string $name)
 * @method float getPercentage()
 * @method void setPercentage(float $percentage)
 * @method bool getIsDefault()
 * @method void setIsDefault(bool $isDefault)
 * @method bool getActive()
 * @method void setActive(bool $active)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class VatRate extends Entity implements \JsonSerializable {
	protected string $name = '';
	protected float $percentage = 0.0;
	protected bool $isDefault = false;
	protected bool $active = true;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('percentage', 'float');
		$this->addType('isDefault', 'boolean');
		$this->addType('active', 'boolean');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'name' => $this->getName(),
			'percentage' => $this->getPercentage(),
			'isDefault' => $this->getIsDefault(),
			'active' => $this->getActive(),
		];
	}
}
