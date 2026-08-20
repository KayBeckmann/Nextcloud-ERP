<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Service\PermissionService;
use OCA\ERP\Service\TimeEntryService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\IRequest;
use OCP\IUserSession;

/** Zeiterfassung (ADR-0012, Gate: StundenZeitkonto). */
class TimeEntryController extends AbstractResourceController {
	public function __construct(
		string $appName,
		IRequest $request,
		private TimeEntryService $timeEntryService,
		PermissionService $permissionService,
		IUserSession $userSession,
	) {
		parent::__construct($appName, $request, $permissionService, $userSession);
	}

	protected function resource(): ResourceType {
		return ResourceType::StundenZeitkonto;
	}

	/**
	 * Eigene Einträge listet jeder mit Read-Recht; fremde Einträge (userId
	 * abweichend vom angemeldeten User) erfordern Approve, analog zur
	 * Freigabe-Rolle in der Roadmap.
	 *
	 * @throws OCSForbiddenException
	 */
	#[NoAdminRequired]
	public function index(?string $userId = null, ?string $projectId = null, ?string $fromDate = null, ?string $toDate = null): DataResponse {
		$user = $this->requireLevel(PermissionLevel::Read);
		if ($projectId !== null) {
			return new DataResponse($this->timeEntryService->listForProject((int)$projectId));
		}
		$targetUserId = $userId ?? $user->getUID();
		if ($targetUserId !== $user->getUID()) {
			$this->requireLevel(PermissionLevel::Approve);
		}
		return new DataResponse($this->timeEntryService->listForUser($targetUserId, $fromDate, $toDate));
	}

	/** @throws OCSNotFoundException */
	#[NoAdminRequired]
	public function show(int $id): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		try {
			return new DataResponse($this->timeEntryService->get($id));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Time entry $id not found");
		}
	}

	/** @throws OCSBadRequestException */
	#[NoAdminRequired]
	public function create(
		int $workTypeId,
		string $entryDate,
		int $durationMinutes,
		?int $projectId = null,
		?int $orderId = null,
		int $breakMinutes = 0,
		bool $billable = true,
		?string $notes = null,
	): DataResponse {
		$user = $this->requireLevel(PermissionLevel::Write);
		if ($durationMinutes <= 0) {
			throw new OCSBadRequestException('durationMinutes must be greater than 0');
		}
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $entryDate)) {
			throw new OCSBadRequestException('entryDate must be in YYYY-MM-DD format');
		}
		$groupIds = $this->permissionService->groupIdsFor($user);
		try {
			return new DataResponse($this->timeEntryService->create(
				$user->getUID(),
				$groupIds,
				$workTypeId,
				$projectId,
				$orderId,
				$entryDate,
				$durationMinutes,
				$breakMinutes,
				$billable,
				$notes,
			));
		} catch (\OutOfBoundsException $e) {
			throw new OCSBadRequestException($e->getMessage());
		}
	}

	/** @throws OCSBadRequestException|OCSNotFoundException */
	#[NoAdminRequired]
	public function update(int $id, string $entryDate, int $durationMinutes, int $breakMinutes = 0, bool $billable = true, ?string $notes = null): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if ($durationMinutes <= 0) {
			throw new OCSBadRequestException('durationMinutes must be greater than 0');
		}
		try {
			return new DataResponse($this->timeEntryService->update($id, $entryDate, $durationMinutes, $breakMinutes, $billable, $notes));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Time entry $id not found");
		}
	}

	/** @throws OCSNotFoundException */
	#[NoAdminRequired]
	public function destroy(int $id): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		try {
			$this->timeEntryService->delete($id);
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Time entry $id not found");
		}
		return new DataResponse([]);
	}
}
