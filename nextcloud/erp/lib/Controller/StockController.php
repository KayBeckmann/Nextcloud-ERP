<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Service\PermissionService;
use OCA\ERP\Service\PurchaseSuggestionService;
use OCA\ERP\Service\StockService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSPreconditionFailedException;
use OCP\IRequest;
use OCP\IUserSession;

/** Bestände, Bewegungen und Bestellvorschläge (ADR-0014, Gate: Lager). */
class StockController extends AbstractResourceController {
	public function __construct(
		string $appName,
		IRequest $request,
		private StockService $stockService,
		private PurchaseSuggestionService $purchaseSuggestionService,
		PermissionService $permissionService,
		IUserSession $userSession,
	) {
		parent::__construct($appName, $request, $permissionService, $userSession);
	}

	protected function resource(): ResourceType {
		return ResourceType::Lager;
	}

	#[NoAdminRequired]
	public function index(?int $warehouseId = null, ?int $articleId = null): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		if ($warehouseId !== null) {
			return new DataResponse($this->stockService->listForWarehouse($warehouseId));
		}
		if ($articleId !== null) {
			return new DataResponse($this->stockService->listForArticle($articleId));
		}
		return new DataResponse($this->stockService->listAllLevels());
	}

	#[NoAdminRequired]
	public function movements(int $articleId, int $warehouseId): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		return new DataResponse($this->stockService->listMovements($articleId, $warehouseId));
	}

	/** @throws OCSBadRequestException */
	#[NoAdminRequired]
	public function setMinQuantity(int $articleId, int $warehouseId, float $minQuantity): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		try {
			return new DataResponse($this->stockService->setMinQuantity($articleId, $warehouseId, $minQuantity));
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage());
		}
	}

	/** @throws OCSBadRequestException|OCSPreconditionFailedException */
	#[NoAdminRequired]
	public function recordMovement(
		int $articleId,
		int $warehouseId,
		float $quantityDelta,
		string $movementType,
		?string $referenceType = null,
		?int $referenceId = null,
		?string $notes = null,
	): DataResponse {
		$user = $this->requireLevel(PermissionLevel::Write);
		try {
			return new DataResponse($this->stockService->recordMovement(
				$articleId,
				$warehouseId,
				$quantityDelta,
				$movementType,
				$referenceType,
				$referenceId,
				$user->getUID(),
				$notes,
			));
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage());
		} catch (\DomainException $e) {
			throw new OCSPreconditionFailedException($e->getMessage());
		}
	}

	/** @throws OCSBadRequestException|OCSPreconditionFailedException */
	#[NoAdminRequired]
	public function transfer(int $articleId, int $fromWarehouseId, int $toWarehouseId, float $quantity, ?string $notes = null): DataResponse {
		$user = $this->requireLevel(PermissionLevel::Write);
		try {
			$this->stockService->transfer($articleId, $fromWarehouseId, $toWarehouseId, $quantity, $user->getUID(), $notes);
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage());
		} catch (\DomainException $e) {
			throw new OCSPreconditionFailedException($e->getMessage());
		}
		return new DataResponse([]);
	}

	/** @throws OCSBadRequestException */
	#[NoAdminRequired]
	public function reserve(int $articleId, int $warehouseId, float $quantity): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		try {
			return new DataResponse($this->stockService->reserve($articleId, $warehouseId, $quantity));
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage());
		}
	}

	/** @throws OCSBadRequestException|OCSPreconditionFailedException */
	#[NoAdminRequired]
	public function release(int $articleId, int $warehouseId, float $quantity): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		try {
			return new DataResponse($this->stockService->release($articleId, $warehouseId, $quantity));
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage());
		} catch (\DomainException $e) {
			throw new OCSPreconditionFailedException($e->getMessage());
		}
	}

	#[NoAdminRequired]
	public function purchaseSuggestions(?int $warehouseId = null): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		return new DataResponse($this->purchaseSuggestionService->suggestions($warehouseId));
	}
}
