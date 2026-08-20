<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getName()
 * @method void setName(string $name)
 * @method float getHourlyRate()
 * @method void setHourlyRate(float $hourlyRate)
 * @method int|null getVatRateId()
 * @method void setVatRateId(?int $vatRateId)
 * @method bool getActive()
 * @method void setActive(bool $active)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class WorkType extends Entity implements \JsonSerializable {
	protected string $name = '';
	protected float $hourlyRate = 0.0;
	protected ?int $vatRateId = null;
	protected bool $active = true;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('hourlyRate', 'float');
		$this->addType('vatRateId', 'integer');
		$this->addType('active', 'boolean');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'name' => $this->getName(),
			'hourlyRate' => $this->getHourlyRate(),
			'vatRateId' => $this->getVatRateId(),
			'active' => $this->getActive(),
		];
	}
}
