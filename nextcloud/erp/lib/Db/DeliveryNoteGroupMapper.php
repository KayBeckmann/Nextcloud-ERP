<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<DeliveryNoteGroup>
 */
class DeliveryNoteGroupMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'erp_delivery_note_groups', DeliveryNoteGroup::class);
	}

	/** @return DeliveryNoteGroup[] */
	public function findByDeliveryNote(int $deliveryNoteId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('delivery_note_id', $qb->createNamedParameter($deliveryNoteId, \PDO::PARAM_INT)))
			->orderBy('position', 'ASC');
		return $this->findEntities($qb);
	}

	public function findOne(int $deliveryNoteId, int $id): ?DeliveryNoteGroup {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('delivery_note_id', $qb->createNamedParameter($deliveryNoteId, \PDO::PARAM_INT)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}
}
