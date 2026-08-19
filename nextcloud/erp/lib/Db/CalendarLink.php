<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getResourceType()
 * @method void setResourceType(string $resourceType)
 * @method string getResourceId()
 * @method void setResourceId(string $resourceId)
 * @method string getCalendarUri()
 * @method void setCalendarUri(string $calendarUri)
 * @method string getEventUri()
 * @method void setEventUri(string $eventUri)
 * @method string|null getSummary()
 * @method void setSummary(?string $summary)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 */
class CalendarLink extends Entity implements \JsonSerializable {
	protected string $resourceType = '';
	protected string $resourceId = '';
	protected string $calendarUri = '';
	protected string $eventUri = '';
	protected ?string $summary = null;
	protected int $createdAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('createdAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'resourceType' => $this->getResourceType(),
			'resourceId' => $this->getResourceId(),
			'calendarUri' => $this->getCalendarUri(),
			'eventUri' => $this->getEventUri(),
			'summary' => $this->getSummary(),
		];
	}
}
