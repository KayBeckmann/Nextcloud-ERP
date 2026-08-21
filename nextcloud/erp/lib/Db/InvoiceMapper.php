<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Invoice>
 */
class InvoiceMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'erp_invoices', Invoice::class);
	}

	/** @return Invoice[] */
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

	/**
	 * Andere Rechnungen desselben Auftrags (ADR-0016) — für die
	 * "Teilrechnungen/Teilzahlungen"-Übersicht in `getFullInvoice()`.
	 *
	 * @return Invoice[]
	 */
	public function findByOrder(int $orderId, ?int $excludeId = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('order_id', $qb->createNamedParameter($orderId, \PDO::PARAM_INT)))
			->orderBy('created_at', 'ASC');
		if ($excludeId !== null) {
			$qb->andWhere($qb->expr()->neq('id', $qb->createNamedParameter($excludeId, \PDO::PARAM_INT)));
		}
		return $this->findEntities($qb);
	}

	public function findById(int $id): ?Invoice {
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
