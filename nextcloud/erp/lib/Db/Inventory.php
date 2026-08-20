<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getWarehouseId()
 * @method void setWarehouseId(int $warehouseId)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method int getStartedAt()
 * @method void setStartedAt(int $startedAt)
 * @method int|null getClosedAt()
 * @method void setClosedAt(?int $closedAt)
 * @method string|null getNotes()
 * @method void setNotes(?string $notes)
 * @method string getCreatedBy()
 * @method void setCreatedBy(string $createdBy)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class Inventory extends Entity implements \JsonSerializable {
	protected int $warehouseId = 0;
	protected string $status = 'open';
	protected int $startedAt = 0;
	protected ?int $closedAt = null;
	protected ?string $notes = null;
	protected string $createdBy = '';
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('warehouseId', 'integer');
		$this->addType('startedAt', 'integer');
		$this->addType('closedAt', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'warehouseId' => $this->getWarehouseId(),
			'status' => $this->getStatus(),
			'startedAt' => $this->getStartedAt(),
			'closedAt' => $this->getClosedAt(),
			'notes' => $this->getNotes(),
			'createdBy' => $this->getCreatedBy(),
		];
	}
}
