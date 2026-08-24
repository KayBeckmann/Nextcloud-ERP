<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string|null getDeliveryNoteNumber()
 * @method void setDeliveryNoteNumber(?string $deliveryNoteNumber)
 * @method int getProjectId()
 * @method void setProjectId(int $projectId)
 * @method int|null getOrderId()
 * @method void setOrderId(?int $orderId)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method int|null getDeliveredAt()
 * @method void setDeliveredAt(?int $deliveredAt)
 * @method string|null getNotes()
 * @method void setNotes(?string $notes)
 * @method int|null getDocumentFileId()
 * @method void setDocumentFileId(?int $documentFileId)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class DeliveryNote extends Entity implements \JsonSerializable {
	protected ?string $deliveryNoteNumber = null;
	// project_id: siehe Quote::$projectId-Kommentar (ADR-0015, echte
	// Projekt-IDs starten nie bei 0).
	protected int $projectId = 0;
	protected ?int $orderId = null;
	protected string $status = 'draft';
	protected ?int $deliveredAt = null;
	protected ?string $notes = null;
	protected ?int $documentFileId = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('projectId', 'integer');
		$this->addType('orderId', 'integer');
		$this->addType('deliveredAt', 'integer');
		$this->addType('documentFileId', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'deliveryNoteNumber' => $this->getDeliveryNoteNumber(),
			'projectId' => $this->getProjectId(),
			'orderId' => $this->getOrderId(),
			'status' => $this->getStatus(),
			'deliveredAt' => $this->getDeliveredAt(),
			'notes' => $this->getNotes(),
			'documentFileId' => $this->getDocumentFileId(),
		];
	}
}
