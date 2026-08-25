<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Inline-Anzeige generierter Belegdokumente (Nachtrag zu Phase 12,
 * ADR-0021) — liefert ein per DocumentPdfService erzeugtes PDF mit
 * `Content-Disposition: inline` statt `attachment`, damit es sich per
 * <iframe> direkt in der Belegansicht einbetten lässt (ein einfacher
 * Link auf die Files-App reißt den Nutzer sonst aus dem ERP-Screen).
 *
 * Eigener, schlanker Nicht-OCS-Controller wie ReportExportController — ein
 * roher Datei-Byte-Stream passt nicht zur sonst durchgehenden OCS/JSON-API.
 * Kein zusätzliches ERP-Rechte-Gate über das hinaus, was Nextclouds eigene
 * Datei-Sichtbarkeit (getUserFolder()->getById()) ohnehin erzwingt —
 * derselbe Vertrauenslevel wie der bereits bestehende "/f/{fileId}"-Link
 * der Files-App.
 */
class DocumentsController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private IRootFolder $rootFolder,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	// NoCSRFRequired wie ReportExportController::invoicesCsv() — ein
	// <iframe src="...">-Embed kann keinen CSRF-Token mitschicken.
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function show(int $fileId): FileDisplayResponse|DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['error' => 'No active user session'], Http::STATUS_FORBIDDEN);
		}

		$nodes = $this->rootFolder->getUserFolder($user->getUID())->getById($fileId);
		$node = $nodes[0] ?? null;
		if (!($node instanceof File)) {
			return new DataResponse(['error' => 'Document not found'], Http::STATUS_NOT_FOUND);
		}

		return new FileDisplayResponse($node, Http::STATUS_OK, ['Content-Type' => $node->getMimeType()]);
	}
}
