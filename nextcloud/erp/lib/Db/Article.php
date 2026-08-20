<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getName()
 * @method void setName(string $name)
 * @method string|null getManufacturer()
 * @method void setManufacturer(?string $manufacturer)
 * @method string|null getManufacturerArticleNo()
 * @method void setManufacturerArticleNo(?string $manufacturerArticleNo)
 * @method string getUnit()
 * @method void setUnit(string $unit)
 * @method string|null getCategory()
 * @method void setCategory(?string $category)
 * @method int|null getVatRateId()
 * @method void setVatRateId(?int $vatRateId)
 * @method string|null getNotes()
 * @method void setNotes(?string $notes)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class Article extends Entity implements \JsonSerializable {
	protected string $name = '';
	protected ?string $manufacturer = null;
	protected ?string $manufacturerArticleNo = null;
	protected string $unit = 'Stk';
	protected ?string $category = null;
	protected ?int $vatRateId = null;
	protected ?string $notes = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('vatRateId', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'name' => $this->getName(),
			'manufacturer' => $this->getManufacturer(),
			'manufacturerArticleNo' => $this->getManufacturerArticleNo(),
			'unit' => $this->getUnit(),
			'category' => $this->getCategory(),
			'vatRateId' => $this->getVatRateId(),
			'notes' => $this->getNotes(),
		];
	}
}
