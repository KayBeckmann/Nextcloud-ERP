<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getArticleId()
 * @method void setArticleId(int $articleId)
 * @method int getWarehouseId()
 * @method void setWarehouseId(int $warehouseId)
 * @method float getQuantityDelta()
 * @method void setQuantityDelta(float $quantityDelta)
 * @method string getMovementType()
 * @method void setMovementType(string $movementType)
 * @method string|null getReferenceType()
 * @method void setReferenceType(?string $referenceType)
 * @method int|null getReferenceId()
 * @method void setReferenceId(?int $referenceId)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string|null getNotes()
 * @method void setNotes(?string $notes)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 */
class StockMovement extends Entity implements \JsonSerializable {
	protected int $articleId = 0;
	protected int $warehouseId = 0;
	protected float $quantityDelta = 0.0;
	// Kein sinnvoller Default: 'consumption' ist ein echter Wert, siehe
	// derselbe Fallstrick bei QuotePosition::$positionType (ADR-0011).
	protected string $movementType = '';
	protected ?string $referenceType = null;
	protected ?int $referenceId = null;
	protected string $userId = '';
	protected ?string $notes = null;
	protected int $createdAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('articleId', 'integer');
		$this->addType('warehouseId', 'integer');
		$this->addType('quantityDelta', 'float');
		$this->addType('referenceId', 'integer');
		$this->addType('createdAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'articleId' => $this->getArticleId(),
			'warehouseId' => $this->getWarehouseId(),
			'quantityDelta' => $this->getQuantityDelta(),
			'movementType' => $this->getMovementType(),
			'referenceType' => $this->getReferenceType(),
			'referenceId' => $this->getReferenceId(),
			'userId' => $this->getUserId(),
			'notes' => $this->getNotes(),
			'createdAt' => $this->getCreatedAt(),
		];
	}
}
