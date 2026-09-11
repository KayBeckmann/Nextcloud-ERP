<?php
declare(strict_types=1);
namespace OCA\ERP\Db;
use OCP\AppFramework\Db\DoesNotExistException; use OCP\AppFramework\Db\QBMapper; use OCP\IDBConnection;
/** @extends QBMapper<MeasurementAsset> */
class MeasurementAssetMapper extends QBMapper { public function __construct(IDBConnection $db){parent::__construct($db,'erp_measurement_assets',MeasurementAsset::class);} public function findActive(int $projectId,int $fileId):?MeasurementAsset{$q=$this->db->getQueryBuilder();$q->select('*')->from($this->getTableName())->where($q->expr()->eq('project_id',$q->createNamedParameter($projectId,\PDO::PARAM_INT)))->andWhere($q->expr()->eq('file_id',$q->createNamedParameter($fileId,\PDO::PARAM_INT)))->andWhere($q->expr()->isNull('deleted_at'));try{return $this->findEntity($q);}catch(DoesNotExistException){return null;}} }
