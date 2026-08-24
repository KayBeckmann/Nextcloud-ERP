<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IUser;

/**
 * Legt die ERP-Ordnerstruktur im Home-Verzeichnis des Users an (ADR-0009).
 * Idempotent: sicher wiederholt aufrufbar, legt nur fehlende Ordner an.
 *
 * Bekannte Einschränkung (siehe ADR-0009): pro-User statt eines gemeinsamen
 * Ablageorts — für Phase 4 (echte Projektordner) zu überdenken.
 */
class ErpFolderService {
	private const ROOT = 'ERP';

	/** Struktur aus dem Brainstorming (10_Projects/Nextcloud-ERP-Apps/Brainstorming.md). */
	private const SUBFOLDERS = [
		'Projekte',
		'Artikel',
		'Produkte',
		'Lieferanten',
		'Fuhrpark',
		'Kosten',
		'Vorlagen',
		'Archiv',
	];

	public function __construct(
		private IRootFolder $rootFolder,
	) {
	}

	/** @return list<array{name: string, path: string, fileId: int}> */
	public function ensureStructure(IUser $user): array {
		$userFolder = $this->rootFolder->getUserFolder($user->getUID());

		$erpFolder = $this->ensureFolder($userFolder, self::ROOT);
		$result = [[
			'name' => self::ROOT,
			'path' => $erpFolder->getPath(),
			'fileId' => $erpFolder->getId(),
		]];

		foreach (self::SUBFOLDERS as $name) {
			$sub = $this->ensureFolder($erpFolder, $name);
			$result[] = [
				'name' => $name,
				'path' => $sub->getPath(),
				'fileId' => $sub->getId(),
			];
		}

		return $result;
	}

	/**
	 * Legt (falls nötig) den Projektordner ERP/Projekte/<projectNumber> an und
	 * gibt dessen Node zurück (ADR-0010). Setzt voraus, dass ensureStructure()
	 * vorher lief oder ruft die nötigen Elternordner selbst mit an.
	 */
	public function ensureProjectFolder(IUser $user, string $projectNumber): Folder {
		$userFolder = $this->rootFolder->getUserFolder($user->getUID());
		$erpFolder = $this->ensureFolder($userFolder, self::ROOT);
		$projekteFolder = $this->ensureFolder($erpFolder, 'Projekte');
		return $this->ensureFolder($projekteFolder, $projectNumber);
	}

	/**
	 * Legt (falls nötig) ERP/Projekte/<projectNumber>/Rechnungen an, für die
	 * beim Ausstellen einer Rechnung abgelegte Dokument-Datei (ADR-0013).
	 */
	public function ensureInvoiceFolder(IUser $user, string $projectNumber): Folder {
		$projectFolder = $this->ensureProjectFolder($user, $projectNumber);
		return $this->ensureFolder($projectFolder, 'Rechnungen');
	}

	/**
	 * Je ein weiterer Unterordner pro Belegtyp im Projektordner für den
	 * PDF-Export (ADR-0021) — analog zu ensureInvoiceFolder().
	 */
	public function ensureQuoteFolder(IUser $user, string $projectNumber): Folder {
		$projectFolder = $this->ensureProjectFolder($user, $projectNumber);
		return $this->ensureFolder($projectFolder, 'Angebote');
	}

	public function ensureOrderFolder(IUser $user, string $projectNumber): Folder {
		$projectFolder = $this->ensureProjectFolder($user, $projectNumber);
		return $this->ensureFolder($projectFolder, 'Aufträge');
	}

	public function ensureDeliveryNoteFolder(IUser $user, string $projectNumber): Folder {
		$projectFolder = $this->ensureProjectFolder($user, $projectNumber);
		return $this->ensureFolder($projectFolder, 'Lieferscheine');
	}

	public function ensureCreditNoteFolder(IUser $user, string $projectNumber): Folder {
		$projectFolder = $this->ensureProjectFolder($user, $projectNumber);
		return $this->ensureFolder($projectFolder, 'Gutschriften');
	}

	/**
	 * Legt (falls nötig) ERP/Fuhrpark/<Kennzeichen>/Tankbelege an, für
	 * hochgeladene Tankbeleg-Fotos (ADR-0017).
	 */
	public function ensureVehicleReceiptFolder(IUser $user, string $licensePlate): Folder {
		$userFolder = $this->rootFolder->getUserFolder($user->getUID());
		$erpFolder = $this->ensureFolder($userFolder, self::ROOT);
		$fuhrparkFolder = $this->ensureFolder($erpFolder, 'Fuhrpark');
		$vehicleFolder = $this->ensureFolder($fuhrparkFolder, $licensePlate);
		return $this->ensureFolder($vehicleFolder, 'Tankbelege');
	}

	private function ensureFolder(Folder $parent, string $name): Folder {
		if ($parent->nodeExists($name)) {
			/** @var Folder $node */
			$node = $parent->get($name);
			return $node;
		}
		return $parent->newFolder($name);
	}
}
