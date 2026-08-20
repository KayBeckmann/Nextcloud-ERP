<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Service\ArticleService;
use OCA\ERP\Service\PermissionService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\IRequest;
use OCP\IUserSession;

class ArticleController extends AbstractResourceController {
	public function __construct(
		string $appName,
		IRequest $request,
		private ArticleService $articleService,
		PermissionService $permissionService,
		IUserSession $userSession,
	) {
		parent::__construct($appName, $request, $permissionService, $userSession);
	}

	protected function resource(): ResourceType {
		return ResourceType::Artikel;
	}

	#[NoAdminRequired]
	public function index(): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		return new DataResponse($this->articleService->listAll());
	}

	/** @throws OCSNotFoundException */
	#[NoAdminRequired]
	public function show(int $id): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		try {
			return new DataResponse($this->articleService->getWithPrices($id));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Article $id not found");
		}
	}

	/** @throws OCSBadRequestException */
	#[NoAdminRequired]
	public function create(
		string $name,
		?string $manufacturer = null,
		?string $manufacturerArticleNo = null,
		string $unit = 'Stk',
		?string $category = null,
		?int $vatRateId = null,
		?string $notes = null,
	): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if (trim($name) === '') {
			throw new OCSBadRequestException('name must not be empty');
		}
		return new DataResponse($this->articleService->create($name, $manufacturer, $manufacturerArticleNo, $unit, $category, $vatRateId, $notes));
	}

	/** @throws OCSBadRequestException|OCSNotFoundException */
	#[NoAdminRequired]
	public function update(
		int $id,
		string $name,
		?string $manufacturer = null,
		?string $manufacturerArticleNo = null,
		string $unit = 'Stk',
		?string $category = null,
		?int $vatRateId = null,
		?string $notes = null,
	): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if (trim($name) === '') {
			throw new OCSBadRequestException('name must not be empty');
		}
		try {
			return new DataResponse($this->articleService->update($id, $name, $manufacturer, $manufacturerArticleNo, $unit, $category, $vatRateId, $notes));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Article $id not found");
		}
	}

	/** @throws OCSBadRequestException|OCSNotFoundException */
	#[NoAdminRequired]
	public function addSupplierPrice(
		int $articleId,
		string $supplierContactUid,
		float $purchasePrice,
		?string $supplierArticleNo = null,
		string $currency = 'EUR',
		?float $minOrderQuantity = null,
		?string $deliveryTime = null,
	): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if (trim($supplierContactUid) === '') {
			throw new OCSBadRequestException('supplierContactUid must not be empty');
		}
		try {
			return new DataResponse($this->articleService->addSupplierPrice(
				$articleId,
				$supplierContactUid,
				$supplierArticleNo,
				$purchasePrice,
				$currency,
				$minOrderQuantity,
				$deliveryTime,
			));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Article $articleId not found");
		}
	}

	/** @throws OCSNotFoundException */
	#[NoAdminRequired]
	public function removeSupplierPrice(int $articleId, int $priceId): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		try {
			$this->articleService->removeSupplierPrice($articleId, $priceId);
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Supplier price $priceId not found");
		}
		return new DataResponse([]);
	}
}
