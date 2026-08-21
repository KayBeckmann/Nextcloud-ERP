<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getLicensePlate()
 * @method void setLicensePlate(string $licensePlate)
 * @method string|null getBrandModel()
 * @method void setBrandModel(?string $brandModel)
 * @method string getVehicleType()
 * @method void setVehicleType(string $vehicleType)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method string|null getAssignedUserId()
 * @method void setAssignedUserId(?string $assignedUserId)
 * @method int getCurrentMileageKm()
 * @method void setCurrentMileageKm(int $currentMileageKm)
 * @method string|null getNextInspectionDate()
 * @method void setNextInspectionDate(?string $nextInspectionDate)
 * @method string|null getNotes()
 * @method void setNotes(?string $notes)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class Vehicle extends Entity implements \JsonSerializable {
	protected string $licensePlate = '';
	protected ?string $brandModel = null;
	protected string $vehicleType = 'car';
	protected string $status = 'active';
	protected ?string $assignedUserId = null;
	protected int $currentMileageKm = 0;
	protected ?string $nextInspectionDate = null;
	protected ?string $notes = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('currentMileageKm', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'licensePlate' => $this->getLicensePlate(),
			'brandModel' => $this->getBrandModel(),
			'vehicleType' => $this->getVehicleType(),
			'status' => $this->getStatus(),
			'assignedUserId' => $this->getAssignedUserId(),
			'currentMileageKm' => $this->getCurrentMileageKm(),
			'nextInspectionDate' => $this->getNextInspectionDate(),
			'notes' => $this->getNotes(),
		];
	}
}
