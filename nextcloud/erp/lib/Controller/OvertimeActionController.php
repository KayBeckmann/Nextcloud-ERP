<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Service\OvertimeActionService;
use OCA\ERP\Service\PermissionService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCS\OCSPreconditionFailedException;
use OCP\IRequest;
use OCP\IUserSession;

/** Überstunden-Abbau/-Auszahlung mit Freigabe-Workflow (ADR-0012, Gate: StundenZeitkonto). */
class OvertimeActionController extends AbstractResourceController {
	public function __construct(
		string $appName,
		IRequest $request,
		private OvertimeActionService $overtimeActionService,
		PermissionService $permissionService,
		IUserSession $userSession,
	) {
		parent::__construct($appName, $request, $permissionService, $userSession);
	}

	protected function resource(): ResourceType {
		return ResourceType::StundenZeitkonto;
	}

	/** Eigene Aktionen ab Read, fremde (userId) oder alle offenen (status) erfordern Approve. */
	#[NoAdminRequired]
	public function index(?string $userId = null, ?string $status = null): DataResponse {
		$user = $this->requireLevel(PermissionLevel::Read);
		if ($status !== null) {
			$this->requireLevel(PermissionLevel::Approve);
			return new DataResponse($this->overtimeActionService->listByStatus($status));
		}
		$targetUserId = $userId ?? $user->getUID();
		if ($targetUserId !== $user->getUID()) {
			$this->requireLevel(PermissionLevel::Approve);
		}
		return new DataResponse($this->overtimeActionService->listForUser($targetUserId));
	}

	/** @throws OCSBadRequestException */
	#[NoAdminRequired]
	public function create(float $hours, string $actionType, ?string $notes = null): DataResponse {
		$user = $this->requireLevel(PermissionLevel::Write);
		if ($hours <= 0) {
			throw new OCSBadRequestException('hours must be greater than 0');
		}
		try {
			return new DataResponse($this->overtimeActionService->create($user->getUID(), $hours, $actionType, $notes));
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage());
		}
	}

	/** @throws OCSNotFoundException|OCSPreconditionFailedException */
	#[NoAdminRequired]
	public function approve(int $id): DataResponse {
		$this->requireLevel(PermissionLevel::Approve);
		return $this->transition($id, fn (int $id) => $this->overtimeActionService->approve($id));
	}

	/** @throws OCSNotFoundException|OCSPreconditionFailedException */
	#[NoAdminRequired]
	public function complete(int $id): DataResponse {
		$this->requireLevel(PermissionLevel::Approve);
		return $this->transition($id, fn (int $id) => $this->overtimeActionService->complete($id));
	}

	/** @throws OCSNotFoundException|OCSPreconditionFailedException */
	#[NoAdminRequired]
	public function reject(int $id): DataResponse {
		$this->requireLevel(PermissionLevel::Approve);
		return $this->transition($id, fn (int $id) => $this->overtimeActionService->reject($id));
	}

	/** @throws OCSNotFoundException|OCSPreconditionFailedException */
	private function transition(int $id, callable $action): DataResponse {
		try {
			return new DataResponse($action($id));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Overtime action $id not found");
		} catch (\DomainException $e) {
			throw new OCSPreconditionFailedException($e->getMessage());
		}
	}
}
