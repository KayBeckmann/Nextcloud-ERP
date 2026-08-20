<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getQuoteId()
 * @method void setQuoteId(int $quoteId)
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method int getPosition()
 * @method void setPosition(int $position)
 */
class QuoteGroup extends Entity implements \JsonSerializable {
	protected int $quoteId = 0;
	protected string $title = '';
	protected int $position = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('quoteId', 'integer');
		$this->addType('position', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'quoteId' => $this->getQuoteId(),
			'title' => $this->getTitle(),
			'position' => $this->getPosition(),
		];
	}
}
