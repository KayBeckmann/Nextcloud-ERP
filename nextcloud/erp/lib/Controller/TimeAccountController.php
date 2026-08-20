<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Service\PermissionService;
use OCA\ERP\Service\TimeAccountService;
use OCA\ERP\Service\WorkScheduleService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\IRequest;
use OCP\IUserSession;

/** Zeitkonto (Soll/Ist) + Arbeitszeitmodell (ADR-0012, Gate: StundenZeitkonto). */
class TimeAccountController extends AbstractResourceController {
	public function __construct(
		string $appName,
		IRequest $request,
		private TimeAccountService $timeAccountService,
		private WorkScheduleService $workScheduleService,
		PermissionService $permissionService,
		IUserSession $userSession,
	) {
		parent::__construct($appName, $request, $permissionService, $userSession);
	}

	protected function resource(): ResourceType {
		return ResourceType::StundenZeitkonto;
	}

	/** Eigenes Zeitkonto ab Read, fremde Zeitkonten (userId-Parameter) erfordern Approve. */
	#[NoAdminRequired]
	public function index(string $fromDate, string $toDate, ?string $userId = null): DataResponse {
		$user = $this->requireLevel(PermissionLevel::Read);
		$targetUserId = $userId ?? $user->getUID();
		if ($targetUserId !== $user->getUID()) {
			$this->requireLevel(PermissionLevel::Approve);
		}
		try {
			return new DataResponse($this->timeAccountService->getAccount($targetUserId, $fromDate, $toDate));
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage());
		}
	}

	/** Eigenes Arbeitszeitmodell ab Read, fremde erfordern Approve. */
	#[NoAdminRequired]
	public function schedule(?string $userId = null): DataResponse {
		$user = $this->requireLevel(PermissionLevel::Read);
		$targetUserId = $userId ?? $user->getUID();
		if ($targetUserId !== $user->getUID()) {
			$this->requireLevel(PermissionLevel::Approve);
		}
		return new DataResponse($this->workScheduleService->getForUser($targetUserId));
	}

	/** @throws OCSBadRequestException */
	#[NoAdminRequired]
	public function setSchedule(string $userId, float $weeklyHours): DataResponse {
		$this->requireLevel(PermissionLevel::Approve);
		if ($weeklyHours < 0 || $weeklyHours > 168) {
			throw new OCSBadRequestException('weeklyHours must be between 0 and 168');
		}
		return new DataResponse($this->workScheduleService->setForUser($userId, $weeklyHours));
	}
}
