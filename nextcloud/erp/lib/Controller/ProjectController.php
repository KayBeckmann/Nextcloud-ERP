<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Projects\ProjectStatus;
use OCA\ERP\Service\PermissionService;
use OCA\ERP\Service\ProjectService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;

/**
 * Projektkern (Roadmap Phase 4, ADR-0010). Rechte-Gate über
 * ResourceType::Projekte, wie schon bei Contacts/Calendar in Phase 3.
 */
class ProjectController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private ProjectService $projectService,
		private PermissionService $permissionService,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	/** @throws OCSForbiddenException */
	private function requireUser(): IUser {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new OCSForbiddenException('No active user session');
		}
		return $user;
	}

	/** @throws OCSForbiddenException */
	private function requireLevel(IUser $user, PermissionLevel $required): void {
		$level = $this->permissionService->getEffectivePermission($user, ResourceType::Projekte);
		if (!$level->atLeast($required)) {
			throw new OCSForbiddenException("Requires at least '{$required->value}' on 'projekte'");
		}
	}

	/** @throws OCSBadRequestException */
	private static function parseStatus(?string $status): ?ProjectStatus {
		if ($status === null || $status === '') {
			return null;
		}
		$parsed = ProjectStatus::tryFrom($status);
		if ($parsed === null) {
			throw new OCSBadRequestException("Unknown status: $status");
		}
		return $parsed;
	}

	/**
	 * @throws OCSForbiddenException|OCSBadRequestException
	 */
	#[NoAdminRequired]
	public function index(?string $status = null): DataResponse {
		$user = $this->requireUser();
		$this->requireLevel($user, PermissionLevel::Read);
		return new DataResponse($this->projectService->listProjects(self::parseStatus($status)));
	}

	/**
	 * @throws OCSForbiddenException|OCSNotFoundException
	 */
	#[NoAdminRequired]
	public function show(int $id): DataResponse {
		$user = $this->requireUser();
		$this->requireLevel($user, PermissionLevel::Read);
		try {
			return new DataResponse($this->projectService->getProject($id));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Project $id not found");
		}
	}

	/**
	 * @throws OCSForbiddenException|OCSBadRequestException
	 */
	#[NoAdminRequired]
	public function create(string $title, ?string $customerContactUid = null, ?string $responsibleUserId = null, ?string $notes = null): DataResponse {
		$user = $this->requireUser();
		$this->requireLevel($user, PermissionLevel::Write);

		if (trim($title) === '') {
			throw new OCSBadRequestException('title must not be empty');
		}

		return new DataResponse($this->projectService->createProject($user, $title, $customerContactUid, $responsibleUserId, $notes));
	}

	/**
	 * @throws OCSForbiddenException|OCSBadRequestException|OCSNotFoundException
	 */
	#[NoAdminRequired]
	public function update(
		int $id,
		string $title,
		string $status,
		?string $customerContactUid = null,
		?string $responsibleUserId = null,
		?string $notes = null,
	): DataResponse {
		$user = $this->requireUser();
		$this->requireLevel($user, PermissionLevel::Write);

		if (trim($title) === '') {
			throw new OCSBadRequestException('title must not be empty');
		}
		$parsedStatus = ProjectStatus::tryFrom($status);
		if ($parsedStatus === null) {
			throw new OCSBadRequestException("Unknown status: $status");
		}

		try {
			$project = $this->projectService->updateProject($id, $title, $customerContactUid, $responsibleUserId, $parsedStatus, $notes);
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Project $id not found");
		}

		return new DataResponse($project);
	}
}
