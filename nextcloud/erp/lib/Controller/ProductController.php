<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Service\PermissionService;
use OCA\ERP\Service\ProductService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\IRequest;
use OCP\IUserSession;

class ProductController extends AbstractResourceController {
	public function __construct(
		string $appName,
		IRequest $request,
		private ProductService $productService,
		PermissionService $permissionService,
		IUserSession $userSession,
	) {
		parent::__construct($appName, $request, $permissionService, $userSession);
	}

	protected function resource(): ResourceType {
		return ResourceType::Produkte;
	}

	#[NoAdminRequired]
	public function index(): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		return new DataResponse($this->productService->listAll());
	}

	/** @throws OCSNotFoundException */
	#[NoAdminRequired]
	public function show(int $id): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		try {
			return new DataResponse($this->productService->getWithComponents($id));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Product $id not found");
		}
	}

	/** @throws OCSBadRequestException */
	#[NoAdminRequired]
	public function create(string $name, ?string $description = null, ?int $vatRateId = null, ?string $notes = null): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if (trim($name) === '') {
			throw new OCSBadRequestException('name must not be empty');
		}
		return new DataResponse($this->productService->create($name, $description, $vatRateId, $notes));
	}

	/** @throws OCSBadRequestException|OCSNotFoundException */
	#[NoAdminRequired]
	public function update(int $id, string $name, ?string $description = null, ?int $vatRateId = null, ?string $notes = null): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if (trim($name) === '') {
			throw new OCSBadRequestException('name must not be empty');
		}
		try {
			return new DataResponse($this->productService->update($id, $name, $description, $vatRateId, $notes));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Product $id not found");
		}
	}

	/** @throws OCSNotFoundException */
	#[NoAdminRequired]
	public function addComponent(int $productId, int $articleId, float $quantity = 1.0, string $unit = 'Stk'): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		try {
			return new DataResponse($this->productService->addComponent($productId, $articleId, $quantity, $unit));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Product $productId not found");
		}
	}

	/** @throws OCSNotFoundException */
	#[NoAdminRequired]
	public function removeComponent(int $productId, int $id): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		try {
			$this->productService->removeComponent($productId, $id);
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Component $id not found");
		}
		return new DataResponse([]);
	}

	/** @throws OCSNotFoundException */
	#[NoAdminRequired]
	public function addLabor(int $productId, int $workTypeId, float $hours): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		try {
			return new DataResponse($this->productService->addLabor($productId, $workTypeId, $hours));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Product $productId not found");
		}
	}

	/** @throws OCSNotFoundException */
	#[NoAdminRequired]
	public function removeLabor(int $productId, int $id): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		try {
			$this->productService->removeLabor($productId, $id);
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Labor entry $id not found");
		}
		return new DataResponse([]);
	}
}
