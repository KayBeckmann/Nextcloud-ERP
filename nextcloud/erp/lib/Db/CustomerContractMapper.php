<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<CustomerContract>
 */
class CustomerContractMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'erp_customer_contracts', CustomerContract::class);
	}

	/** @return CustomerContract[] */
	public function findByCustomer(string $customerContactUid): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('customer_contact_uid', $qb->createNamedParameter($customerContactUid)))
			->orderBy('valid_from', 'DESC');
		return $this->findEntities($qb);
	}

	public function findById(int $id): ?CustomerContract {
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
