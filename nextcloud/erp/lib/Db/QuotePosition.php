<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getQuoteId()
 * @method void setQuoteId(int $quoteId)
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
 * @method float getDiscountPercent()
 * @method void setDiscountPercent(float $discountPercent)
 */
class QuotePosition extends Entity implements \JsonSerializable {
	protected int $quoteId = 0;
	protected ?int $groupId = null;
	// Bewusst kein sinnvoller Default: Nextclouds Entity-Dirty-Tracking
	// vergleicht setPositionType() gegen den Klassen-Default und überspringt
	// die Spalte beim INSERT, wenn beide gleich sind — 'custom' als Default
	// hätte genau den echten Wert 'custom' beim Anlegen stillschweigend
	// verschluckt (position_type hat absichtlich keinen DB-Default).
	protected string $positionType = '';
	protected ?int $referenceId = null;
	protected string $description = '';
	protected float $quantity = 1.0;
	protected string $unit = 'Stk';
	protected float $unitPriceNet = 0.0;
	protected float $vatRatePercent = 0.0;
	protected int $positionOrder = 0;
	// Rabatt auf diese Position (ADR-0022), wirkt vor der MwSt.-Berechnung.
	protected float $discountPercent = 0.0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('quoteId', 'integer');
		$this->addType('groupId', 'integer');
		$this->addType('referenceId', 'integer');
		$this->addType('quantity', 'float');
		$this->addType('unitPriceNet', 'float');
		$this->addType('vatRatePercent', 'float');
		$this->addType('positionOrder', 'integer');
		$this->addType('discountPercent', 'float');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'quoteId' => $this->getQuoteId(),
			'groupId' => $this->getGroupId(),
			'positionType' => $this->getPositionType(),
			'referenceId' => $this->getReferenceId(),
			'description' => $this->getDescription(),
			'quantity' => $this->getQuantity(),
			'unit' => $this->getUnit(),
			'unitPriceNet' => $this->getUnitPriceNet(),
			'vatRatePercent' => $this->getVatRatePercent(),
			'positionOrder' => $this->getPositionOrder(),
			'discountPercent' => $this->getDiscountPercent(),
			'netTotal' => round($this->getQuantity() * $this->getUnitPriceNet() * (1 - $this->getDiscountPercent() / 100), 2),
		];
	}
}
