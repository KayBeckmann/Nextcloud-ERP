<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getContractId()
 * @method void setContractId(int $contractId)
 * @method int getWorkTypeId()
 * @method void setWorkTypeId(int $workTypeId)
 * @method string|null getPrincipalType()
 * @method void setPrincipalType(?string $principalType)
 * @method string|null getPrincipalId()
 * @method void setPrincipalId(?string $principalId)
 * @method float getRate()
 * @method void setRate(float $rate)
 */
class CustomerContractRate extends Entity implements \JsonSerializable {
	protected int $contractId = 0;
	protected int $workTypeId = 0;
	protected ?string $principalType = null;
	protected ?string $principalId = null;
	protected float $rate = 0.0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('contractId', 'integer');
		$this->addType('workTypeId', 'integer');
		$this->addType('rate', 'float');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'contractId' => $this->getContractId(),
			'workTypeId' => $this->getWorkTypeId(),
			'principalType' => $this->getPrincipalType(),
			'principalId' => $this->getPrincipalId(),
			'rate' => $this->getRate(),
		];
	}
}
