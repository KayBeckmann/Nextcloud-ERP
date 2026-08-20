<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<CreditNotePosition>
 */
class CreditNotePositionMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'erp_credit_note_positions', CreditNotePosition::class);
	}

	/** @return CreditNotePosition[] */
	public function findByCreditNote(int $creditNoteId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('credit_note_id', $qb->createNamedParameter($creditNoteId, \PDO::PARAM_INT)))
			->orderBy('position_order', 'ASC');
		return $this->findEntities($qb);
	}
}
