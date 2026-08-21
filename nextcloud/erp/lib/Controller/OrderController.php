<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Projects\OrderStatus;
use OCA\ERP\Service\OrderService;
use OCA\ERP\Service\PermissionService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

/** Aufträge — eigener Rechtebereich ResourceType::Auftraege (ADR-0010, seit ADR-0016 mit Positionen). */
class OrderController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private OrderService $orderService,
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
		$level = $this->permissionService->getEffectivePermission($user, ResourceType::Auftraege);
		if (!$level->atLeast($required)) {
			throw new OCSForbiddenException("Requires at least '{$required->value}' on 'auftraege'");
		}
	}

	#[NoAdminRequired]
	public function index(int $projectId): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		return new DataResponse($this->orderService->listOrders($projectId));
	}

	/**
	 * Flache Detailansicht (Positionen + Berechnung) — ohne bekannte
	 * projectId im Pfad, analog zu quote#show.
	 *
	 * @throws OCSNotFoundException
	 */
	#[NoAdminRequired]
	public function show(int $id): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		try {
			return new DataResponse($this->orderService->getFullOrder($id));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Order $id not found");
		}
	}

	/** @throws OCSBadRequestException */
	#[NoAdminRequired]
	public function create(int $projectId, string $title, ?string $description = null, ?string $customerContactUid = null): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if (trim($title) === '') {
			throw new OCSBadRequestException('title must not be empty');
		}
		return new DataResponse($this->orderService->createOrder($projectId, $title, $description, $customerContactUid));
	}

	/** @throws OCSBadRequestException|OCSNotFoundException */
	#[NoAdminRequired]
	public function update(int $projectId, int $id, string $title, string $status, ?string $description = null, ?string $customerContactUid = null): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if (trim($title) === '') {
			throw new OCSBadRequestException('title must not be empty');
		}
		$parsedStatus = OrderStatus::tryFrom($status);
		if ($parsedStatus === null) {
			throw new OCSBadRequestException("Unknown status: $status");
		}
		try {
			return new DataResponse($this->orderService->updateOrder($projectId, $id, $title, $parsedStatus, $description, $customerContactUid));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Order $id not found");
		}
	}

	/**
	 * Auftrag aus einem angenommenen Angebot (ADR-0016) — kopiert Titel,
	 * Kunde und alle Positionen 1:1.
	 *
	 * @throws OCSNotFoundException
	 */
	#[NoAdminRequired]
	public function createFromQuote(int $quoteId, ?string $title = null): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		try {
			return new DataResponse($this->orderService->createFromQuote($quoteId, $title));
		} catch (\OutOfBoundsException $e) {
			throw new OCSNotFoundException($e->getMessage());
		}
	}

	/** @throws OCSBadRequestException|OCSNotFoundException */
	#[NoAdminRequired]
	public function addPosition(
		int $orderId,
		string $positionType,
		string $description,
		float $quantity,
		float $unitPriceNet,
		float $vatRatePercent,
		?int $referenceId = null,
		string $unit = 'Stk',
	): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if (trim($description) === '') {
			throw new OCSBadRequestException('description must not be empty');
		}
		try {
			return new DataResponse($this->orderService->addPosition($orderId, $positionType, $referenceId, $description, $quantity, $unit, $unitPriceNet, $vatRatePercent));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Order $orderId not found");
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage());
		}
	}

	/** @throws OCSNotFoundException */
	#[NoAdminRequired]
	public function removePosition(int $orderId, int $id): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		try {
			$this->orderService->removePosition($orderId, $id);
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Position $id not found in order $orderId");
		}
		return new DataResponse([]);
	}
}
