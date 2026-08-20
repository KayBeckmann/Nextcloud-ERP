<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string|null getCreditNoteNumber()
 * @method void setCreditNoteNumber(?string $creditNoteNumber)
 * @method int getInvoiceId()
 * @method void setInvoiceId(int $invoiceId)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method string|null getReason()
 * @method void setReason(?string $reason)
 * @method bool getCancelsInvoice()
 * @method void setCancelsInvoice(bool $cancelsInvoice)
 * @method int|null getIssuedAt()
 * @method void setIssuedAt(?int $issuedAt)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class CreditNote extends Entity implements \JsonSerializable {
	// Wie Invoice::$invoiceNumber: erst bei issue() vergeben (ADR-0013),
	// null ist ungefährlich fürs Entity-Dirty-Tracking.
	protected ?string $creditNoteNumber = null;
	protected int $invoiceId = 0;
	protected string $status = 'draft';
	protected ?string $reason = null;
	protected bool $cancelsInvoice = false;
	protected ?int $issuedAt = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('invoiceId', 'integer');
		$this->addType('cancelsInvoice', 'boolean');
		$this->addType('issuedAt', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'creditNoteNumber' => $this->getCreditNoteNumber(),
			'invoiceId' => $this->getInvoiceId(),
			'status' => $this->getStatus(),
			'reason' => $this->getReason(),
			'cancelsInvoice' => $this->getCancelsInvoice(),
			'issuedAt' => $this->getIssuedAt(),
		];
	}
}
