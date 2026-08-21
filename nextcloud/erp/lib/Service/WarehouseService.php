<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\Warehouse;
use OCA\ERP\Db\WarehouseMapper;

/** Lagerorte (Roadmap Phase 8, ADR-0014) — Zentrallager, optional Fahrzeug-/Baustellenlager. */
class WarehouseService {
	private const VALID_TYPES = ['central', 'vehicle', 'site'];

	public function __construct(
		private WarehouseMapper $mapper,
	) {
	}

	/** @return Warehouse[] */
	public function listAll(): array {
		return $this->mapper->findAll();
	}

	/** @throws \OutOfBoundsException */
	public function get(int $id): Warehouse {
		$warehouse = $this->mapper->findById($id);
		if ($warehouse === null) {
			throw new \OutOfBoundsException("Warehouse $id not found");
		}
		return $warehouse;
	}

	/** @throws \InvalidArgumentException */
	public function create(string $name, string $type, ?int $projectId, ?string $notes, ?int $vehicleId = null): Warehouse {
		if (!in_array($type, self::VALID_TYPES, true)) {
			throw new \InvalidArgumentException('type must be one of: ' . implode(', ', self::VALID_TYPES));
		}
		if ($type === 'site' && $projectId === null) {
			throw new \InvalidArgumentException("projectId is required for warehouse type 'site'");
		}

		$now = time();
		$warehouse = new Warehouse();
		$warehouse->setName($name);
		$warehouse->setType($type);
		$warehouse->setProjectId($type === 'site' ? $projectId : null);
		// vehicle_id ist nur bei type='vehicle' sinnvoll (ADR-0017) — bei
		// anderen Typen wird ein mitgeschickter Wert bewusst ignoriert,
		// analog zu project_id bei type != 'site'.
		$warehouse->setVehicleId($type === 'vehicle' ? $vehicleId : null);
		$warehouse->setActive(true);
		$warehouse->setNotes($notes);
		$warehouse->setCreatedAt($now);
		$warehouse->setUpdatedAt($now);
		return $this->mapper->insert($warehouse);
	}

	/** @throws \OutOfBoundsException|\InvalidArgumentException */
	public function update(int $id, string $name, bool $active, ?string $notes): Warehouse {
		$warehouse = $this->get($id);
		if (trim($name) === '') {
			throw new \InvalidArgumentException('name must not be empty');
		}
		$warehouse->setName($name);
		$warehouse->setActive($active);
		$warehouse->setNotes($notes);
		$warehouse->setUpdatedAt(time());
		return $this->mapper->update($warehouse);
	}
}
