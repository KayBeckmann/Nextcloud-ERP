<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\Vehicle;
use OCA\ERP\Db\VehicleFuelLog;
use OCA\ERP\Db\VehicleFuelLogMapper;
use OCA\ERP\Db\VehicleMapper;
use OCA\ERP\Db\WarehouseMapper;
use OCP\IUser;

/**
 * Fuhrpark (Roadmap Phase 9, ADR-0017): Fahrzeugstamm, Tankbelege
 * (inkl. Foto-Upload), Kilometerstand-Fortschreibung, Verknüpfung zum
 * bestehenden Fahrzeuglager aus ADR-0014.
 */
class VehicleService {
	private const VALID_TYPES = ['car', 'van', 'trailer', 'other'];
	private const VALID_STATUSES = ['active', 'inactive', 'sold'];

	public function __construct(
		private VehicleMapper $mapper,
		private VehicleFuelLogMapper $fuelLogMapper,
		private WarehouseMapper $warehouseMapper,
		private ErpFolderService $folderService,
	) {
	}

	/** @return Vehicle[] */
	public function listAll(?string $status = null): array {
		return $this->mapper->findAll($status);
	}

	/** @throws \OutOfBoundsException */
	public function get(int $id): Vehicle {
		$vehicle = $this->mapper->findById($id);
		if ($vehicle === null) {
			throw new \OutOfBoundsException("Vehicle $id not found");
		}
		return $vehicle;
	}

	/**
	 * Fahrzeug inkl. Tankbelegen und verknüpften Fahrzeuglagern
	 * (ADR-0014/ADR-0017).
	 */
	public function getFull(int $id): array {
		$vehicle = $this->get($id);
		return [
			...$vehicle->jsonSerialize(),
			'fuelLogs' => $this->fuelLogMapper->findByVehicle($id),
			'warehouses' => $this->warehouseMapper->findByVehicle($id),
		];
	}

	/**
	 * @throws \InvalidArgumentException wenn Kennzeichen leer/doppelt oder
	 *         vehicleType unbekannt ist
	 */
	public function create(
		string $licensePlate,
		?string $brandModel,
		string $vehicleType,
		?string $assignedUserId,
		?string $nextInspectionDate,
		?string $notes,
	): Vehicle {
		$licensePlate = trim($licensePlate);
		if ($licensePlate === '') {
			throw new \InvalidArgumentException('licensePlate must not be empty');
		}
		if (!in_array($vehicleType, self::VALID_TYPES, true)) {
			throw new \InvalidArgumentException('vehicleType must be one of: ' . implode(', ', self::VALID_TYPES));
		}
		if ($this->mapper->findByLicensePlate($licensePlate) !== null) {
			throw new \InvalidArgumentException("License plate '$licensePlate' is already in use");
		}

		$now = time();
		$vehicle = new Vehicle();
		$vehicle->setLicensePlate($licensePlate);
		$vehicle->setBrandModel($brandModel);
		$vehicle->setVehicleType($vehicleType);
		$vehicle->setStatus('active');
		$vehicle->setAssignedUserId($assignedUserId);
		$vehicle->setCurrentMileageKm(0);
		$vehicle->setNextInspectionDate($nextInspectionDate);
		$vehicle->setNotes($notes);
		$vehicle->setCreatedAt($now);
		$vehicle->setUpdatedAt($now);
		return $this->mapper->insert($vehicle);
	}

