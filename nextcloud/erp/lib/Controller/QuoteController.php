<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Service\PermissionService;
use OCA\ERP\Service\QuoteService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\IRequest;
use OCP\IUserSession;

class QuoteController extends AbstractResourceController {
	private const VALID_POSITION_TYPES = ['article', 'product', 'labor', 'custom'];
	private const VALID_STATUSES = ['draft', 'sent', 'accepted', 'rejected', 'expired'];

	public function __construct(
		string $appName,
		IRequest $request,
		private QuoteService $quoteService,
		PermissionService $permissionService,
		IUserSession $userSession,
	) {
		parent::__construct($appName, $request, $permissionService, $userSession);
	}

	protected function resource(): ResourceType {
		return ResourceType::Angebote;
	}

	#[NoAdminRequired]
	public function index(?string $status = null): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		return new DataResponse($this->quoteService->listQuotes($status));
	}

	/** @throws OCSNotFoundException */
	#[NoAdminRequired]
	public function show(int $id): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		try {
			return new DataResponse($this->quoteService->getFullQuote($id));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Quote $id not found");
		}
	}

	/** @throws OCSBadRequestException */
	#[NoAdminRequired]
	public function create(string $title, ?int $projectId = null, ?string $customerContactUid = null, ?string $notes = null): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if (trim($title) === '') {
			throw new OCSBadRequestException('title must not be empty');
		}
		return new DataResponse($this->quoteService->createQuote($title, $projectId, $customerContactUid, $notes));
	}

	/** @throws OCSBadRequestException|OCSNotFoundException */
	#[NoAdminRequired]
	public function update(
		int $id,
		string $title,
		string $status,
		?int $projectId = null,
		?string $customerContactUid = null,
		?int $validUntil = null,
		?string $notes = null,
	): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if (trim($title) === '') {
			throw new OCSBadRequestException('title must not be empty');
		}
		if (!in_array($status, self::VALID_STATUSES, true)) {
			throw new OCSBadRequestException('Unknown status: ' . $status);
		}
		try {
			return new DataResponse($this->quoteService->updateQuote($id, $title, $status, $projectId, $customerContactUid, $validUntil, $notes));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Quote $id not found");
		}
	}

	/** @throws OCSBadRequestException|OCSNotFoundException */
	#[NoAdminRequired]
	public function addGroup(int $quoteId, string $title): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if (trim($title) === '') {
			throw new OCSBadRequestException('title must not be empty');
		}
		try {
			return new DataResponse($this->quoteService->addGroup($quoteId, $title));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Quote $quoteId not found");
		}
	}

	/** @throws OCSBadRequestException|OCSNotFoundException */
	#[NoAdminRequired]
	public function addPosition(
		int $quoteId,
		string $positionType,
		string $description,
		float $quantity,
		float $unitPriceNet,
		float $vatRatePercent,
		?int $groupId = null,
		?int $referenceId = null,
		string $unit = 'Stk',
	): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if (!in_array($positionType, self::VALID_POSITION_TYPES, true)) {
			throw new OCSBadRequestException('Unknown positionType: ' . $positionType);
		}
		if (trim($description) === '') {
			throw new OCSBadRequestException('description must not be empty');
		}
		try {
			return new DataResponse($this->quoteService->addPosition(
				$quoteId,
				$groupId,
				$positionType,
				$referenceId,
				$description,
				$quantity,
				$unit,
				$unitPriceNet,
				$vatRatePercent,
			));
		} catch (\OutOfBoundsException $e) {
			throw new OCSNotFoundException($e->getMessage());
		}
	}

	/** @throws OCSNotFoundException */
	#[NoAdminRequired]
	public function removePosition(int $quoteId, int $id): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		try {
			$this->quoteService->removePosition($quoteId, $id);
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Position $id not found");
		}
		return new DataResponse([]);
	}
}
