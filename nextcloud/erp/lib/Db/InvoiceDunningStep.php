<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/** @method int getInvoiceId() @method void setInvoiceId(int $value) @method int getLevel() @method void setLevel(int $value) @method string|null getNotes() @method void setNotes(?string $value) @method string getCreatedBy() @method void setCreatedBy(string $value) @method int getCreatedAt() @method void setCreatedAt(int $value) */
class InvoiceDunningStep extends Entity implements \JsonSerializable {
	protected int $invoiceId = 0;
	// PHP-Default bewusst 0 (kein gültiger Mahn-Level, siehe
	// recordDunningStep()): Nextclouds Entity markiert ein Feld nur als
	// "dirty" für den Insert, wenn setLevel() einen vom Default
	// ABWEICHENDEN Wert setzt. Ein Default von 1 hätte beim ersten
	// echten Mahnschritt (Level 1) die Spalte im Insert weggelassen ->
	// NOT-NULL-Verletzung in der DB.
	// 1 = Zahlungserinnerung, 2 = erste Mahnung, 3 = zweite/letzte Mahnung — siehe ADR-0025.
	protected int $level = 0;
	protected ?string $notes = null;
	protected string $createdBy = '';
	protected int $createdAt = 0;
	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('invoiceId', 'integer');
		$this->addType('level', 'integer');
		$this->addType('createdAt', 'integer');
	}
	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'invoiceId' => $this->getInvoiceId(),
			'level' => $this->getLevel(),
			'notes' => $this->getNotes(),
			'createdBy' => $this->getCreatedBy(),
			'createdAt' => $this->getCreatedAt(),
		];
	}
}
