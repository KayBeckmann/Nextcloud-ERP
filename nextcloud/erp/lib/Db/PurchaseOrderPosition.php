<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/** @method int getPurchaseOrderId() @method void setPurchaseOrderId(int $value) @method int|null getArticleId() @method void setArticleId(?int $value) @method string getDescription() @method void setDescription(string $value) @method float getQuantityOrdered() @method void setQuantityOrdered(float $value) @method float getQuantityReceived() @method void setQuantityReceived(float $value) @method string getUnit() @method void setUnit(string $value) @method string|null getSupplierArticleNo() @method void setSupplierArticleNo(?string $value) @method float getUnitPurchasePrice() @method void setUnitPurchasePrice(float $value) @method string getCurrency() @method void setCurrency(string $value) @method int|null getProjectId() @method void setProjectId(?int $value) @method int|null getWarehouseId() @method void setWarehouseId(?int $value) @method int getPositionOrder() @method void setPositionOrder(int $value) */
class PurchaseOrderPosition extends Entity implements \JsonSerializable {
	protected int $purchaseOrderId = 0;
	protected ?int $articleId = null;
	protected string $description = '';
	protected float $quantityOrdered = 0.0;
	protected float $quantityReceived = 0.0;
	protected string $unit = 'Stk';
	protected ?string $supplierArticleNo = null;
	protected float $unitPurchasePrice = 0.0;
	protected string $currency = 'EUR';
	protected ?int $projectId = null;
	protected ?int $warehouseId = null;
	protected int $positionOrder = 0;
	public function __construct() {
		foreach (['id','purchaseOrderId','articleId','projectId','warehouseId','positionOrder'] as $field) { $this->addType($field, 'integer'); }
		foreach (['quantityOrdered','quantityReceived','unitPurchasePrice'] as $field) { $this->addType($field, 'float'); }
	}
	public function jsonSerialize(): array {
		return ['id'=>$this->getId(),'purchaseOrderId'=>$this->getPurchaseOrderId(),'articleId'=>$this->getArticleId(),'description'=>$this->getDescription(),'quantityOrdered'=>$this->getQuantityOrdered(),'quantityReceived'=>$this->getQuantityReceived(),'unit'=>$this->getUnit(),'supplierArticleNo'=>$this->getSupplierArticleNo(),'unitPurchasePrice'=>$this->getUnitPurchasePrice(),'currency'=>$this->getCurrency(),'projectId'=>$this->getProjectId(),'warehouseId'=>$this->getWarehouseId(),'positionOrder'=>$this->getPositionOrder()];
	}
}
