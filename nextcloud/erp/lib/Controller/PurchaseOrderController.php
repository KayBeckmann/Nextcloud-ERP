<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Service\PermissionService;
use OCA\ERP\Service\PurchaseOrderService;
use OCA\ERP\Service\PurchaseOrderDocumentService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCS\OCSPreconditionFailedException;
use OCP\IRequest;
use OCP\IUserSession;

/** Lieferantenbestellungen und bewusste Wareneingänge (Roadmap P1). */
class PurchaseOrderController extends AbstractResourceController {
	public function __construct(
		string $appName,
		IRequest $request,
		private PurchaseOrderService $purchaseOrderService,
		private PurchaseOrderDocumentService $purchaseOrderDocumentService,
		private IRootFolder $rootFolder,
		PermissionService $permissionService,
		IUserSession $userSession,
	) {
		parent::__construct($appName, $request, $permissionService, $userSession);
	}

	protected function resource(): ResourceType {
		return ResourceType::Lager;
	}

	#[NoAdminRequired]
	public function index(): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		return new DataResponse($this->purchaseOrderService->listAll());
	}

	/** @throws OCSNotFoundException */
	#[NoAdminRequired]
	public function show(int $id): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		try {
			return new DataResponse($this->purchaseOrderService->get($id));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Purchase order $id not found");
		}
	}

	/** @param list<array<string,mixed>> $positions @throws OCSBadRequestException */
	#[NoAdminRequired]
	public function create(string $supplierContactUid, array $positions, ?string $supplierReference = null, ?string $notes = null): DataResponse {
		$user = $this->requireLevel(PermissionLevel::Write);
		try {
			return new DataResponse($this->purchaseOrderService->createDraft($supplierContactUid, $positions, $user->getUID(), $supplierReference, $notes));
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage());
		}
	}

	/** @param list<array<string,mixed>> $selections @throws OCSBadRequestException */
	#[NoAdminRequired]
	public function createFromSuggestions(array $selections): DataResponse {
		$user = $this->requireLevel(PermissionLevel::Write);
		try {
			return new DataResponse($this->purchaseOrderService->createDraftsFromSuggestions($selections, $user->getUID()));
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage());
		}
	}

	/** @throws OCSBadRequestException|OCSNotFoundException|OCSPreconditionFailedException */
	#[NoAdminRequired]
	public function transition(int $id, string $status, ?string $notes = null): DataResponse {
		$user = $this->requireLevel(PermissionLevel::Write);
		try {
			return new DataResponse($this->purchaseOrderService->transitionStatus($id, $status, $user->getUID(), $notes));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Purchase order $id not found");
		} catch (\DomainException $e) {
			throw new OCSPreconditionFailedException($e->getMessage());
		}
	}

	/** @throws OCSBadRequestException|OCSNotFoundException|OCSPreconditionFailedException */
	#[NoAdminRequired]
	public function receive(int $positionId, float $quantity, int $warehouseId, ?string $notes = null): DataResponse {
		$user = $this->requireLevel(PermissionLevel::Write);
		try {
			return new DataResponse($this->purchaseOrderService->receive($positionId, $quantity, $warehouseId, $user->getUID(), $notes));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Purchase order position $positionId not found");
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage());
		} catch (\DomainException $e) {
			throw new OCSPreconditionFailedException($e->getMessage());
		}
	}

	/** @throws OCSNotFoundException */
	#[NoAdminRequired]
	public function prepareDocument(int $id): DataResponse {
		$user = $this->requireLevel(PermissionLevel::Write);
		try {
			$order = $this->purchaseOrderDocumentService->prepare($id, $user);
			return new DataResponse(['documentPrepared' => true]);
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Purchase order $id not found");
		}
	}

	/** Protected retrieval: the file ID is read from the authorized purchase-order record only. */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function document(int $id): FileDisplayResponse|DataResponse {
		$user = $this->requireLevel(PermissionLevel::Read);
		try {
			$order = $this->purchaseOrderService->get($id)['order'];
		} catch (\OutOfBoundsException) {
			return new DataResponse(['error' => 'Purchase order not found'], Http::STATUS_NOT_FOUND);
		}
		$fileId = $order->getDocumentFileId();
		if ($fileId === null) {
			return new DataResponse(['error' => 'Document not prepared'], Http::STATUS_NOT_FOUND);
		}
		try {
			$node = $this->rootFolder->getUserFolder($user->getUID())->getById($fileId)[0] ?? null;
		} catch (NotFoundException) {
			$node = null;
		}
		if (!$this->isOwnedPurchaseOrderPdf($node, $id)) {
			return new DataResponse(['error' => 'Document not found'], Http::STATUS_NOT_FOUND);
		}
		return new FileDisplayResponse($node, Http::STATUS_OK, ['Content-Type' => 'application/pdf']);
	}

	private function isOwnedPurchaseOrderPdf(mixed $node, int $purchaseOrderId): bool {
		if (!($node instanceof File) || $node->getMimeType() !== 'application/pdf') {
			return false;
		}
		$number = sprintf('PO-%05d', $purchaseOrderId);
		return (bool)preg_match('#/ERP-Firma/ERP/Lieferanten/Bestellungen/' . preg_quote($number, '#') . '_[^/]+\\.pdf$#', $node->getPath());
	}
}
