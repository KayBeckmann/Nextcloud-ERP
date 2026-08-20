<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method float getHours()
 * @method void setHours(float $hours)
 * @method string getActionType()
 * @method void setActionType(string $actionType)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method string|null getNotes()
 * @method void setNotes(?string $notes)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class OvertimeAction extends Entity implements \JsonSerializable {
	protected string $userId = '';
	protected float $hours = 0.0;
	// actionType hat keinen DB-Default und ist nie real leer — '' als
	// Entity-Default ist damit ungefährlich (siehe ADR-0011/Phase 5).
	protected string $actionType = '';
	// status hat einen DB-Default ('requested'), der Entity-Default spiegelt
	// ihn bewusst (siehe TimeEntry::$billable-Kommentar).
	protected string $status = 'requested';
	protected ?string $notes = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('hours', 'float');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'userId' => $this->getUserId(),
			'hours' => $this->getHours(),
			'actionType' => $this->getActionType(),
			'status' => $this->getStatus(),
			'notes' => $this->getNotes(),
		];
	}
}