	/**
	 * @throws \OutOfBoundsException
	 * @throws \InvalidArgumentException wenn Kennzeichen leer/doppelt oder Typ/Status unbekannt ist
	 */
	public function update(
		int $id,
		string $licensePlate,
		?string $brandModel,
		string $vehicleType,
		string $status,
		?string $assignedUserId,
		?string $nextInspectionDate,
		?string $notes,
	): Vehicle {
		$vehicle = $this->get($id);
		$licensePlate = trim($licensePlate);
		if ($licensePlate === '') {
			throw new \InvalidArgumentException('licensePlate must not be empty');
		}
		if (!in_array($vehicleType, self::VALID_TYPES, true)) {
			throw new \InvalidArgumentException('vehicleType must be one of: ' . implode(', ', self::VALID_TYPES));
		}
		if (!in_array($status, self::VALID_STATUSES, true)) {
			throw new \InvalidArgumentException('status must be one of: ' . implode(', ', self::VALID_STATUSES));
		}
		$existing = $this->mapper->findByLicensePlate($licensePlate);
		if ($existing !== null && $existing->getId() !== $id) {
			throw new \InvalidArgumentException("License plate '$licensePlate' is already in use");
		}

		$vehicle->setLicensePlate($licensePlate);
		$vehicle->setBrandModel($brandModel);
		$vehicle->setVehicleType($vehicleType);
		$vehicle->setStatus($status);
		$vehicle->setAssignedUserId($assignedUserId);
		$vehicle->setNextInspectionDate($nextInspectionDate);
		$vehicle->setNotes($notes);
		$vehicle->setUpdatedAt(time());
		return $this->mapper->update($vehicle);
	}

	/**
	 * Erfasst einen Tankbeleg. Ein Kilometerstand über dem bisherigen
	 * `currentMileageKm` schreibt diesen automatisch fort (informativ,
	 * kein Zwang — ein niedrigerer Wert wird nicht abgelehnt, nur nicht
	 * übernommen, ADR-0017).
	 *
	 * @throws \OutOfBoundsException wenn das Fahrzeug nicht existiert
	 * @throws \InvalidArgumentException wenn liters/amount/mileageKm negativ sind
	 */
	public function addFuelLog(int $vehicleId, string $entryDate, float $liters, float $amount, int $mileageKm, ?string $notes): VehicleFuelLog {
		$vehicle = $this->get($vehicleId);
		if ($liters < 0 || $amount < 0 || $mileageKm < 0) {
			throw new \InvalidArgumentException('liters/amount/mileageKm must not be negative');
		}

		$log = new VehicleFuelLog();
		$log->setVehicleId($vehicleId);
		$log->setEntryDate($entryDate);
		$log->setLiters($liters);
		$log->setAmount($amount);
		$log->setMileageKm($mileageKm);
		$log->setNotes($notes);
		$log->setCreatedAt(time());
		$log = $this->fuelLogMapper->insert($log);

		if ($mileageKm > $vehicle->getCurrentMileageKm()) {
			$vehicle->setCurrentMileageKm($mileageKm);
			$vehicle->setUpdatedAt(time());
			$this->mapper->update($vehicle);
		}

		return $log;
	}

	/** @throws \OutOfBoundsException */
	public function removeFuelLog(int $vehicleId, int $id): void {
		$log = $this->fuelLogMapper->findOne($vehicleId, $id);
		if ($log === null) {
			throw new \OutOfBoundsException("Fuel log $id not found for vehicle $vehicleId");
		}
		$this->fuelLogMapper->delete($log);
	}

	/**
	 * Lädt ein Tankbeleg-Foto hoch (Base64 im JSON-Body, ADR-0017) und legt
	 * es unter `ERP/Fuhrpark/<Kennzeichen>/Tankbelege/` ab.
	 *
	 * @throws \OutOfBoundsException wenn Fahrzeug oder Tankbeleg nicht existiert
	 * @throws \InvalidArgumentException wenn der Base64-Inhalt ungültig ist
	 */
	public function uploadReceipt(int $vehicleId, int $fuelLogId, IUser $user, string $fileName, string $base64Content): VehicleFuelLog {
		$vehicle = $this->get($vehicleId);
		$log = $this->fuelLogMapper->findOne($vehicleId, $fuelLogId);
		if ($log === null) {
			throw new \OutOfBoundsException("Fuel log $fuelLogId not found for vehicle $vehicleId");
		}

		$binary = base64_decode($base64Content, true);
		if ($binary === false) {
			throw new \InvalidArgumentException('content must be valid base64');
		}

		$folder = $this->folderService->ensureVehicleReceiptFolder($user, $vehicle->getLicensePlate());
		$file = $folder->nodeExists($fileName) ? $folder->get($fileName) : $folder->newFile($fileName);
		$file->putContent($binary);

		$log->setReceiptFileId($file->getId());
		return $this->fuelLogMapper->update($log);
	}
}
