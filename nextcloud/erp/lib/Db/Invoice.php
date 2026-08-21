<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string|null getInvoiceNumber()
 * @method void setInvoiceNumber(?string $invoiceNumber)
 * @method string getType()
 * @method void setType(string $type)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method int getProjectId()
 * @method void setProjectId(int $projectId)
 * @method int|null getOrderId()
 * @method void setOrderId(?int $orderId)
 * @method int|null getQuoteId()
 * @method void setQuoteId(?int $quoteId)
 * @method string|null getCustomerContactUid()
 * @method void setCustomerContactUid(?string $customerContactUid)
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method int|null getIssuedAt()
 * @method void setIssuedAt(?int $issuedAt)
 * @method string|null getDueDate()
 * @method void setDueDate(?string $dueDate)
 * @method float getPaidAmount()
 * @method void setPaidAmount(float $paidAmount)
 * @method string|null getNotes()
 * @method void setNotes(?string $notes)
 * @method int|null getDocumentFileId()
 * @method void setDocumentFileId(?int $documentFileId)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class Invoice extends Entity implements \JsonSerializable {
	// Kein sinnvoller Default: eine echte Rechnungsnummer wird erst bei
	// issue() vergeben (ADR-0013) und ist nie leer/gleich einem Platzhalter
	// — null ist damit ungefährlich für Nextclouds Entity-Dirty-Tracking
	// (siehe ADR-0011/Phase 5).
	protected ?string $invoiceNumber = null;
	protected string $type = 'invoice';
	protected string $status = 'draft';
	// project_id ist seit ADR-0015 in der DB NOT NULL ohne Default; echte
	// Projekt-IDs starten nie bei 0 (siehe Quote::$projectId-Kommentar).
	protected int $projectId = 0;
	protected ?int $orderId = null;
	protected ?int $quoteId = null;
	protected ?string $customerContactUid = null;
	protected string $title = '';
	protected ?int $issuedAt = null;
	protected ?string $dueDate = null;
	protected float $paidAmount = 0.0;
	protected ?string $notes = null;
	protected ?int $documentFileId = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('projectId', 'integer');
		$this->addType('orderId', 'integer');
		$this->addType('quoteId', 'integer');
		$this->addType('issuedAt', 'integer');
		$this->addType('paidAmount', 'float');
		$this->addType('documentFileId', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'invoiceNumber' => $this->getInvoiceNumber(),
			'type' => $this->getType(),
			'status' => $this->getStatus(),
			'projectId' => $this->getProjectId(),
			'orderId' => $this->getOrderId(),
			'quoteId' => $this->getQuoteId(),
			'customerContactUid' => $this->getCustomerContactUid(),
			'title' => $this->getTitle(),
			'issuedAt' => $this->getIssuedAt(),
			'dueDate' => $this->getDueDate(),
			'paidAmount' => $this->getPaidAmount(),
			'notes' => $this->getNotes(),
			'documentFileId' => $this->getDocumentFileId(),
		];
	}
}
