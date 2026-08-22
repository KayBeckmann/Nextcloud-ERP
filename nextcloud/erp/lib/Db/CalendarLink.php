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
 * @method string|null getAssignedUserId()
 * @method void setAssignedUserId(?string $assignedUserId)
 * @method int|null getStartAt()
 * @method void setStartAt(?int $startAt)
 * @method int|null getEndAt()
 * @method void setEndAt(?int $endAt)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 */
class CalendarLink extends Entity implements \JsonSerializable {
	protected string $resourceType = '';
	protected string $resourceId = '';
	protected string $calendarUri = '';
	protected string $eventUri = '';
	protected ?string $summary = null;
	// Bewusste Schattenkopie von Start/Ende für die Kollisionserkennung
	// (ADR-0020) — null bei Terminen ohne Mitarbeiter-Zuweisung (Phase 3/4).
	protected ?string $assignedUserId = null;
	protected ?int $startAt = null;
	protected ?int $endAt = null;
	protected int $createdAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('startAt', 'integer');
		$this->addType('endAt', 'integer');
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
			'assignedUserId' => $this->getAssignedUserId(),
			'startAt' => $this->getStartAt(),
			'endAt' => $this->getEndAt(),
		];
	}
}
