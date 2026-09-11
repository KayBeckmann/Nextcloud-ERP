<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/** @method int getPurchaseOrderId() @method void setPurchaseOrderId(int $value) @method string|null getFromStatus() @method void setFromStatus(?string $value) @method string getToStatus() @method void setToStatus(string $value) @method string getChangedBy() @method void setChangedBy(string $value) @method int getChangedAt() @method void setChangedAt(int $value) @method string|null getNotes() @method void setNotes(?string $value) */
class PurchaseOrderStatusChange extends Entity implements \JsonSerializable {
	protected int $purchaseOrderId = 0;
	protected ?string $fromStatus = null;
	protected string $toStatus = '';
	protected string $changedBy = '';
	protected int $changedAt = 0;
	protected ?string $notes = null;
	public function __construct() { $this->addType('id','integer'); $this->addType('purchaseOrderId','integer'); $this->addType('changedAt','integer'); }
	public function jsonSerialize(): array { return ['id'=>$this->getId(),'purchaseOrderId'=>$this->getPurchaseOrderId(),'fromStatus'=>$this->getFromStatus(),'toStatus'=>$this->getToStatus(),'changedBy'=>$this->getChangedBy(),'changedAt'=>$this->getChangedAt(),'notes'=>$this->getNotes()]; }
}
