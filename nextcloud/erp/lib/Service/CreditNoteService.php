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
use OCP\IUser;

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
		private ErpFolderService $folderService,
		private ProjectService $projectService,
		private DocumentPdfService $pdfService,
		private DocumentHtmlBuilder $htmlBuilder,
	) {
	}

	/** @return CreditNote[] */
	public function listForInvoice(int $invoiceId): array {
		return $this->mapper->findByInvoice($invoiceId);
	}

	/** @return CreditNote[] */
	public function listForProject(int $projectId): array {
		return $this->mapper->findByProject($projectId);
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
			'discountPercent' => $p->getDiscountPercent(),
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
		$invoice = $this->invoiceService->getInvoice($invoiceId);

		$creditNote = $this->createDraft($invoiceId, $invoice->getProjectId(), $reason, true);
		foreach ($this->invoicePositionMapper->findByInvoice($invoiceId) as $ip) {
			$position = new CreditNotePosition();
			$position->setCreditNoteId($creditNote->getId());
			$position->setDescription($ip->getDescription());
			$position->setQuantity($ip->getQuantity());
			$position->setUnit($ip->getUnit());
			$position->setUnitPriceNet($ip->getUnitPriceNet());
			$position->setVatRatePercent($ip->getVatRatePercent());
			$position->setDiscountPercent($ip->getDiscountPercent());
			$position->setPositionOrder($ip->getPositionOrder());
			$this->positionMapper->insert($position);
		}
		return $creditNote;
	}

	/** @throws \OutOfBoundsException wenn die Rechnung nicht existiert */
	public function createPartial(int $invoiceId, string $reason): CreditNote {
		$invoice = $this->invoiceService->getInvoice($invoiceId);
		return $this->createDraft($invoiceId, $invoice->getProjectId(), $reason, false);
	}

	private function createDraft(int $invoiceId, int $projectId, string $reason, bool $cancelsInvoice): CreditNote {
		$now = time();
		$creditNote = new CreditNote();
		$creditNote->setInvoiceId($invoiceId);
		$creditNote->setProjectId($projectId);
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
	public function addPosition(int $creditNoteId, string $description, float $quantity, string $unit, float $unitPriceNet, float $vatRatePercent, float $discountPercent = 0.0): CreditNotePosition {
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
		$position->setDiscountPercent($discountPercent);
		$position->setPositionOrder(count($this->positionMapper->findByCreditNote($creditNoteId)));
		return $this->positionMapper->insert($position);
	}

	/**
	 * Bereits angelegte Position korrigieren (ADR-0022) — wie addPosition()
	 * nur solange die Gutschrift im Entwurf ist.
	 *
	 * @throws \OutOfBoundsException
	 * @throws \DomainException wenn nicht mehr im Entwurf
	 */
	public function updatePosition(int $creditNoteId, int $id, string $description, float $quantity, string $unit, float $unitPriceNet, float $vatRatePercent, float $discountPercent = 0.0): CreditNotePosition {
		$creditNote = $this->get($creditNoteId);
		if ($creditNote->getStatus() !== 'draft') {
			throw new \DomainException("Credit note $creditNoteId is not in status 'draft'");
		}
		$position = $this->positionMapper->findOne($creditNoteId, $id);
		if ($position === null) {
			throw new \OutOfBoundsException("Position $id not found in credit note $creditNoteId");
		}
		$position->setDescription($description);
		$position->setQuantity($quantity);
		$position->setUnit($unit !== '' ? $unit : 'Stk');
		$position->setUnitPriceNet($unitPriceNet);
		$position->setVatRatePercent($vatRatePercent);
		$position->setDiscountPercent($discountPercent);
		return $this->positionMapper->update($position);
	}

	/**
	 * Vergibt die Gutschriftnummer atomar. Bei `cancelsInvoice = true` wird
	 * zusätzlich die referenzierte Rechnung storniert.
	 *
	 * @throws \OutOfBoundsException
	 * @throws \DomainException wenn bereits ausgestellt oder keine Positionen vorhanden
	 */
	public function issue(int $id, ?IUser $issuer = null): CreditNote {
		$creditNote = $this->get($id);
		if ($creditNote->getStatus() !== 'draft') {
			throw new \DomainException("Credit note $id is not in status 'draft'");
		}
		$positions = $this->positionMapper->findByCreditNote($id);
		if ($positions === []) {
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

		if ($issuer !== null) {
			$this->tryWriteDocument($creditNote, $positions, $issuer);
		}

		return $creditNote;
	}

	/** @param CreditNotePosition[] $positions */
	private function tryWriteDocument(CreditNote $creditNote, array $positions, IUser $issuer): void {
		try {
			$project = $this->projectService->getProject($creditNote->getProjectId());
			$folder = $this->folderService->ensureCreditNoteFolder($issuer, $project->getProjectNumber());
			$html = $this->renderHtml($creditNote, $positions);
			$fileId = $this->pdfService->writePdf($folder, (string)$creditNote->getCreditNoteNumber(), $html);

			$creditNote->setDocumentFileId($fileId);
			$this->mapper->update($creditNote);
		} catch (\Throwable) {
			// Dokumentablage ist optional (ADR-0013/ADR-0021) — Ausstellen
			// der Gutschrift selbst ist zu diesem Zeitpunkt bereits
			// abgeschlossen.
		}
	}

	/** @param CreditNotePosition[] $positions */
	private function renderHtml(CreditNote $creditNote, array $positions): string {
		$calc = QuoteCalculationService::calculate([], array_map(static fn (CreditNotePosition $p) => [
			'id' => $p->getId(),
			'groupId' => null,
			'quantity' => $p->getQuantity(),
			'unitPriceNet' => $p->getUnitPriceNet(),
			'vatRatePercent' => $p->getVatRatePercent(),
			'discountPercent' => $p->getDiscountPercent(),
		], $positions));

		// Gutschriften haben keinen eigenen Kunden — der kommt von der
		// referenzierten Rechnung.
		$customerContactUid = $this->invoiceService->getInvoice($creditNote->getInvoiceId())->getCustomerContactUid();

		$creditNoteNumber = (string) $creditNote->getCreditNoteNumber();
		$html = $this->htmlBuilder->header('Gutschrift', $creditNoteNumber, (string) $creditNote->getReason(), $creditNote->getCreatedAt(), null, $customerContactUid);
		$html .= $this->htmlBuilder->positionsTable([], array_map(static fn (CreditNotePosition $p) => $p->jsonSerialize(), $positions), true);
		$html .= $this->htmlBuilder->summary($calc);
		$html .= $this->htmlBuilder->footer();

		return $this->htmlBuilder->wrap($creditNoteNumber, $html);
	}
}
