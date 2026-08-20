<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getWorkTypeId()
 * @method void setWorkTypeId(int $workTypeId)
 * @method string|null getPrincipalType()
 * @method void setPrincipalType(?string $principalType)
 * @method string|null getPrincipalId()
 * @method void setPrincipalId(?string $principalId)
 * @method float getRate()
 * @method void setRate(float $rate)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class StandardRate extends Entity implements \JsonSerializable {
	protected int $workTypeId = 0;
	// Kein Default außer null: 'user'/'group' ist ein echter Wert, ein
	// Default hier würde denselben Entity-Dirty-Tracking-Fallstrick aus
	// ADR-0011/Phase 5 riskieren (siehe Commit-Historie QuotePosition).
	protected ?string $principalType = null;
	protected ?string $principalId = null;
	protected float $rate = 0.0;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('workTypeId', 'integer');
		$this->addType('rate', 'float');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'workTypeId' => $this->getWorkTypeId(),
			'principalType' => $this->getPrincipalType(),
			'principalId' => $this->getPrincipalId(),
			'rate' => $this->getRate(),
		];
	}
}
