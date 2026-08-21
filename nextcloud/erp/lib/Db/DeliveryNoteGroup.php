<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getDeliveryNoteId()
 * @method void setDeliveryNoteId(int $deliveryNoteId)
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method int getPosition()
 * @method void setPosition(int $position)
 */
class DeliveryNoteGroup extends Entity implements \JsonSerializable {
	protected int $deliveryNoteId = 0;
	protected string $title = '';
	protected int $position = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('deliveryNoteId', 'integer');
		$this->addType('position', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'deliveryNoteId' => $this->getDeliveryNoteId(),
			'title' => $this->getTitle(),
			'position' => $this->getPosition(),
		];
	}
}
