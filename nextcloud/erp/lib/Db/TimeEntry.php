<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int|null getProjectId()
 * @method void setProjectId(?int $projectId)
 * @method int|null getOrderId()
 * @method void setOrderId(?int $orderId)
 * @method int getWorkTypeId()
 * @method void setWorkTypeId(int $workTypeId)
 * @method string getEntryDate()
 * @method void setEntryDate(string $entryDate)
 * @method int getDurationMinutes()
 * @method void setDurationMinutes(int $durationMinutes)
 * @method int getBreakMinutes()
 * @method void setBreakMinutes(int $breakMinutes)
 * @method bool getBillable()
 * @method void setBillable(bool $billable)
 * @method float getRateSnapshot()
 * @method void setRateSnapshot(float $rateSnapshot)
 * @method string|null getNotes()
 * @method void setNotes(?string $notes)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class TimeEntry extends Entity implements \JsonSerializable {
	// userId/entryDate haben keinen DB-Default und sind nie real leer — ''
	// als Entity-Default ist damit ungefährlich (siehe ADR-0011/Phase 5:
	// QuotePosition::$positionType).
	protected string $userId = '';
	protected ?int $projectId = null;
	protected ?int $orderId = null;
	protected int $workTypeId = 0;
	protected string $entryDate = '';
	protected int $durationMinutes = 0;
	protected int $breakMinutes = 0;
	// billable hat einen DB-Default (true) — der Entity-Default spiegelt ihn
	// bewusst, damit das INSERT bei billable=true (Skip wegen "unverändert")
	// über den DB-Default weiterhin korrekt landet, UND bei billable=false
	// explizit als geänderter Wert erkannt und mitgeschrieben wird.
	protected bool $billable = true;
	protected float $rateSnapshot = 0.0;
	protected ?string $notes = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('projectId', 'integer');
		$this->addType('orderId', 'integer');
		$this->addType('workTypeId', 'integer');
		$this->addType('durationMinutes', 'integer');
		$this->addType('breakMinutes', 'integer');
		$this->addType('billable', 'boolean');
		$this->addType('rateSnapshot', 'float');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'userId' => $this->getUserId(),
			'projectId' => $this->getProjectId(),
			'orderId' => $this->getOrderId(),
			'workTypeId' => $this->getWorkTypeId(),
			'entryDate' => $this->getEntryDate(),
			'durationMinutes' => $this->getDurationMinutes(),
			'breakMinutes' => $this->getBreakMinutes(),
			'billable' => $this->getBillable(),
			'rateSnapshot' => $this->getRateSnapshot(),
			'notes' => $this->getNotes(),
		];
	}
}
