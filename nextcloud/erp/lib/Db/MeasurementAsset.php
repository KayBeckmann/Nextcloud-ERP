<?php
declare(strict_types=1);
namespace OCA\ERP\Db;
use OCP\AppFramework\Db\Entity;
/** @method int getProjectId() @method void setProjectId(int $v) @method int getFileId() @method void setFileId(int $v) @method string getMimeType() @method void setMimeType(string $v) @method int getCreatedAt() @method void setCreatedAt(int $v) @method int|null getDeletedAt() @method void setDeletedAt(?int $v) */
class MeasurementAsset extends Entity { protected int $projectId=0; protected int $fileId=0; protected string $mimeType=''; protected int $createdAt=0; protected ?int $deletedAt=null; public function __construct(){foreach(['id','projectId','fileId','createdAt','deletedAt'] as $f)$this->addType($f,'integer');} }
