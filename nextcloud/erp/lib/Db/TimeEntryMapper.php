<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<TimeEntry>
 */
class TimeEntryMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'erp_time_entries', TimeEntry::class);
	}

	public function findById(int $id): ?TimeEntry {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * @return TimeEntry[]
	 */
	public function findByUser(string $userId, ?string $fromDate = null, ?string $toDate = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		if ($fromDate !== null) {
			$qb->andWhere($qb->expr()->gte('entry_date', $qb->createNamedParameter($fromDate)));
		}
		if ($toDate !== null) {
			$qb->andWhere($qb->expr()->lte('entry_date', $qb->createNamedParameter($toDate)));
		}
		$qb->orderBy('entry_date', 'DESC')->addOrderBy('id', 'DESC');
		return $this->findEntities($qb);
	}

	/** @return TimeEntry[] */
	public function findByProject(int $projectId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->orderBy('entry_date', 'DESC');
		return $this->findEntities($qb);
	}
}
