<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/** @method string getSupplierContactUid() @method void setSupplierContactUid(string $value) @method string getStatus() @method void setStatus(string $value) @method string getCreatedBy() @method void setCreatedBy(string $value) @method int getCreatedAt() @method void setCreatedAt(int $value) @method int getUpdatedAt() @method void setUpdatedAt(int $value) */
class PurchaseOrder extends Entity implements \JsonSerializable {
	protected string $supplierContactUid = '';
	protected ?string $supplierReference = null;
	protected string $status = 'draft';
	protected ?string $notes = null;
	protected string $createdBy = '';
	protected int $createdAt = 0;
	protected int $updatedAt = 0;
	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}
	public function jsonSerialize(): array {
		return ['id' => $this->getId(), 'supplierContactUid' => $this->getSupplierContactUid(), 'supplierReference' => $this->getSupplierReference(), 'status' => $this->getStatus(), 'notes' => $this->getNotes(), 'createdBy' => $this->getCreatedBy(), 'createdAt' => $this->getCreatedAt(), 'updatedAt' => $this->getUpdatedAt()];
	}
}
