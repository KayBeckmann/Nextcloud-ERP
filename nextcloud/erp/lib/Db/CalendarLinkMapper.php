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

	/**
	 * ERP-Termine desselben zugewiesenen Users, deren Zeitraum sich mit
	 * [$start, $end) überschneidet (ADR-0020, Kollisionserkennung).
	 * Offene Intervalle: ein Termin, der exakt endet, wenn der nächste
	 * beginnt, gilt nicht als Kollision.
	 *
	 * @return CalendarLink[]
	 */
	public function findOverlapping(string $assignedUserId, int $start, int $end): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('assigned_user_id', $qb->createNamedParameter($assignedUserId)))
			->andWhere($qb->expr()->lt('start_at', $qb->createNamedParameter($end, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->gt('end_at', $qb->createNamedParameter($start, \PDO::PARAM_INT)));
		return $this->findEntities($qb);
	}
}
