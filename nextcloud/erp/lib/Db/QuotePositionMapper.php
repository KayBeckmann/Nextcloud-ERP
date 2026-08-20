<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<QuotePosition>
 */
class QuotePositionMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'erp_quote_positions', QuotePosition::class);
	}

	/** @return QuotePosition[] */
	public function findByQuote(int $quoteId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('quote_id', $qb->createNamedParameter($quoteId, \PDO::PARAM_INT)))
			->orderBy('position_order', 'ASC');
		return $this->findEntities($qb);
	}

	public function findOne(int $quoteId, int $id): ?QuotePosition {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('quote_id', $qb->createNamedParameter($quoteId, \PDO::PARAM_INT)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}
}
