<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Ein Zeitabschnitt, in dem ein User einem Fahrzeug zugewiesen war
 * (ADR-0028) — `unassignedAt === null` bedeutet: aktuell noch aktiv.
 *
 * @method int getVehicleId() @method void setVehicleId(int $value)
 * @method string getUserId() @method void setUserId(string $value)
 * @method int getAssignedAt() @method void setAssignedAt(int $value)
 * @method int|null getUnassignedAt() @method void setUnassignedAt(?int $value)
 */
class VehicleAssignment extends Entity implements \JsonSerializable {
	protected int $vehicleId = 0;
	protected string $userId = '';
	protected int $assignedAt = 0;
	protected ?int $unassignedAt = null;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('vehicleId', 'integer');
		$this->addType('assignedAt', 'integer');
		$this->addType('unassignedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'vehicleId' => $this->getVehicleId(),
			'userId' => $this->getUserId(),
			'assignedAt' => $this->getAssignedAt(),
			'unassignedAt' => $this->getUnassignedAt(),
		];
	}
}
