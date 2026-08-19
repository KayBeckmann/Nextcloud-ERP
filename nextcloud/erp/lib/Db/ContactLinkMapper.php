<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<ContactLink>
 */
class ContactLinkMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'erp_contact_links', ContactLink::class);
	}

	/** @return ContactLink[] */
	public function findByRole(string $role): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('role', $qb->createNamedParameter($role)))
			->orderBy('created_at', 'DESC');
		return $this->findEntities($qb);
	}

	public function findOneByContactAndRole(string $contactUid, string $role): ?ContactLink {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('contact_uid', $qb->createNamedParameter($contactUid)))
			->andWhere($qb->expr()->eq('role', $qb->createNamedParameter($role)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function findById(int $id): ?ContactLink {
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
