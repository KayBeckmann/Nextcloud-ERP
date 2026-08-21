<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getInvoiceId()
 * @method void setInvoiceId(int $invoiceId)
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method int getPosition()
 * @method void setPosition(int $position)
 */
class InvoiceGroup extends Entity implements \JsonSerializable {
	protected int $invoiceId = 0;
	protected string $title = '';
	protected int $position = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('invoiceId', 'integer');
		$this->addType('position', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'invoiceId' => $this->getInvoiceId(),
			'title' => $this->getTitle(),
			'position' => $this->getPosition(),
		];
	}
}
