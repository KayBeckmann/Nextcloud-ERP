<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Service\InvoiceService;
use OCA\ERP\Service\PermissionService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCS\OCSPreconditionFailedException;
use OCP\IRequest;
use OCP\IUserSession;

/** Rechnungen (ADR-0013, Gate: Rechnungen). */
class InvoiceController extends AbstractResourceController {
	private const VALID_POSITION_TYPES = ['article', 'product', 'labor', 'custom'];
	private const VALID_TYPES = ['invoice', 'partial', 'final'];

	public function __construct(
		string $appName,
		IRequest $request,
		private InvoiceService $invoiceService,
		PermissionService $permissionService,
		IUserSession $userSession,
	) {
		parent::__construct($appName, $request, $permissionService, $userSession);
	}

	protected function resource(): ResourceType {
		return ResourceType::Rechnungen;
	}

	#[NoAdminRequired]
	public function index(?string $status = null, ?int $projectId = null): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		return new DataResponse($this->invoiceService->listInvoices($status, $projectId));
	}

	/** @throws OCSNotFoundException */
	#[NoAdminRequired]
	public function show(int $id): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		try {
			return new DataResponse($this->invoiceService->getFullInvoice($id));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Invoice $id not found");
		}
	}

	/** @throws OCSBadRequestException */
	#[NoAdminRequired]
	public function create(
		string $title,
		int $projectId,
		string $type = 'invoice',
		?int $orderId = null,
		?string $customerContactUid = null,
		?string $dueDate = null,
		?string $notes = null,
	): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if (trim($title) === '') {
			throw new OCSBadRequestException('title must not be empty');
		}
		if (!in_array($type, self::VALID_TYPES, true)) {
			throw new OCSBadRequestException('Unknown type: ' . $type);
		}
		try {
			return new DataResponse($this->invoiceService->createDraft($title, $type, $projectId, $orderId, $customerContactUid, $dueDate, $notes));
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage());
		}
	}

	/** @throws OCSBadRequestException|OCSNotFoundException */
	#[NoAdminRequired]
	public function createFromQuote(int $quoteId, string $title, string $type = 'invoice', ?string $dueDate = null, ?string $notes = null): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if (trim($title) === '') {
			throw new OCSBadRequestException('title must not be empty');
		}
		if (!in_array($type, self::VALID_TYPES, true)) {
			throw new OCSBadRequestException('Unknown type: ' . $type);
		}
		try {
			return new DataResponse($this->invoiceService->createFromQuote($quoteId, $title, $type, $dueDate, $notes));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Quote $quoteId not found");
		}
	}

	/**
	 * Rechnung aus ausgewählten Auftragspositionen (ADR-0016) — mit
	 * `type='partial'` und einer Teilauswahl entsteht eine Teilrechnung.
	 *
	 * @param array<int, array{orderPositionId: int, quantity?: float}> $positions
	 * @throws OCSBadRequestException|OCSNotFoundException
	 */
	#[NoAdminRequired]
	public function createFromOrder(int $orderId, string $title, string $type = 'invoice', ?string $dueDate = null, ?string $notes = null, array $positions = []): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if (trim($title) === '') {
			throw new OCSBadRequestException('title must not be empty');
		}
		if (!in_array($type, self::VALID_TYPES, true)) {
			throw new OCSBadRequestException('Unknown type: ' . $type);
		}
		try {
			return new DataResponse($this->invoiceService->createFromOrder($orderId, $title, $type, $dueDate, $notes, $positions));
		} catch (\OutOfBoundsException $e) {
			throw new OCSNotFoundException($e->getMessage());
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage());
		}
	}

	/**
	 * Rechnung aus ausgewählten Lieferscheinpositionen (ADR-0016).
	 *
	 * @param array<int, array{deliveryNotePositionId: int, unitPriceNet?: float, vatRatePercent?: float}> $positions
	 * @throws OCSBadRequestException|OCSNotFoundException
	 */
	#[NoAdminRequired]
	public function createFromDeliveryNote(int $deliveryNoteId, string $title, string $type = 'invoice', ?string $dueDate = null, ?string $notes = null, array $positions = []): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if (trim($title) === '') {
			throw new OCSBadRequestException('title must not be empty');
		}
		if (!in_array($type, self::VALID_TYPES, true)) {
			throw new OCSBadRequestException('Unknown type: ' . $type);
		}
		try {
			return new DataResponse($this->invoiceService->createFromDeliveryNote($deliveryNoteId, $title, $type, $dueDate, $notes, $positions));
		} catch (\OutOfBoundsException $e) {
			throw new OCSNotFoundException($e->getMessage());
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage());
		}
	}

	/** @throws OCSBadRequestException|OCSNotFoundException */
	#[NoAdminRequired]
	public function addGroup(int $invoiceId, string $title): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if (trim($title) === '') {
			throw new OCSBadRequestException('title must not be empty');
		}
		try {
			return new DataResponse($this->invoiceService->addGroup($invoiceId, $title));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Invoice $invoiceId not found");
		}
	}

	/** @throws OCSBadRequestException|OCSNotFoundException|OCSPreconditionFailedException */
	#[NoAdminRequired]
	public function addPosition(
		int $invoiceId,
		string $positionType,
		string $description,
		float $quantity,
		float $unitPriceNet,
		float $vatRatePercent,
		?int $groupId = null,
		?int $referenceId = null,
		string $unit = 'Stk',
		float $discountPercent = 0.0,
	): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if (!in_array($positionType, self::VALID_POSITION_TYPES, true)) {
			throw new OCSBadRequestException('Unknown positionType: ' . $positionType);
		}
		if (trim($description) === '') {
			throw new OCSBadRequestException('description must not be empty');
		}
		try {
			return new DataResponse($this->invoiceService->addPosition($invoiceId, $groupId, $positionType, $referenceId, $description, $quantity, $unit, $unitPriceNet, $vatRatePercent, $discountPercent));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Invoice $invoiceId not found");
		} catch (\DomainException $e) {
			throw new OCSPreconditionFailedException($e->getMessage());
		}
	}

	/** @throws OCSBadRequestException|OCSNotFoundException|OCSPreconditionFailedException */
	#[NoAdminRequired]
	public function updatePosition(
		int $invoiceId,
		int $id,
		string $description,
		float $quantity,
		float $unitPriceNet,
		float $vatRatePercent,
		string $unit = 'Stk',
		float $discountPercent = 0.0,
	): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if (trim($description) === '') {
			throw new OCSBadRequestException('description must not be empty');
		}
		try {
			return new DataResponse($this->invoiceService->updatePosition($invoiceId, $id, $description, $quantity, $unit, $unitPriceNet, $vatRatePercent, $discountPercent));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Position $id not found in invoice $invoiceId");
		} catch (\DomainException $e) {
			throw new OCSPreconditionFailedException($e->getMessage());
		}
	}

	/** @throws OCSNotFoundException|OCSPreconditionFailedException */
	#[NoAdminRequired]
	public function removePosition(int $invoiceId, int $id): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		try {
			$this->invoiceService->removePosition($invoiceId, $id);
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Position $id not found in invoice $invoiceId");
		} catch (\DomainException $e) {
			throw new OCSPreconditionFailedException($e->getMessage());
		}
		return new DataResponse([]);
	}

	/** @throws OCSNotFoundException|OCSPreconditionFailedException */
	#[NoAdminRequired]
	public function updateDiscount(int $id, float $discountPercent): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		try {
			return new DataResponse($this->invoiceService->updateDiscount($id, $discountPercent));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Invoice $id not found");
		} catch (\DomainException $e) {
			throw new OCSPreconditionFailedException($e->getMessage());
		}
	}

	/** @throws OCSNotFoundException|OCSPreconditionFailedException */
	#[NoAdminRequired]
	public function issue(int $id): DataResponse {
		$user = $this->requireLevel(PermissionLevel::Write);
		try {
			return new DataResponse($this->invoiceService->issue($id, $user));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Invoice $id not found");
		} catch (\DomainException $e) {
			throw new OCSPreconditionFailedException($e->getMessage());
		}
	}

	/** @throws OCSBadRequestException|OCSNotFoundException|OCSPreconditionFailedException */
	#[NoAdminRequired]
	public function recordPayment(int $id, float $amount): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if ($amount <= 0) {
			throw new OCSBadRequestException('amount must be greater than 0');
		}
		try {
			return new DataResponse($this->invoiceService->recordPayment($id, $amount));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Invoice $id not found");
		} catch (\DomainException $e) {
			throw new OCSPreconditionFailedException($e->getMessage());
		}
	}
}
