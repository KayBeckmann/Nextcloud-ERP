<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<InvoicePosition>
 */
class InvoicePositionMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'erp_invoice_positions', InvoicePosition::class);
	}

	/** @return InvoicePosition[] */
	public function findByInvoice(int $invoiceId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('invoice_id', $qb->createNamedParameter($invoiceId, \PDO::PARAM_INT)))
			->orderBy('position_order', 'ASC');
		return $this->findEntities($qb);
	}

	public function findOne(int $invoiceId, int $id): ?InvoicePosition {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('invoice_id', $qb->createNamedParameter($invoiceId, \PDO::PARAM_INT)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * Bereits berechnete Menge einer Auftragsposition (ADR-0016) — zur
	 * Laufzeit über alle Rechnungspositionen mit diesem `order_position_id`
	 * summiert, stornierte Rechnungen zählen nicht mit. Informativ, kein
	 * hartes Limit (siehe ADR-0016, "Nicht Teil dieser Phase").
	 */
	public function sumQuantityForOrderPosition(int $orderPositionId): float {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('SUM(' . $qb->getColumnName('ip.quantity') . ')'))
			->from($this->getTableName(), 'ip')
			->innerJoin('ip', 'erp_invoices', 'i', $qb->expr()->eq('ip.invoice_id', 'i.id'))
			->where($qb->expr()->eq('ip.order_position_id', $qb->createNamedParameter($orderPositionId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->neq('i.status', $qb->createNamedParameter('cancelled')));
		$sum = $qb->executeQuery()->fetchOne();
		return $sum === false ? 0.0 : (float)$sum;
	}
}
