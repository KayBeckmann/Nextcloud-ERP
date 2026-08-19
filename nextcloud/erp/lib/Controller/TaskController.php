<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Service\PermissionService;
use OCA\ERP\Service\ProjectTaskService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Projektaufgaben — Unterressource eines Projekts, deshalb dasselbe
 * Rechte-Gate wie ProjectController (ResourceType::Projekte, ADR-0010).
 */
class TaskController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private ProjectTaskService $taskService,
		private PermissionService $permissionService,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	/** @throws OCSForbiddenException */
	private function requireLevel(PermissionLevel $required): void {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new OCSForbiddenException('No active user session');
		}
		$level = $this->permissionService->getEffectivePermission($user, ResourceType::Projekte);
		if (!$level->atLeast($required)) {
			throw new OCSForbiddenException("Requires at least '{$required->value}' on 'projekte'");
		}
	}

	#[NoAdminRequired]
	public function index(int $projectId): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		return new DataResponse($this->taskService->listTasks($projectId));
	}

	/** @throws OCSBadRequestException */
	#[NoAdminRequired]
	public function create(int $projectId, string $title): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if (trim($title) === '') {
			throw new OCSBadRequestException('title must not be empty');
		}
		return new DataResponse($this->taskService->createTask($projectId, $title));
	}

	/** @throws OCSBadRequestException|OCSNotFoundException */
	#[NoAdminRequired]
	public function update(int $projectId, int $id, string $title, bool $done): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if (trim($title) === '') {
			throw new OCSBadRequestException('title must not be empty');
		}
		try {
			return new DataResponse($this->taskService->updateTask($projectId, $id, $title, $done));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Task $id not found");
		}
	}

	/** @throws OCSNotFoundException */
	#[NoAdminRequired]
	public function destroy(int $projectId, int $id): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		try {
			$this->taskService->deleteTask($projectId, $id);
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Task $id not found");
		}
		return new DataResponse([]);
	}
}
