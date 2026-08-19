<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<CalendarLink>
 */
class CalendarLinkMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'erp_calendar_links', CalendarLink::class);
	}

	/** @return CalendarLink[] */
	public function findByResource(string $resourceType, string $resourceId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('resource_type', $qb->createNamedParameter($resourceType)))
			->andWhere($qb->expr()->eq('resource_id', $qb->createNamedParameter($resourceId)))
			->orderBy('created_at', 'DESC');
		return $this->findEntities($qb);
	}
}
