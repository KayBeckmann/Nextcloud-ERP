<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\Vehicle;
use OCA\ERP\Db\VehicleAssignment;
use OCA\ERP\Db\VehicleAssignmentMapper;
use OCA\ERP\Db\VehicleFuelLog;
use OCA\ERP\Db\VehicleFuelLogMapper;
use OCA\ERP\Db\VehicleMapper;
use OCA\ERP\Db\VehicleTrip;
use OCA\ERP\Db\VehicleTripMapper;
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

	private const VALID_PURPOSES = ['business', 'private'];

	public function __construct(
		private VehicleMapper $mapper,
		private VehicleFuelLogMapper $fuelLogMapper,
		private WarehouseMapper $warehouseMapper,
		private ErpFolderService $folderService,
		private VehicleTripMapper $tripMapper,
		private VehicleAssignmentMapper $assignmentMapper,
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
			'trips' => $this->tripMapper->findByVehicle($id),
			'assignmentHistory' => $this->assignmentMapper->findByVehicle($id),
			'fuelConsumption' => $this->fuelConsumptionStats($id),
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
		$vehicle = $this->mapper->insert($vehicle);

		if ($assignedUserId !== null) {
			$this->openAssignment($vehicle->getId(), $assignedUserId, $now);
		}

		return $vehicle;
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

		$previousAssignee = $vehicle->getAssignedUserId();

		$vehicle->setLicensePlate($licensePlate);
		$vehicle->setBrandModel($brandModel);
		$vehicle->setVehicleType($vehicleType);
		$vehicle->setStatus($status);
		$vehicle->setAssignedUserId($assignedUserId);
		$vehicle->setNextInspectionDate($nextInspectionDate);
		$vehicle->setNotes($notes);
		$now = time();
		$vehicle->setUpdatedAt($now);
		$vehicle = $this->mapper->update($vehicle);

		// Zuweisungs-Historie (ADR-0028) nur bei tatsächlicher Änderung
		// fortschreiben — ein "Speichern" mit unverändertem Fahrer soll
		// keinen neuen Zuweisungs-Zeitraum eröffnen.
		if ($assignedUserId !== $previousAssignee) {
			if ($previousAssignee !== null) {
				$this->closeOpenAssignment($id, $now);
			}
			if ($assignedUserId !== null) {
				$this->openAssignment($id, $assignedUserId, $now);
			}
		}

		return $vehicle;
	}

	private function openAssignment(int $vehicleId, string $userId, int $now): void {
		$assignment = new VehicleAssignment();
		$assignment->setVehicleId($vehicleId);
		$assignment->setUserId($userId);
		$assignment->setAssignedAt($now);
		$this->assignmentMapper->insert($assignment);
	}

	private function closeOpenAssignment(int $vehicleId, int $now): void {
		$open = $this->assignmentMapper->findOpen($vehicleId);
		if ($open === null) {
			// Kann bei Altdatensätzen vorkommen, die vor ADR-0028 einen
			// Fahrer zugewiesen bekamen, ohne dass eine Historie-Zeile
			// angelegt wurde — kein Fehlerfall, einfach nichts zu schließen.
			return;
		}
		$open->setUnassignedAt($now);
		$this->assignmentMapper->update($open);
	}

	/** @return VehicleAssignment[] */
	public function listAssignmentHistory(int $vehicleId): array {
		$this->get($vehicleId);
		return $this->assignmentMapper->findByVehicle($vehicleId);
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

	/**
	 * Erfasst eine Fahrtenbuch-Fahrt (ADR-0028). `endMileageKm` über dem
	 * bisherigen `currentMileageKm` schreibt diesen automatisch fort —
	 * dieselbe informative Logik wie bei Tankbelegen.
	 *
	 * @throws \OutOfBoundsException wenn das Fahrzeug nicht existiert
	 * @throws \InvalidArgumentException wenn purpose ungültig ist, Start-/
	 *         Zielort leer sind, oder endMileageKm < startMileageKm ist
	 */
	public function recordTrip(
		int $vehicleId,
		string $tripDate,
		?string $driverUserId,
		string $purpose,
		string $startLocation,
		string $destination,
		int $startMileageKm,
		int $endMileageKm,
		string $createdBy,
		?string $notes,
	): VehicleTrip {
		$vehicle = $this->get($vehicleId);
		if (!in_array($purpose, self::VALID_PURPOSES, true)) {
			throw new \InvalidArgumentException('purpose must be one of: ' . implode(', ', self::VALID_PURPOSES));
		}
		if (trim($startLocation) === '' || trim($destination) === '') {
			throw new \InvalidArgumentException('startLocation/destination must not be empty');
		}
		if ($endMileageKm < $startMileageKm) {
			throw new \InvalidArgumentException('endMileageKm must not be less than startMileageKm');
		}

		$trip = new VehicleTrip();
		$trip->setVehicleId($vehicleId);
		$trip->setTripDate($tripDate);
		$trip->setDriverUserId($driverUserId);
		$trip->setPurpose($purpose);
		$trip->setStartLocation($startLocation);
		$trip->setDestination($destination);
		$trip->setStartMileageKm($startMileageKm);
		$trip->setEndMileageKm($endMileageKm);
		$trip->setNotes($notes);
		$trip->setCreatedBy($createdBy);
		$trip->setCreatedAt(time());
		$trip = $this->tripMapper->insert($trip);

		if ($endMileageKm > $vehicle->getCurrentMileageKm()) {
			$vehicle->setCurrentMileageKm($endMileageKm);
			$vehicle->setUpdatedAt(time());
			$this->mapper->update($vehicle);
		}

		return $trip;
	}

	/** @return VehicleTrip[] */
	public function listTrips(int $vehicleId): array {
		$this->get($vehicleId);
		return $this->tripMapper->findByVehicle($vehicleId);
	}

	/** @throws \OutOfBoundsException */
	public function removeTrip(int $vehicleId, int $id): void {
		$trip = $this->tripMapper->findOne($vehicleId, $id);
		if ($trip === null) {
			throw new \OutOfBoundsException("Trip $id not found for vehicle $vehicleId");
		}
		$this->tripMapper->delete($trip);
	}

	/**
	 * Kraftstoffverbrauch je Tankvorgang in l/100km (ADR-0028) — aus der
	 * Distanz zum JEWEILS VORHERIGEN Tankbeleg (nach Kilometerstand
	 * sortiert) und den an DIESEM Beleg getankten Litern: diese Liter
	 * haben die Strecke seit dem letzten Volltanken ermöglicht. Der
	 * älteste Beleg hat keinen Vorgänger und liefert deshalb keinen
	 * Verbrauchswert (`consumptionL100km: null`) — ohne eine Startbasis
	 * ist kein Verbrauch berechenbar.
	 *
	 * Diese Methode setzt implizit voraus, dass jeder Tankvorgang (wie im
	 * echten Leben) eine Vollbetankung ist — bei Teilbetankungen wäre der
	 * errechnete Wert verfälscht. Das ERP kann das nicht validieren,
	 * daher keine zusätzliche Warnung im Ergebnis (siehe ADR-0028,
	 * "Nicht Teil dieser Phase").
	 *
	 * @return array{entries: list<array{fuelLogId:int,entryDate:string,distanceKm:int|null,consumptionL100km:float|null}>, averageL100km: float|null}
	 */
	public function fuelConsumptionStats(int $vehicleId): array {
		$logs = $this->fuelLogMapper->findByVehicle($vehicleId);
		// findByVehicle liefert neueste zuerst — für die Verbrauchsrechnung
		// brauchen wir aufsteigende Kilometerstände.
		usort($logs, static fn (VehicleFuelLog $a, VehicleFuelLog $b): int => $a->getMileageKm() <=> $b->getMileageKm());

		$entries = [];
		$totalDistance = 0;
		$totalLiters = 0.0;
		$previous = null;
		foreach ($logs as $log) {
			$distance = $previous !== null ? $log->getMileageKm() - $previous->getMileageKm() : null;
			$consumption = ($distance !== null && $distance > 0) ? round($log->getLiters() / $distance * 100, 2) : null;
			$entries[] = [
				'fuelLogId' => $log->getId(),
				'entryDate' => $log->getEntryDate(),
				'distanceKm' => $distance,
				'consumptionL100km' => $consumption,
			];
			if ($distance !== null && $distance > 0) {
				$totalDistance += $distance;
				$totalLiters += $log->getLiters();
			}
			$previous = $log;
		}

		return [
			'entries' => $entries,
			'averageL100km' => $totalDistance > 0 ? round($totalLiters / $totalDistance * 100, 2) : null,
		];
	}
}
