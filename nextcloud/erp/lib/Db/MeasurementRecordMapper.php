<?php

declare(strict_types=1);
namespace OCA\ERP\Db;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;
/** @extends QBMapper<MeasurementRecord> */
class MeasurementRecordMapper extends QBMapper {
	public function __construct(IDBConnection $db) { parent::__construct($db, 'erp_measurement_records', MeasurementRecord::class); }
	public function findById(int $id): ?MeasurementRecord { $qb=$this->db->getQueryBuilder(); $qb->select('*')->from($this->getTableName())->where($qb->expr()->eq('id',$qb->createNamedParameter($id, \PDO::PARAM_INT))); try { return $this->findEntity($qb); } catch (DoesNotExistException) { return null; } }
	/** @return list<MeasurementRecord> */
	public function findByProject(int $projectId): array { $qb=$this->db->getQueryBuilder(); $qb->select('*')->from($this->getTableName())->where($qb->expr()->eq('project_id',$qb->createNamedParameter($projectId, \PDO::PARAM_INT)))->andWhere($qb->expr()->isNull('deleted_at'))->orderBy('updated_at','DESC'); return $this->findEntities($qb); }
}
