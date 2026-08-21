<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getCategory()
 * @method void setCategory(string $category)
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method float getMonthlyAmount()
 * @method void setMonthlyAmount(float $monthlyAmount)
 * @method int getYear()
 * @method void setYear(int $year)
 * @method int getMonth()
 * @method void setMonth(int $month)
 * @method string|null getNotes()
 * @method void setNotes(?string $notes)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class CostEntry extends Entity implements \JsonSerializable {
	// Kein sinnvoller Default: jede echte Kategorie ist ein gültiger Wert,
	// identischer Fallstrick wie QuotePosition::$positionType (ADR-0011).
	protected string $category = '';
	protected string $title = '';
	protected float $monthlyAmount = 0.0;
	protected int $year = 0;
	protected int $month = 0;
	protected ?string $notes = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('monthlyAmount', 'float');
		$this->addType('year', 'integer');
		$this->addType('month', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'category' => $this->getCategory(),
			'title' => $this->getTitle(),
			'monthlyAmount' => $this->getMonthlyAmount(),
			'year' => $this->getYear(),
			'month' => $this->getMonth(),
			'notes' => $this->getNotes(),
		];
	}
}
