<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\CreditNote;
use OCA\ERP\Db\CreditNoteMapper;
use OCA\ERP\Db\CreditNotePosition;
use OCA\ERP\Db\CreditNotePositionMapper;
use OCA\ERP\Db\InvoicePositionMapper;
use OCA\ERP\Invoices\InvoiceNumberFormatter;
use OCA\ERP\Quotes\QuoteCalculationService;

/**
 * Gutschriften (Roadmap Phase 7, ADR-0013) — der einzige Korrekturweg für
 * eine bereits ausgestellte Rechnung. Ein Vollstorno setzt beim Ausstellen
 * zusätzlich den Rechnungsstatus auf 'cancelled'; eine Teilkorrektur lässt
 * ihn unverändert.
 */
class CreditNoteService {
	public function __construct(
		private CreditNoteMapper $mapper,
		private CreditNotePositionMapper $positionMapper,
		private InvoicePositionMapper $invoicePositionMapper,
		private InvoiceService $invoiceService,
	) {
	}

	/** @return CreditNote[] */
	public function listForInvoice(int $invoiceId): array {
		return $this->mapper->findByInvoice($invoiceId);
	}

	/** @throws \OutOfBoundsException */
	public function get(int $id): CreditNote {
		$creditNote = $this->mapper->findById($id);
		if ($creditNote === null) {
			throw new \OutOfBoundsException("Credit note $id not found");
		}
		return $creditNote;
	}

	public function getFull(int $id): array {
		$creditNote = $this->get($id);
		$positions = $this->positionMapper->findByCreditNote($id);
		$calculation = QuoteCalculationService::calculate([], array_map(static fn (CreditNotePosition $p) => [
			'id' => $p->getId(),
			'groupId' => null,
			'quantity' => $p->getQuantity(),
			'unitPriceNet' => $p->getUnitPriceNet(),
			'vatRatePercent' => $p->getVatRatePercent(),
		], $positions));

		return [
			...$creditNote->jsonSerialize(),
			'positions' => $positions,
			'calculation' => $calculation,
		];
	}

	/**
	 * Vollstorno: kopiert alle Positionen der Rechnung unverändert.
	 *
	 * @throws \OutOfBoundsException wenn die Rechnung nicht existiert
	 */
	public function createFullCancellation(int $invoiceId, string $reason): CreditNote {
		$this->invoiceService->getInvoice($invoiceId);

		$creditNote = $this->createDraft($invoiceId, $reason, true);
		foreach ($this->invoicePositionMapper->findByInvoice($invoiceId) as $ip) {
			$position = new CreditNotePosition();
			$position->setCreditNoteId($creditNote->getId());
			$position->setDescription($ip->getDescription());
			$position->setQuantity($ip->getQuantity());
			$position->setUnit($ip->getUnit());
			$position->setUnitPriceNet($ip->getUnitPriceNet());
			$position->setVatRatePercent($ip->getVatRatePercent());
			$position->setPositionOrder($ip->getPositionOrder());
			$this->positionMapper->insert($position);
		}
		return $creditNote;
	}

	/** @throws \OutOfBoundsException wenn die Rechnung nicht existiert */
	public function createPartial(int $invoiceId, string $reason): CreditNote {
		$this->invoiceService->getInvoice($invoiceId);
		return $this->createDraft($invoiceId, $reason, false);
	}

	private function createDraft(int $invoiceId, string $reason, bool $cancelsInvoice): CreditNote {
		$now = time();
		$creditNote = new CreditNote();
		$creditNote->setInvoiceId($invoiceId);
		$creditNote->setStatus('draft');
		$creditNote->setReason($reason);
		$creditNote->setCancelsInvoice($cancelsInvoice);
		$creditNote->setCreatedAt($now);
		$creditNote->setUpdatedAt($now);
		return $this->mapper->insert($creditNote);
	}

	/**
	 * @throws \OutOfBoundsException
	 * @throws \DomainException wenn nicht mehr im Entwurf
	 */
	public function addPosition(int $creditNoteId, string $description, float $quantity, string $unit, float $unitPriceNet, float $vatRatePercent): CreditNotePosition {
		$creditNote = $this->get($creditNoteId);
		if ($creditNote->getStatus() !== 'draft') {
			throw new \DomainException("Credit note $creditNoteId is not in status 'draft'");
		}

		$position = new CreditNotePosition();
		$position->setCreditNoteId($creditNoteId);
		$position->setDescription($description);
		$position->setQuantity($quantity);
		$position->setUnit($unit !== '' ? $unit : 'Stk');
		$position->setUnitPriceNet($unitPriceNet);
		$position->setVatRatePercent($vatRatePercent);
		$position->setPositionOrder(count($this->positionMapper->findByCreditNote($creditNoteId)));
		return $this->positionMapper->insert($position);
	}

	/**
	 * Vergibt die Gutschriftnummer atomar. Bei `cancelsInvoice = true` wird
	 * zusätzlich die referenzierte Rechnung storniert.
	 *
	 * @throws \OutOfBoundsException
	 * @throws \DomainException wenn bereits ausgestellt oder keine Positionen vorhanden
	 */
	public function issue(int $id): CreditNote {
		$creditNote = $this->get($id);
		if ($creditNote->getStatus() !== 'draft') {
			throw new \DomainException("Credit note $id is not in status 'draft'");
		}
		if ($this->positionMapper->findByCreditNote($id) === []) {
			throw new \DomainException("Credit note $id has no positions");
		}

		$year = (int) date('Y');
		$sequence = $this->invoiceService->nextSequence('credit_note', $year);
		$creditNote->setCreditNoteNumber(InvoiceNumberFormatter::format(InvoiceNumberFormatter::CREDIT_NOTE_PREFIX, $year, $sequence));
		$creditNote->setStatus('issued');
		$creditNote->setIssuedAt(time());
		$creditNote->setUpdatedAt(time());
		$creditNote = $this->mapper->update($creditNote);

		if ($creditNote->getCancelsInvoice()) {
			$this->invoiceService->markCancelled($creditNote->getInvoiceId());
		}

		return $creditNote;
	}
}
