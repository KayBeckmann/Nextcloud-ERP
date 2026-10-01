<?php

declare(strict_types=1);
namespace OCA\ERP\Db;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;
/** @extends QBMapper<VehicleTrip> */
class VehicleTripMapper extends QBMapper {
	public function __construct(IDBConnection $db) { parent::__construct($db, 'erp_vehicle_trips', VehicleTrip::class); }
	/** @return VehicleTrip[] */
	public function findByVehicle(int $vehicleId): array { $qb=$this->db->getQueryBuilder(); $qb->select('*')->from($this->getTableName())->where($qb->expr()->eq('vehicle_id',$qb->createNamedParameter($vehicleId,\PDO::PARAM_INT)))->orderBy('trip_date','DESC')->addOrderBy('id','DESC'); return $this->findEntities($qb); }
	public function findOne(int $vehicleId, int $id): ?VehicleTrip {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('vehicle_id', $qb->createNamedParameter($vehicleId, \PDO::PARAM_INT)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}
}
