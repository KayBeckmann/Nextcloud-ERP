<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getInvoiceId()
 * @method void setInvoiceId(int $invoiceId)
 * @method int|null getGroupId()
 * @method void setGroupId(?int $groupId)
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
 * @method int|null getOrderPositionId()
 * @method void setOrderPositionId(?int $orderPositionId)
 */
class InvoicePosition extends Entity implements \JsonSerializable {
	protected int $invoiceId = 0;
	// Gruppenzuordnung (Nutzerwunsch 2026-08-21) — bleibt bei Umwandlungen
	// erhalten.
	protected ?int $groupId = null;
	// Kein sinnvoller Default: 'custom' ist ein echter Wert, siehe der
	// identische Fallstrick bei QuotePosition::$positionType (ADR-0011/
	// Phase 5) — Entity-Dirty-Tracking hätte ihn sonst beim Insert
	// verschluckt.
	protected string $positionType = '';
	protected ?int $referenceId = null;
	protected string $description = '';
	protected float $quantity = 1.0;
	protected string $unit = 'Stk';
	protected float $unitPriceNet = 0.0;
	protected float $vatRatePercent = 0.0;
	protected int $positionOrder = 0;
	// Verweis auf die Auftragsposition, aus der diese Rechnungsposition bei
	// einer Umwandlung entstanden ist (ADR-0016) — null bei manuell
	// hinzugefügten Positionen (z. B. Materialabschlag).
	protected ?int $orderPositionId = null;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('invoiceId', 'integer');
		$this->addType('groupId', 'integer');
		$this->addType('referenceId', 'integer');
		$this->addType('quantity', 'float');
		$this->addType('unitPriceNet', 'float');
		$this->addType('vatRatePercent', 'float');
		$this->addType('positionOrder', 'integer');
		$this->addType('orderPositionId', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'invoiceId' => $this->getInvoiceId(),
			'groupId' => $this->getGroupId(),
			'positionType' => $this->getPositionType(),
			'referenceId' => $this->getReferenceId(),
			'description' => $this->getDescription(),
			'quantity' => $this->getQuantity(),
			'unit' => $this->getUnit(),
			'unitPriceNet' => $this->getUnitPriceNet(),
			'vatRatePercent' => $this->getVatRatePercent(),
			'positionOrder' => $this->getPositionOrder(),
			'orderPositionId' => $this->getOrderPositionId(),
			'netTotal' => round($this->getQuantity() * $this->getUnitPriceNet(), 2),
		];
	}
}
