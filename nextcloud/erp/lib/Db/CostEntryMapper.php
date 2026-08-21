<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<CostEntry>
 */
class CostEntryMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'erp_cost_entries', CostEntry::class);
	}

	/** @return CostEntry[] */
	public function findByYear(int $year): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('year', $qb->createNamedParameter($year, \PDO::PARAM_INT)))
			->orderBy('month', 'ASC');
		return $this->findEntities($qb);
	}

	public function findById(int $id): ?CostEntry {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}
}
