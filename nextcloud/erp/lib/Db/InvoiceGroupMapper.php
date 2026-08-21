<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<InvoiceGroup>
 */
class InvoiceGroupMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'erp_invoice_groups', InvoiceGroup::class);
	}

	/** @return InvoiceGroup[] */
	public function findByInvoice(int $invoiceId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('invoice_id', $qb->createNamedParameter($invoiceId, \PDO::PARAM_INT)))
			->orderBy('position', 'ASC');
		return $this->findEntities($qb);
	}

	public function findOne(int $invoiceId, int $id): ?InvoiceGroup {
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
}
