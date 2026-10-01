<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Fahrtenbuch-Eintrag (ADR-0028). `distanceKm` ist kein eigenes DB-Feld,
 * sondern immer `endMileageKm - startMileageKm` — wird nicht redundant
 * gespeichert, um Drift zu vermeiden (siehe jsonSerialize()).
 *
 * @method int getVehicleId() @method void setVehicleId(int $value)
 * @method string getTripDate() @method void setTripDate(string $value)
 * @method string|null getDriverUserId() @method void setDriverUserId(?string $value)
 * @method string getPurpose() @method void setPurpose(string $value)
 * @method string getStartLocation() @method void setStartLocation(string $value)
 * @method string getDestination() @method void setDestination(string $value)
 * @method int getStartMileageKm() @method void setStartMileageKm(int $value)
 * @method int getEndMileageKm() @method void setEndMileageKm(int $value)
 * @method string|null getNotes() @method void setNotes(?string $value)
 * @method string getCreatedBy() @method void setCreatedBy(string $value)
 * @method int getCreatedAt() @method void setCreatedAt(int $value)
 */
class VehicleTrip extends Entity implements \JsonSerializable {
	protected int $vehicleId = 0;
	protected string $tripDate = '';
	protected ?string $driverUserId = null;
	protected string $purpose = 'business';
	protected string $startLocation = '';
	protected string $destination = '';
	protected int $startMileageKm = 0;
	protected int $endMileageKm = 0;
	protected ?string $notes = null;
	protected string $createdBy = '';
	protected int $createdAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('vehicleId', 'integer');
		$this->addType('startMileageKm', 'integer');
		$this->addType('endMileageKm', 'integer');
		$this->addType('createdAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'vehicleId' => $this->getVehicleId(),
			'tripDate' => $this->getTripDate(),
			'driverUserId' => $this->getDriverUserId(),
			'purpose' => $this->getPurpose(),
			'startLocation' => $this->getStartLocation(),
			'destination' => $this->getDestination(),
			'startMileageKm' => $this->getStartMileageKm(),
			'endMileageKm' => $this->getEndMileageKm(),
			'distanceKm' => $this->getEndMileageKm() - $this->getStartMileageKm(),
			'notes' => $this->getNotes(),
			'createdBy' => $this->getCreatedBy(),
			'createdAt' => $this->getCreatedAt(),
		];
	}
}
