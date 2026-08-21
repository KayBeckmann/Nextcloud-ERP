<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Service\PermissionService;
use OCA\ERP\Service\VehicleService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\IRequest;
use OCP\IUserSession;

/** Fuhrpark (ADR-0017, Gate: Fuhrpark). */
class VehicleController extends AbstractResourceController {
	public function __construct(
		string $appName,
		IRequest $request,
		private VehicleService $vehicleService,
		PermissionService $permissionService,
		IUserSession $userSession,
	) {
		parent::__construct($appName, $request, $permissionService, $userSession);
	}

	protected function resource(): ResourceType {
		return ResourceType::Fuhrpark;
	}

	#[NoAdminRequired]
	public function index(?string $status = null): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		return new DataResponse($this->vehicleService->listAll($status));
	}

	/** @throws OCSNotFoundException */
	#[NoAdminRequired]
	public function show(int $id): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		try {
			return new DataResponse($this->vehicleService->getFull($id));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Vehicle $id not found");
		}
	}

	/** @throws OCSBadRequestException */
	#[NoAdminRequired]
	public function create(
		string $licensePlate,
		string $vehicleType = 'car',
		?string $brandModel = null,
		?string $assignedUserId = null,
		?string $nextInspectionDate = null,
		?string $notes = null,
	): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		try {
			return new DataResponse($this->vehicleService->create($licensePlate, $brandModel, $vehicleType, $assignedUserId, $nextInspectionDate, $notes));
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage());
		}
	}

	/** @throws OCSBadRequestException|OCSNotFoundException */
	#[NoAdminRequired]
	public function update(
		int $id,
		string $licensePlate,
		string $vehicleType,
		string $status,
		?string $brandModel = null,
		?string $assignedUserId = null,
		?string $nextInspectionDate = null,
		?string $notes = null,
	): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		try {
			return new DataResponse($this->vehicleService->update($id, $licensePlate, $brandModel, $vehicleType, $status, $assignedUserId, $nextInspectionDate, $notes));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Vehicle $id not found");
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage());
		}
	}

	/** @throws OCSBadRequestException|OCSNotFoundException */
	#[NoAdminRequired]
	public function addFuelLog(int $vehicleId, string $entryDate, float $liters, float $amount, int $mileageKm, ?string $notes = null): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		try {
			return new DataResponse($this->vehicleService->addFuelLog($vehicleId, $entryDate, $liters, $amount, $mileageKm, $notes));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Vehicle $vehicleId not found");
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage());
		}
	}

	/** @throws OCSNotFoundException */
	#[NoAdminRequired]
	public function removeFuelLog(int $vehicleId, int $id): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		try {
			$this->vehicleService->removeFuelLog($vehicleId, $id);
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Fuel log $id not found for vehicle $vehicleId");
		}
		return new DataResponse([]);
	}

	/**
	 * Tankbeleg-Foto hochladen — Base64 im JSON-Body (ADR-0017).
	 *
	 * @throws OCSBadRequestException|OCSNotFoundException|OCSForbiddenException
	 */
	#[NoAdminRequired]
	public function uploadReceipt(int $vehicleId, int $fuelLogId, string $fileName, string $content): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new OCSForbiddenException('No active user session');
		}
		try {
			return new DataResponse($this->vehicleService->uploadReceipt($vehicleId, $fuelLogId, $user, $fileName, $content));
		} catch (\OutOfBoundsException $e) {
			throw new OCSNotFoundException($e->getMessage());
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage());
		}
	}
}
