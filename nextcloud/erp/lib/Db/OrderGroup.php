<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getOrderId()
 * @method void setOrderId(int $orderId)
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method int getPosition()
 * @method void setPosition(int $position)
 */
class OrderGroup extends Entity implements \JsonSerializable {
	protected int $orderId = 0;
	protected string $title = '';
	protected int $position = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('orderId', 'integer');
		$this->addType('position', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'orderId' => $this->getOrderId(),
			'title' => $this->getTitle(),
			'position' => $this->getPosition(),
		];
	}
}
