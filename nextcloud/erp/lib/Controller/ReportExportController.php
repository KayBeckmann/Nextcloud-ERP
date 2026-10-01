<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Service\DatevExportService;
use OCA\ERP\Service\PermissionService;
use OCA\ERP\Service\ReportingService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * CSV-Export für Steuerberater/Buchhaltung (Roadmap Phase 11, ADR-0019).
 * Eigener, schlanker Nicht-OCS-Controller (wie PageController) statt
 * OCSController — ein roher Datei-Download mit Content-Disposition passt
 * nicht zur sonst durchgehenden OCS/JSON-API, deshalb auch das Rechte-Gate
 * manuell statt über AbstractResourceController (dessen
 * OCSForbiddenException hier nicht passen würde).
 */
class ReportExportController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private ReportingService $reportingService,
		private DatevExportService $datevExportService,
		private PermissionService $permissionService,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	// NoCSRFRequired wie bei PageController::index() — reiner GET-Lesezugriff
	// ohne Zustandsänderung, ein CSRF-Token wäre für einen einfachen
	// Datei-Download-Link (z. B. per Browser-Adresszeile) unpraktikabel.
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function invoicesCsv(?string $from = null, ?string $to = null, ?string $status = null): DataDownloadResponse|DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['error' => 'No active user session'], Http::STATUS_FORBIDDEN);
		}
		$level = $this->permissionService->getEffectivePermission($user, ResourceType::Dashboard);
		if (!$level->atLeast(PermissionLevel::Read)) {
			return new DataResponse(['error' => "Requires at least 'read' on 'dashboard'"], Http::STATUS_FORBIDDEN);
		}

		$csv = $this->reportingService->exportInvoicesCsv($from, $to, $status);
		return new DataDownloadResponse($csv, 'rechnungen-' . date('Y-m-d') . '.csv', 'text/csv');
	}

	/**
	 * DATEV-Buchungsstapel für ausgestellte Rechnungen im Zeitraum
	 * [from, to] (ADR-0026). beraternummer/mandantennummer sind
	 * DATEV-Pflichtangaben ohne sinnvollen Default — bewusst Pflicht-
	 * Query-Parameter statt stiller Platzhalterwerte.
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function datevBuchungsstapelCsv(
		string $from,
		string $to,
		int $beraternummer,
		int $mandantennummer,
		?int $debtorAccount = null,
		?string $wjBeginn = null,
		int $sachkontenlaenge = 4,
	): DataDownloadResponse|DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['error' => 'No active user session'], Http::STATUS_FORBIDDEN);
		}
		$level = $this->permissionService->getEffectivePermission($user, ResourceType::Dashboard);
		if (!$level->atLeast(PermissionLevel::Read)) {
			return new DataResponse(['error' => "Requires at least 'read' on 'dashboard'"], Http::STATUS_FORBIDDEN);
		}

		try {
			$csv = $this->datevExportService->exportBuchungsstapel(
				$from,
				$to,
				$beraternummer,
				$mandantennummer,
				$user->getUID(),
				$debtorAccount,
				$wjBeginn,
				$sachkontenlaenge,
			);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		return new DataDownloadResponse($csv, "EXTF_Buchungsstapel_{$from}_{$to}.csv", 'text/csv');
	}
}
