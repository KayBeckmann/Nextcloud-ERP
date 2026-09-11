<?php

declare(strict_types=1);
namespace OCA\ERP\Db;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;
/** @extends QBMapper<DocumentLayout> */
class DocumentLayoutMapper extends QBMapper {
	public function __construct(IDBConnection $db) { parent::__construct($db, 'erp_document_layouts', DocumentLayout::class); }
	public function findByType(string $type): ?DocumentLayout { $qb=$this->db->getQueryBuilder(); $qb->select('*')->from($this->getTableName())->where($qb->expr()->eq('document_type',$qb->createNamedParameter($type))); try { return $this->findEntity($qb); } catch (DoesNotExistException) { return null; } }
	/** @return list<DocumentLayout> */ public function findAllLayouts(): array { $qb=$this->db->getQueryBuilder(); $qb->select('*')->from($this->getTableName())->orderBy('document_type'); return $this->findEntities($qb); }
}
