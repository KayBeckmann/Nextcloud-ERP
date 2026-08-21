<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<DeliveryNotePosition>
 */
class DeliveryNotePositionMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'erp_delivery_note_positions', DeliveryNotePosition::class);
	}

	/** @return DeliveryNotePosition[] */
	public function findByDeliveryNote(int $deliveryNoteId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('delivery_note_id', $qb->createNamedParameter($deliveryNoteId, \PDO::PARAM_INT)))
			->orderBy('position_order', 'ASC');
		return $this->findEntities($qb);
	}

	public function findOne(int $deliveryNoteId, int $id): ?DeliveryNotePosition {
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

	public function findById(int $id): ?DeliveryNotePosition {
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
	 * Bereits gelieferte Menge einer Auftragsposition (ADR-0016) — zur
	 * Laufzeit summiert, informativ, kein hartes Limit.
	 */
	public function sumQuantityForOrderPosition(int $orderPositionId): float {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('SUM(quantity)'))
			->from($this->getTableName())
			->where($qb->expr()->eq('order_position_id', $qb->createNamedParameter($orderPositionId, \PDO::PARAM_INT)));
		$sum = $qb->executeQuery()->fetchOne();
		return $sum === false ? 0.0 : (float)$sum;
	}
}
