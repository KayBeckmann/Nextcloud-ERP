<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int getAbsenceTypeId()
 * @method void setAbsenceTypeId(int $absenceTypeId)
 * @method string getStartDate()
 * @method void setStartDate(string $startDate)
 * @method string getEndDate()
 * @method void setEndDate(string $endDate)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method string|null getNotes()
 * @method void setNotes(?string $notes)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class AbsenceRequest extends Entity implements \JsonSerializable {
	protected string $userId = '';
	protected int $absenceTypeId = 0;
	protected string $startDate = '';
	protected string $endDate = '';
	// status hat einen DB-Default ('requested'), der Entity-Default spiegelt
	// ihn bewusst (siehe TimeEntry::$billable-Kommentar, ADR-0011/Phase 5).
	protected string $status = 'requested';
	protected ?string $notes = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('absenceTypeId', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'userId' => $this->getUserId(),
			'absenceTypeId' => $this->getAbsenceTypeId(),
			'startDate' => $this->getStartDate(),
			'endDate' => $this->getEndDate(),
			'status' => $this->getStatus(),
			'notes' => $this->getNotes(),
		];
	}
}
