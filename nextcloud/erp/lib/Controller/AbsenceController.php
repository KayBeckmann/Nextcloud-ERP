<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Service\AbsenceRequestService;
use OCA\ERP\Service\AbsenceTypeService;
use OCA\ERP\Service\CalendarService;
use OCA\ERP\Service\PermissionService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSPreconditionFailedException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\IRequest;
use OCP\IUserSession;

/** Abwesenheitstypen + Urlaubs-/Abwesenheitsanträge (ADR-0012, Gate: StundenZeitkonto). */
class AbsenceController extends AbstractResourceController {
	public function __construct(
		string $appName,
		IRequest $request,
		private AbsenceTypeService $absenceTypeService,
		private AbsenceRequestService $absenceRequestService,
		private CalendarService $calendarService,
		PermissionService $permissionService,
		IUserSession $userSession,
	) {
		parent::__construct($appName, $request, $permissionService, $userSession);
	}

	protected function resource(): ResourceType {
		return ResourceType::StundenZeitkonto;
	}

	#[NoAdminRequired]
	public function types(): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		return new DataResponse($this->absenceTypeService->listAll());
	}

	/** @throws OCSBadRequestException */
	#[NoAdminRequired]
	public function createType(string $name, bool $affectsVacationBalance = false): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if (trim($name) === '') {
			throw new OCSBadRequestException('name must not be empty');
		}
		return new DataResponse($this->absenceTypeService->create($name, $affectsVacationBalance));
	}

	/**
	 * Eigene Anträge ab Read, fremde (userId-Parameter) oder alle offenen
	 * Anträge (status=requested, für die Freigabe-Ansicht) erfordern Approve.
	 */
	#[NoAdminRequired]
	public function index(?string $userId = null, ?string $status = null): DataResponse {
		$user = $this->requireLevel(PermissionLevel::Read);
		if ($status !== null) {
			$this->requireLevel(PermissionLevel::Approve);
			return new DataResponse($this->absenceRequestService->listByStatus($status));
		}
		$targetUserId = $userId ?? $user->getUID();
		if ($targetUserId !== $user->getUID()) {
			$this->requireLevel(PermissionLevel::Approve);
		}
		return new DataResponse($this->absenceRequestService->listForUser($targetUserId));
	}

	/** @throws OCSBadRequestException */
	#[NoAdminRequired]
	public function create(int $absenceTypeId, string $startDate, string $endDate, ?string $notes = null): DataResponse {
		$user = $this->requireLevel(PermissionLevel::Write);
		if ($endDate < $startDate) {
			throw new OCSBadRequestException('endDate must not be before startDate');
		}
		try {
			return new DataResponse($this->absenceRequestService->create($user->getUID(), $absenceTypeId, $startDate, $endDate, $notes));
		} catch (\OutOfBoundsException $e) {
			throw new OCSBadRequestException($e->getMessage());
		}
	}

	/** @throws OCSNotFoundException|OCSPreconditionFailedException */
	#[NoAdminRequired]
	public function approve(int $id): DataResponse {
		$this->requireLevel(PermissionLevel::Approve);
		try {
			return new DataResponse($this->absenceRequestService->approve($id));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Absence request $id not found");
		} catch (\DomainException $e) {
			throw new OCSPreconditionFailedException($e->getMessage());
		}
	}

	/** @throws OCSNotFoundException|OCSPreconditionFailedException */
	#[NoAdminRequired]
	public function reject(int $id): DataResponse {
		$this->requireLevel(PermissionLevel::Approve);
		try {
			return new DataResponse($this->absenceRequestService->reject($id));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Absence request $id not found");
		} catch (\DomainException $e) {
			throw new OCSPreconditionFailedException($e->getMessage());
		}
	}

	/**
	 * Zeigt den bei der Genehmigung optional angelegten Kalendertermin
	 * (siehe AbsenceRequestService::approve()). Eigene, generische
	 * calendar#links-Route passt hier nicht, weil deren resourceType eine
	 * ResourceType-Berechtigung adressiert — 'absence' ist keine.
	 */
	#[NoAdminRequired]
	public function calendarLinks(int $id): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		return new DataResponse($this->calendarService->listLinks('absence', (string)$id));
	}
}
