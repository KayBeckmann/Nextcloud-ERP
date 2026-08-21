<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<CostSettings>
 */
class CostSettingsMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'erp_cost_settings', CostSettings::class);
	}

	public function findByYear(int $year): ?CostSettings {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('year', $qb->createNamedParameter($year, \PDO::PARAM_INT)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}
}
