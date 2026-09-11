<?php

declare(strict_types=1);
namespace OCA\ERP\Db;
use OCP\AppFramework\Db\Entity;
/** @method int getPurchaseOrderPositionId() @method void setPurchaseOrderPositionId(int $value) @method int getWarehouseId() @method void setWarehouseId(int $value) @method float getQuantity() @method void setQuantity(float $value) @method string getReceivedBy() @method void setReceivedBy(string $value) @method int getReceivedAt() @method void setReceivedAt(int $value) @method string|null getNotes() @method void setNotes(?string $value) */
class PurchaseOrderReceipt extends Entity implements \JsonSerializable {
	protected int $purchaseOrderPositionId = 0; protected int $warehouseId = 0; protected float $quantity = 0.0; protected string $receivedBy = ''; protected int $receivedAt = 0; protected ?string $notes = null;
	public function __construct() { foreach (['id','purchaseOrderPositionId','warehouseId','receivedAt'] as $field) { $this->addType($field,'integer'); } $this->addType('quantity','float'); }
	public function jsonSerialize(): array { return ['id'=>$this->getId(),'purchaseOrderPositionId'=>$this->getPurchaseOrderPositionId(),'warehouseId'=>$this->getWarehouseId(),'quantity'=>$this->getQuantity(),'receivedBy'=>$this->getReceivedBy(),'receivedAt'=>$this->getReceivedAt(),'notes'=>$this->getNotes()]; }
}
