<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IUser;

/**
 * Legt die ERP-Ordnerstruktur im gemeinsamen Group Folder "ERP-Firma" an
 * (ADR-0024) — bewusst NICHT mehr im persönlichen Home-Verzeichnis des
 * jeweiligen Users, das war die in ADR-0009 dokumentierte, nie geschlossene
 * "bekannte Einschränkung" ("pro-User statt eines gemeinsamen Ablageorts").
 *
 * Der Group Folder (App `groupfolders`, Ordner "ERP-Firma", Gruppen
 * `erp-projektleiter`/`erp-monteure`) mountet sich transparent als normaler
 * Ordner in [IRootFolder::getUserFolder()] — die bestehende
 * `Folder::newFolder()`/`nodeExists()`-Logik bleibt unverändert, nur der
 * Startpunkt hat sich geändert. Idempotent: sicher wiederholt aufrufbar,
 * legt nur fehlende Ordner an.
 */
class ErpFolderService {
	private const ROOT = 'ERP';

	/**
	 * Mount-Name des Group Folders (App `groupfolders`, ADR-0024) — muss mit
	 * dem in `occ groupfolders:create` vergebenen Namen übereinstimmen.
	 */
	private const GROUP_FOLDER_MOUNT_POINT = 'ERP-Firma';

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
		$erpFolder = $this->ensureFolder($this->erpRoot($user), self::ROOT);
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
		$erpFolder = $this->ensureFolder($this->erpRoot($user), self::ROOT);
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
		$erpFolder = $this->ensureFolder($this->erpRoot($user), self::ROOT);
		$fuhrparkFolder = $this->ensureFolder($erpFolder, 'Fuhrpark');
		$vehicleFolder = $this->ensureFolder($fuhrparkFolder, $licensePlate);
		return $this->ensureFolder($vehicleFolder, 'Tankbelege');
	}

	/**
	 * Wurzel für die gesamte ERP-Ordnerstruktur (ADR-0024): der gemeinsame
	 * Group Folder statt des persönlichen Home-Verzeichnisses.
	 *
	 * @throws NotFoundException wenn der Group Folder für diesen User nicht
	 *         sichtbar ist — passiert nur, wenn `groupfolders` nicht wie in
	 *         ADR-0024 vorgesehen eingerichtet ist (App nicht installiert,
	 *         Folder nicht angelegt, oder der User in keiner der
	 *         freigegebenen Gruppen ist). Ein User ohne jede ERP-Gruppe hat
	 *         über die Rechte-Matrix ohnehin keinen sinnvollen Zugriff auf
	 *         Ressourcen, die Dateien referenzieren.
	 */
	private function erpRoot(IUser $user): Folder {
		$userFolder = $this->rootFolder->getUserFolder($user->getUID());
		// Bewusst kein vorheriges nodeExists()-Check: bei frisch gemounteten
		// Group Foldern (neuer User in der Gruppe, erster Zugriff in diesem
		// Request) liefert nodeExists() unzuverlässig `false`, obwohl get()
		// direkt danach den Node zuverlässig findet — vermutlich weil
		// nodeExists() den Mount nicht selbst auflöst, während get() das tut.
		// Stattdessen get() direkt versuchen und dessen eigene
		// NotFoundException mit dem hilfreicheren ADR-0024-Hinweis ersetzen.
		try {
			/** @var Folder $node */
			$node = $userFolder->get(self::GROUP_FOLDER_MOUNT_POINT);
		} catch (NotFoundException $e) {
			throw new NotFoundException(
				"Group folder '" . self::GROUP_FOLDER_MOUNT_POINT . "' not visible for user "
				. "'{$user->getUID()}' — see ADR-0024 (groupfolders app, 'occ groupfolders:create "
				. self::GROUP_FOLDER_MOUNT_POINT . "', Gruppenzuweisung)."
			);
		}
		return $node;
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
