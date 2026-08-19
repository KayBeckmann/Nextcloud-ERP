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

	private function ensureFolder(Folder $parent, string $name): Folder {
		if ($parent->nodeExists($name)) {
			/** @var Folder $node */
			$node = $parent->get($name);
			return $node;
		}
		return $parent->newFolder($name);
	}
}
