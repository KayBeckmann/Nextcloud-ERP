<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Service\PermissionService;
use OCA\ERP\Service\XRechnungService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * XRechnung-XML- und ZUGFeRD-PDF-Download für ausgestellte Rechnungen
 * (ADR-0040). Eigener, schlanker Nicht-OCS-Controller wie
 * ReportExportController — ein roher Datei-Download passt nicht zur sonst
 * durchgehenden OCS/JSON-API.
 */
class EInvoiceController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private XRechnungService $xRechnungService,
		private PermissionService $permissionService,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	// NoCSRFRequired wie ReportExportController::invoicesCsv() — reiner
	// GET-Lesezugriff ohne Zustandsänderung.
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function xml(int $id): DataDownloadResponse|DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['error' => 'No active user session'], Http::STATUS_FORBIDDEN);
		}
		$level = $this->permissionService->getEffectivePermission($user, ResourceType::Rechnungen);
		if (!$level->atLeast(PermissionLevel::Read)) {
			return new DataResponse(['error' => "Requires at least 'read' on 'rechnungen'"], Http::STATUS_FORBIDDEN);
		}

		try {
			$xml = $this->xRechnungService->generateXml($id);
		} catch (\DomainException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\RuntimeException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataDownloadResponse($xml, "xrechnung-$id.xml", 'application/xml');
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function pdf(int $id): DataDownloadResponse|DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['error' => 'No active user session'], Http::STATUS_FORBIDDEN);
		}
		$level = $this->permissionService->getEffectivePermission($user, ResourceType::Rechnungen);
		if (!$level->atLeast(PermissionLevel::Read)) {
			return new DataResponse(['error' => "Requires at least 'read' on 'rechnungen'"], Http::STATUS_FORBIDDEN);
		}

		try {
			$pdf = $this->xRechnungService->generateZugferdPdf($id, $user);
		} catch (\DomainException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\RuntimeException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataDownloadResponse($pdf, "zugferd-$id.pdf", 'application/pdf');
	}
}
