<?php

declare(strict_types=1);
namespace OCA\ERP\Db;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;
/** @extends QBMapper<VehicleAssignment> */
class VehicleAssignmentMapper extends QBMapper {
	public function __construct(IDBConnection $db) { parent::__construct($db, 'erp_vehicle_assignments', VehicleAssignment::class); }
	/** @return VehicleAssignment[] */
	public function findByVehicle(int $vehicleId): array { $qb=$this->db->getQueryBuilder(); $qb->select('*')->from($this->getTableName())->where($qb->expr()->eq('vehicle_id',$qb->createNamedParameter($vehicleId,\PDO::PARAM_INT)))->orderBy('assigned_at','DESC')->addOrderBy('id','DESC'); return $this->findEntities($qb); }

	/** Die aktuell offene Zuweisung eines Fahrzeugs (unassigned_at IS NULL), falls vorhanden. */
	public function findOpen(int $vehicleId): ?VehicleAssignment {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('vehicle_id', $qb->createNamedParameter($vehicleId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->isNull('unassigned_at'));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}
}
