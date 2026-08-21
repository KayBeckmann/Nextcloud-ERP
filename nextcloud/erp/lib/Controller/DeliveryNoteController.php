<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Service\DeliveryNoteService;
use OCA\ERP\Service\PermissionService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCS\OCSPreconditionFailedException;
use OCP\IRequest;
use OCP\IUserSession;

/** Lieferscheine (ADR-0015, Gate: Lieferscheine). */
class DeliveryNoteController extends AbstractResourceController {
	public function __construct(
		string $appName,
		IRequest $request,
		private DeliveryNoteService $deliveryNoteService,
		PermissionService $permissionService,
		IUserSession $userSession,
	) {
		parent::__construct($appName, $request, $permissionService, $userSession);
	}

	protected function resource(): ResourceType {
		return ResourceType::Lieferscheine;
	}

	#[NoAdminRequired]
	public function index(int $projectId): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		return new DataResponse($this->deliveryNoteService->listForProject($projectId));
	}

	/** @throws OCSNotFoundException */
	#[NoAdminRequired]
	public function show(int $id): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		try {
			return new DataResponse($this->deliveryNoteService->getFull($id));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Delivery note $id not found");
		}
	}

	/** @throws OCSBadRequestException */
	#[NoAdminRequired]
	public function create(int $projectId, ?int $orderId = null, ?string $notes = null): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		try {
			return new DataResponse($this->deliveryNoteService->createDraft($projectId, $orderId, $notes));
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage());
		}
	}

	/**
	 * Lieferschein aus ausgewählten Auftragspositionen (ADR-0016) — nur
	 * Artikel/Produkt, keine Arbeitsstunden.
	 *
	 * @param array<int, array{orderPositionId: int, quantity: float}> $positions
	 * @throws OCSBadRequestException|OCSNotFoundException|OCSPreconditionFailedException
	 */
	#[NoAdminRequired]
	public function createFromOrder(int $orderId, array $positions, ?string $notes = null): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		try {
			return new DataResponse($this->deliveryNoteService->createFromOrder($orderId, $positions, $notes));
		} catch (\OutOfBoundsException $e) {
			throw new OCSNotFoundException($e->getMessage());
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage());
		} catch (\DomainException $e) {
			throw new OCSPreconditionFailedException($e->getMessage());
		}
	}

	/** @throws OCSBadRequestException|OCSNotFoundException|OCSPreconditionFailedException */
	#[NoAdminRequired]
	public function addPosition(int $deliveryNoteId, string $positionType, string $description, float $quantity, ?int $referenceId = null, string $unit = 'Stk'): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if (trim($description) === '') {
			throw new OCSBadRequestException('description must not be empty');
		}
		try {
			return new DataResponse($this->deliveryNoteService->addPosition($deliveryNoteId, $positionType, $referenceId, $description, $quantity, $unit));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Delivery note $deliveryNoteId not found");
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage());
		} catch (\DomainException $e) {
			throw new OCSPreconditionFailedException($e->getMessage());
		}
	}

	/** @throws OCSNotFoundException|OCSPreconditionFailedException */
	#[NoAdminRequired]
	public function removePosition(int $deliveryNoteId, int $id): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		try {
			$this->deliveryNoteService->removePosition($deliveryNoteId, $id);
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Position $id not found in delivery note $deliveryNoteId");
		} catch (\DomainException $e) {
			throw new OCSPreconditionFailedException($e->getMessage());
		}
		return new DataResponse([]);
	}

	/** @throws OCSNotFoundException|OCSPreconditionFailedException */
	#[NoAdminRequired]
	public function issue(int $id): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		try {
			return new DataResponse($this->deliveryNoteService->issue($id));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Delivery note $id not found");
		} catch (\DomainException $e) {
			throw new OCSPreconditionFailedException($e->getMessage());
		}
	}
}
