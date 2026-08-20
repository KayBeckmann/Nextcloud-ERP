<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getCreditNoteId()
 * @method void setCreditNoteId(int $creditNoteId)
 * @method string getDescription()
 * @method void setDescription(string $description)
 * @method float getQuantity()
 * @method void setQuantity(float $quantity)
 * @method string getUnit()
 * @method void setUnit(string $unit)
 * @method float getUnitPriceNet()
 * @method void setUnitPriceNet(float $unitPriceNet)
 * @method float getVatRatePercent()
 * @method void setVatRatePercent(float $vatRatePercent)
 * @method int getPositionOrder()
 * @method void setPositionOrder(int $positionOrder)
 */
class CreditNotePosition extends Entity implements \JsonSerializable {
	protected int $creditNoteId = 0;
	protected string $description = '';
	protected float $quantity = 1.0;
	protected string $unit = 'Stk';
	protected float $unitPriceNet = 0.0;
	protected float $vatRatePercent = 0.0;
	protected int $positionOrder = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('creditNoteId', 'integer');
		$this->addType('quantity', 'float');
		$this->addType('unitPriceNet', 'float');
		$this->addType('vatRatePercent', 'float');
		$this->addType('positionOrder', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'creditNoteId' => $this->getCreditNoteId(),
			'description' => $this->getDescription(),
			'quantity' => $this->getQuantity(),
			'unit' => $this->getUnit(),
			'unitPriceNet' => $this->getUnitPriceNet(),
			'vatRatePercent' => $this->getVatRatePercent(),
			'positionOrder' => $this->getPositionOrder(),
			'netTotal' => round($this->getQuantity() * $this->getUnitPriceNet(), 2),
		];
	}
}
