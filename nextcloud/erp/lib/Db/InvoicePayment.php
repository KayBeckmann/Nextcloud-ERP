<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/** @method int getInvoiceId() @method void setInvoiceId(int $value) @method float getAmount() @method void setAmount(float $value) @method string getPaidAt() @method void setPaidAt(string $value) @method string|null getReference() @method void setReference(?string $value) @method string|null getNotes() @method void setNotes(?string $value) @method string getRecordedBy() @method void setRecordedBy(string $value) @method int getRecordedAt() @method void setRecordedAt(int $value) */
class InvoicePayment extends Entity implements \JsonSerializable {
	protected int $invoiceId = 0;
	protected float $amount = 0.0;
	/** ISO-Datum (YYYY-MM-DD) — vom Nutzer gewähltes Zahlungsdatum, siehe Migration. */
	protected string $paidAt = '';
	protected ?string $reference = null;
	protected ?string $notes = null;
	protected string $recordedBy = '';
	protected int $recordedAt = 0;
	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('invoiceId', 'integer');
		$this->addType('amount', 'float');
		$this->addType('recordedAt', 'integer');
	}
	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'invoiceId' => $this->getInvoiceId(),
			'amount' => $this->getAmount(),
			'paidAt' => $this->getPaidAt(),
			'reference' => $this->getReference(),
			'notes' => $this->getNotes(),
			'recordedBy' => $this->getRecordedBy(),
			'recordedAt' => $this->getRecordedAt(),
		];
	}
}
