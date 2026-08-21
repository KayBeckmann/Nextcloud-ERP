<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getOrderId()
 * @method void setOrderId(int $orderId)
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
 * @method float getUnitPriceNet()
 * @method void setUnitPriceNet(float $unitPriceNet)
 * @method float getVatRatePercent()
 * @method void setVatRatePercent(float $vatRatePercent)
 * @method int getPositionOrder()
 * @method void setPositionOrder(int $positionOrder)
 */
class OrderPosition extends Entity implements \JsonSerializable {
	protected int $orderId = 0;
	// Kein sinnvoller Default: 'custom' ist ein echter Wert, identischer
	// Fallstrick wie QuotePosition::$positionType (ADR-0011/Phase 5).
	protected string $positionType = '';
	protected ?int $referenceId = null;
	protected string $description = '';
	protected float $quantity = 1.0;
	protected string $unit = 'Stk';
	protected float $unitPriceNet = 0.0;
	protected float $vatRatePercent = 0.0;
	protected int $positionOrder = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('orderId', 'integer');
		$this->addType('referenceId', 'integer');
		$this->addType('quantity', 'float');
		$this->addType('unitPriceNet', 'float');
		$this->addType('vatRatePercent', 'float');
		$this->addType('positionOrder', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'orderId' => $this->getOrderId(),
			'positionType' => $this->getPositionType(),
			'referenceId' => $this->getReferenceId(),
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
