<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Quote>
 */
class QuoteMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'erp_quotes', Quote::class);
	}

	/** @return Quote[] */
	public function findAll(?string $status = null, ?int $projectId = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())->orderBy('created_at', 'DESC');
		if ($status !== null) {
			$qb->andWhere($qb->expr()->eq('status', $qb->createNamedParameter($status)));
		}
		if ($projectId !== null) {
			$qb->andWhere($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)));
		}
		return $this->findEntities($qb);
	}

	public function findById(int $id): ?Quote {
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
