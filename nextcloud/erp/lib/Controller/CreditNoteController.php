<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Service\CreditNoteService;
use OCA\ERP\Service\PermissionService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCS\OCSPreconditionFailedException;
use OCP\IRequest;
use OCP\IUserSession;

/** Gutschriften (ADR-0013, Gate: Rechnungen). */
class CreditNoteController extends AbstractResourceController {
	public function __construct(
		string $appName,
		IRequest $request,
		private CreditNoteService $creditNoteService,
		PermissionService $permissionService,
		IUserSession $userSession,
	) {
		parent::__construct($appName, $request, $permissionService, $userSession);
	}

	protected function resource(): ResourceType {
		return ResourceType::Rechnungen;
	}

	#[NoAdminRequired]
	public function index(int $invoiceId): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		return new DataResponse($this->creditNoteService->listForInvoice($invoiceId));
	}

	/** @throws OCSNotFoundException */
	#[NoAdminRequired]
	public function show(int $id): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		try {
			return new DataResponse($this->creditNoteService->getFull($id));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Credit note $id not found");
		}
	}

	/** @throws OCSBadRequestException|OCSNotFoundException */
	#[NoAdminRequired]
	public function createFullCancellation(int $invoiceId, string $reason): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if (trim($reason) === '') {
			throw new OCSBadRequestException('reason must not be empty');
		}
		try {
			return new DataResponse($this->creditNoteService->createFullCancellation($invoiceId, $reason));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Invoice $invoiceId not found");
		}
	}

	/** @throws OCSBadRequestException|OCSNotFoundException */
	#[NoAdminRequired]
	public function createPartial(int $invoiceId, string $reason): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if (trim($reason) === '') {
			throw new OCSBadRequestException('reason must not be empty');
		}
		try {
			return new DataResponse($this->creditNoteService->createPartial($invoiceId, $reason));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Invoice $invoiceId not found");
		}
	}

	/** @throws OCSBadRequestException|OCSNotFoundException|OCSPreconditionFailedException */
	#[NoAdminRequired]
	public function addPosition(int $creditNoteId, string $description, float $quantity, float $unitPriceNet, float $vatRatePercent, string $unit = 'Stk'): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if (trim($description) === '') {
			throw new OCSBadRequestException('description must not be empty');
		}
		try {
			return new DataResponse($this->creditNoteService->addPosition($creditNoteId, $description, $quantity, $unit, $unitPriceNet, $vatRatePercent));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Credit note $creditNoteId not found");
		} catch (\DomainException $e) {
			throw new OCSPreconditionFailedException($e->getMessage());
		}
	}

	/** @throws OCSNotFoundException|OCSPreconditionFailedException */
	#[NoAdminRequired]
	public function issue(int $id): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		try {
			return new DataResponse($this->creditNoteService->issue($id));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Credit note $id not found");
		} catch (\DomainException $e) {
			throw new OCSPreconditionFailedException($e->getMessage());
		}
	}
}
