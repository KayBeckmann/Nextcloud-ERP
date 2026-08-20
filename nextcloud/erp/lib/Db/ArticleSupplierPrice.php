<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getArticleId()
 * @method void setArticleId(int $articleId)
 * @method string getSupplierContactUid()
 * @method void setSupplierContactUid(string $supplierContactUid)
 * @method string|null getSupplierArticleNo()
 * @method void setSupplierArticleNo(?string $supplierArticleNo)
 * @method float getPurchasePrice()
 * @method void setPurchasePrice(float $purchasePrice)
 * @method string getCurrency()
 * @method void setCurrency(string $currency)
 * @method float|null getMinOrderQuantity()
 * @method void setMinOrderQuantity(?float $minOrderQuantity)
 * @method string|null getDeliveryTime()
 * @method void setDeliveryTime(?string $deliveryTime)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class ArticleSupplierPrice extends Entity implements \JsonSerializable {
	protected int $articleId = 0;
	protected string $supplierContactUid = '';
	protected ?string $supplierArticleNo = null;
	protected float $purchasePrice = 0.0;
	protected string $currency = 'EUR';
	protected ?float $minOrderQuantity = null;
	protected ?string $deliveryTime = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('articleId', 'integer');
		$this->addType('purchasePrice', 'float');
		$this->addType('minOrderQuantity', 'float');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'articleId' => $this->getArticleId(),
			'supplierContactUid' => $this->getSupplierContactUid(),
			'supplierArticleNo' => $this->getSupplierArticleNo(),
			'purchasePrice' => $this->getPurchasePrice(),
			'currency' => $this->getCurrency(),
			'minOrderQuantity' => $this->getMinOrderQuantity(),
			'deliveryTime' => $this->getDeliveryTime(),
		];
	}
}
