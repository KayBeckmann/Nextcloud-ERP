<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getVehicleId()
 * @method void setVehicleId(int $vehicleId)
 * @method string getEntryDate()
 * @method void setEntryDate(string $entryDate)
 * @method float getLiters()
 * @method void setLiters(float $liters)
 * @method float getAmount()
 * @method void setAmount(float $amount)
 * @method int getMileageKm()
 * @method void setMileageKm(int $mileageKm)
 * @method int|null getReceiptFileId()
 * @method void setReceiptFileId(?int $receiptFileId)
 * @method string|null getNotes()
 * @method void setNotes(?string $notes)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 */
class VehicleFuelLog extends Entity implements \JsonSerializable {
	protected int $vehicleId = 0;
	protected string $entryDate = '';
	protected float $liters = 0.0;
	protected float $amount = 0.0;
	protected int $mileageKm = 0;
	protected ?int $receiptFileId = null;
	protected ?string $notes = null;
	protected int $createdAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('vehicleId', 'integer');
		$this->addType('liters', 'float');
		$this->addType('amount', 'float');
		$this->addType('mileageKm', 'integer');
		$this->addType('receiptFileId', 'integer');
		$this->addType('createdAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'vehicleId' => $this->getVehicleId(),
			'entryDate' => $this->getEntryDate(),
			'liters' => $this->getLiters(),
			'amount' => $this->getAmount(),
			'mileageKm' => $this->getMileageKm(),
			'receiptFileId' => $this->getReceiptFileId(),
			'notes' => $this->getNotes(),
		];
	}
}
