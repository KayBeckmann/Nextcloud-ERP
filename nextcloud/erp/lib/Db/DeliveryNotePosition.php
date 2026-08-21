<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getDeliveryNoteId()
 * @method void setDeliveryNoteId(int $deliveryNoteId)
 * @method string getPositionType()
 * @method void setPositionType(string $positionType)
 * @method int|null getReferenceId()
 * @method void setReferenceId(?int $referenceId)
 * @method string getDescription()
 * @method void setDescription(string $description)
 * @method float getQuantity()
 * @method void setQuantity(float $quantity)
 * @method string getUnit()
 * @method void setUnit(string $unit)
 * @method int getPositionOrder()
 * @method void setPositionOrder(int $positionOrder)
 */
class DeliveryNotePosition extends Entity implements \JsonSerializable {
	protected int $deliveryNoteId = 0;
	// Kein sinnvoller Default: 'custom' ist ein echter Wert, identischer
	// Fallstrick wie QuotePosition::$positionType (ADR-0011/Phase 5).
	protected string $positionType = '';
	protected ?int $referenceId = null;
	protected string $description = '';
	protected float $quantity = 1.0;
	protected string $unit = 'Stk';
	protected int $positionOrder = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('deliveryNoteId', 'integer');
		$this->addType('referenceId', 'integer');
		$this->addType('quantity', 'float');
		$this->addType('positionOrder', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'deliveryNoteId' => $this->getDeliveryNoteId(),
			'positionType' => $this->getPositionType(),
			'referenceId' => $this->getReferenceId(),
			'description' => $this->getDescription(),
			'quantity' => $this->getQuantity(),
			'unit' => $this->getUnit(),
			'positionOrder' => $this->getPositionOrder(),
		];
	}
}
