<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use DateTimeImmutable;
use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Service\CalendarService;
use OCA\ERP\Service\PermissionService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSPreconditionFailedException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;

/**
 * Kalender-Integration (Roadmap Phase 3, ADR-0009). Termine anlegen prüft die
 * ERP-Rechte-Matrix für den übergebenen resourceType — dieselbe Logik wie
 * ContactsController, damit Rechte- und Integrationsschicht konsistent
 * zusammenspielen.
 */
class CalendarController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private CalendarService $calendarService,
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
	private function requireLevel(IUser $user, ResourceType $resource, PermissionLevel $required): void {
		$level = $this->permissionService->getEffectivePermission($user, $resource);
		if (!$level->atLeast($required)) {
			throw new OCSForbiddenException("Requires at least '{$required->value}' on '{$resource->value}'");
		}
	}

	/** @throws OCSBadRequestException */
	private static function parseResource(string $resourceType): ResourceType {
		$resource = ResourceType::tryFrom($resourceType);
		if ($resource === null) {
			throw new OCSBadRequestException("Unknown resourceType: $resourceType");
		}
		return $resource;
	}

	#[NoAdminRequired]
	public function calendars(): DataResponse {
		return new DataResponse($this->calendarService->listCalendars($this->requireUser()));
	}

	/**
	 * @throws OCSBadRequestException|OCSForbiddenException|OCSPreconditionFailedException
	 */
	#[NoAdminRequired]
	public function createEvent(
		string $calendarUri,
		string $resourceType,
		string $resourceId,
		string $summary,
		string $start,
		string $end,
		?string $description = null,
		?string $assignedUserId = null,
	): DataResponse {
		$user = $this->requireUser();
		$resource = self::parseResource($resourceType);
		$this->requireLevel($user, $resource, PermissionLevel::Write);

		try {
			$startDt = new DateTimeImmutable($start);
			$endDt = new DateTimeImmutable($end);
		} catch (\Exception $e) {
			throw new OCSBadRequestException('start/end must be valid date-time strings: ' . $e->getMessage());
		}

		try {
			$link = $this->calendarService->createEvent($user, $calendarUri, $resourceType, $resourceId, $summary, $startDt, $endDt, $description, $assignedUserId);
		} catch (\InvalidArgumentException|\OutOfBoundsException $e) {
			throw new OCSBadRequestException($e->getMessage());
		} catch (\DomainException $e) {
			// Terminkollision beim zugewiesenen Mitarbeiter (ADR-0020) —
			// dasselbe 412-Muster wie bei anderen Geschäftsregel-Ablehnungen
			// in diesem Projekt (z. B. DeliveryNoteController).
			throw new OCSPreconditionFailedException($e->getMessage());
		}

		return new DataResponse($link);
	}

	/**
	 * @throws OCSBadRequestException|OCSForbiddenException
	 */
	#[NoAdminRequired]
	public function links(string $resourceType, string $resourceId): DataResponse {
		$user = $this->requireUser();
		$resource = self::parseResource($resourceType);
		$this->requireLevel($user, $resource, PermissionLevel::Read);

		return new DataResponse($this->calendarService->listLinks($resourceType, $resourceId));
	}
}
