<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<ContactPersonDefault>
 */
class ContactPersonDefaultMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'erp_contact_person_defaults', ContactPersonDefault::class);
	}

	/** @return ContactPersonDefault[] */
	public function findByContactLink(int $contactLinkId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('contact_link_id', $qb->createNamedParameter($contactLinkId, \PDO::PARAM_INT)));
		return $this->findEntities($qb);
	}

	public function findOneByLinkAndType(int $contactLinkId, string $documentType): ?ContactPersonDefault {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('contact_link_id', $qb->createNamedParameter($contactLinkId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('document_type', $qb->createNamedParameter($documentType)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}
}
