<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Support;

use OCA\GroupFolders\Folder\FolderManager;
use OCP\Constants;
use OCP\IDBConnection;
use OCP\IGroupManager;
use Test\TestCase;

/**
 * Stabilisiert die echte Nextcloud-Files-Integration der ERP-Tests.
 *
 * Der Nextcloud-Server-TestCase entfernt nach jeder Testklasse bewusst alle
 * Storage- und Filecache-Einträge. Die Konfiguration der Teamfolder-App bleibt
 * dabei erhalten, zeigt anschließend aber auf eine gelöschte Storage. Für die
 * ERP-Tests wird der gemeinsame ADR-0024-Teamfolder deshalb vor der jeweiligen
 * Testklasse bei Bedarf neu angelegt.
 */
abstract class ErpIntegrationTestCase extends TestCase {
	private const GROUP_FOLDER = 'ERP-Firma';
	private const PROJECT_LEADERS = 'erp-projektleiter';
	private const FIELD_WORKERS = 'erp-monteure';

	protected function setUp(): void {
		parent::setUp();
		self::ensureSharedTeamFolder();
	}

	public static function tearDownAfterClass(): void {
		parent::tearDownAfterClass();
		self::ensureSharedTeamFolder();
	}

	private static function ensureSharedTeamFolder(): void {
		/** @var IDBConnection $db */
		$db = \OC::$server->get(IDBConnection::class);
		$query = $db->getQueryBuilder();
		$query->select('f.folder_id')
			->from('group_folders', 'f')
			->innerJoin('f', 'filecache', 'c', $query->expr()->eq('c.fileid', 'f.root_id'))
			->where($query->expr()->eq('f.mount_point', $query->createNamedParameter(self::GROUP_FOLDER)))
			->setMaxResults(1);
		$folderId = $query->executeQuery()->fetchOne();

		if ($folderId !== false) {
			return;
		}

		// Eine alte Konfigurationszeile ohne Storage blockiert createFolder().
		$staleQuery = $db->getQueryBuilder();
		$staleIds = $staleQuery->select('folder_id')
			->from('group_folders')
			->where($staleQuery->expr()->eq('mount_point', $staleQuery->createNamedParameter(self::GROUP_FOLDER)))
			->executeQuery()
			->fetchFirstColumn();
		foreach ($staleIds as $staleId) {
			$cleanup = $db->getQueryBuilder();
			$cleanup->delete('group_folders_groups')
				->where($cleanup->expr()->eq('folder_id', $cleanup->createNamedParameter((int)$staleId)))
				->executeStatement();
		}

		$cleanup = $db->getQueryBuilder();
		$cleanup->delete('group_folders')
			->where($cleanup->expr()->eq('mount_point', $cleanup->createNamedParameter(self::GROUP_FOLDER)))
			->executeStatement();

		/** @var IGroupManager $groups */
		$groups = \OC::$server->get(IGroupManager::class);
		$groups->get(self::PROJECT_LEADERS) ?? $groups->createGroup(self::PROJECT_LEADERS);
		$groups->get(self::FIELD_WORKERS) ?? $groups->createGroup(self::FIELD_WORKERS);

		/** @var FolderManager $folders */
		$folders = \OC::$server->get(FolderManager::class);
		$folderId = $folders->createFolder(self::GROUP_FOLDER);
		$folders->addApplicableGroup($folderId, self::PROJECT_LEADERS);
		$folders->setGroupPermissions($folderId, self::PROJECT_LEADERS, Constants::PERMISSION_ALL);
		$folders->addApplicableGroup($folderId, self::FIELD_WORKERS);
		$folders->setGroupPermissions($folderId, self::FIELD_WORKERS, Constants::PERMISSION_READ | Constants::PERMISSION_UPDATE | Constants::PERMISSION_CREATE);
	}
}
