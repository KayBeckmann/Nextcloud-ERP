<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<StandardRate>
 */
class StandardRateMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'erp_standard_rates', StandardRate::class);
	}

	/** @return StandardRate[] */
	public function findAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName());
		return $this->findEntities($qb);
	}

	public function findById(int $id): ?StandardRate {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function findExisting(int $workTypeId, ?string $principalType, ?string $principalId): ?StandardRate {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('work_type_id', $qb->createNamedParameter($workTypeId, \PDO::PARAM_INT)));
		$qb->andWhere($principalType === null ? $qb->expr()->isNull('principal_type') : $qb->expr()->eq('principal_type', $qb->createNamedParameter($principalType)));
		$qb->andWhere($principalId === null ? $qb->expr()->isNull('principal_id') : $qb->expr()->eq('principal_id', $qb->createNamedParameter($principalId)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}
}
