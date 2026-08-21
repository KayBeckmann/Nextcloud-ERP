<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\Invoice;
use OCA\ERP\Db\InvoiceMapper;
use OCA\ERP\Db\InvoicePosition;
use OCA\ERP\Db\InvoicePositionMapper;
use OCA\ERP\Db\QuoteMapper;
use OCA\ERP\Db\QuotePositionMapper;
use OCA\ERP\Invoices\InvoiceNumberFormatter;
use OCA\ERP\Quotes\QuoteCalculationService;
use OCP\IDBConnection;
use OCP\IUser;

/**
 * Rechnungen (Roadmap Phase 7, ADR-0013). Positionen sind nur im Entwurf
 * änderbar; das Ausstellen (issue()) vergibt die Rechnungsnummer atomar und
 * macht die Rechnung unveränderlich — Korrekturen laufen danach nur noch
 * über CreditNoteService.
 */
class InvoiceService {
	public function __construct(
		private InvoiceMapper $mapper,
		private InvoicePositionMapper $positionMapper,
		private QuoteMapper $quoteMapper,
		private QuotePositionMapper $quotePositionMapper,
		private IDBConnection $db,
		private ErpFolderService $folderService,
		private ProjectService $projectService,
	) {
	}

	/** @return Invoice[] */
	public function listInvoices(?string $status = null, ?int $projectId = null): array {
		return $this->mapper->findAll($status, $projectId);
	}

	/** @throws \OutOfBoundsException */
	public function getInvoice(int $id): Invoice {
		$invoice = $this->mapper->findById($id);
		if ($invoice === null) {
			throw new \OutOfBoundsException("Invoice $id not found");
		}
		return $invoice;
	}

	/** Rechnung inkl. Positionen und berechneter Netto-/MwSt.-Summen. */
	public function getFullInvoice(int $id): array {
		$invoice = $this->getInvoice($id);
		$positions = $this->positionMapper->findByInvoice($id);
		$calculation = $this->calculate($positions);

		return [
			...$invoice->jsonSerialize(),
			'positions' => $positions,
			'calculation' => $calculation,
			'isOverdue' => $this->isOverdue($invoice),
		];
	}

	private function calculate(array $positions): array {
		return QuoteCalculationService::calculate([], array_map(static fn (InvoicePosition $p) => [
			'id' => $p->getId(),
			'groupId' => null,
			'quantity' => $p->getQuantity(),
			'unitPriceNet' => $p->getUnitPriceNet(),
			'vatRatePercent' => $p->getVatRatePercent(),
		], $positions));
	}

	private function isOverdue(Invoice $invoice): bool {
		if ($invoice->getDueDate() === null) {
			return false;
		}
		if (!in_array($invoice->getStatus(), ['issued', 'partially_paid'], true)) {
			return false;
		}
		return $invoice->getDueDate() < date('Y-m-d');
	}

	/** @throws \InvalidArgumentException wenn projectId nicht gesetzt ist (ADR-0015: Rechnungen hängen zwingend an Projekten) */
	public function createDraft(
		string $title,
		string $type,
		int $projectId,
		?int $orderId,
		?string $customerContactUid,
		?string $dueDate,
		?string $notes,
	): Invoice {
		if ($projectId <= 0) {
			throw new \InvalidArgumentException('projectId is required');
		}
		$now = time();
		$invoice = new Invoice();
		$invoice->setTitle($title);
		$invoice->setType($type);
		$invoice->setStatus('draft');
		$invoice->setProjectId($projectId);
		$invoice->setOrderId($orderId);
		$invoice->setCustomerContactUid($customerContactUid);
		$invoice->setDueDate($dueDate);
		$invoice->setNotes($notes);
		$invoice->setCreatedAt($now);
		$invoice->setUpdatedAt($now);
		return $this->mapper->insert($invoice);
	}

	/**
	 * Legt eine Rechnung an und übernimmt alle Positionen eines Angebots
	 * 1:1 (Snapshot-Kopie, keine Live-Referenz auf das Angebot danach).
	 *
	 * @throws \OutOfBoundsException wenn das Angebot nicht existiert
	 */
	public function createFromQuote(int $quoteId, string $title, string $type, ?string $dueDate, ?string $notes): Invoice {
		$quote = $this->quoteMapper->findById($quoteId);
		if ($quote === null) {
			throw new \OutOfBoundsException("Quote $quoteId not found");
		}

		$invoice = $this->createDraft($title, $type, $quote->getProjectId(), null, $quote->getCustomerContactUid(), $dueDate, $notes);
		$invoice->setQuoteId($quoteId);
		$invoice = $this->mapper->update($invoice);

		foreach ($this->quotePositionMapper->findByQuote($quoteId) as $qp) {
			$position = new InvoicePosition();
			$position->setInvoiceId($invoice->getId());
			$position->setPositionType($qp->getPositionType());
			$position->setReferenceId($qp->getReferenceId());
			$position->setDescription($qp->getDescription());
			$position->setQuantity($qp->getQuantity());
			$position->setUnit($qp->getUnit());
			$position->setUnitPriceNet($qp->getUnitPriceNet());
			$position->setVatRatePercent($qp->getVatRatePercent());
			$position->setPositionOrder($qp->getPositionOrder());
			$this->positionMapper->insert($position);
		}

		return $invoice;
	}

	/**
	 * @throws \OutOfBoundsException wenn die Rechnung nicht existiert
	 * @throws \DomainException wenn die Rechnung nicht mehr im Entwurf ist
	 */
	public function addPosition(
		int $invoiceId,
		string $positionType,
		?int $referenceId,
		string $description,
		float $quantity,
		string $unit,
		float $unitPriceNet,
		float $vatRatePercent,
	): InvoicePosition {
		$invoice = $this->getInvoice($invoiceId);
		$this->requireDraft($invoice);

		$position = new InvoicePosition();
		$position->setInvoiceId($invoiceId);
		$position->setPositionType($positionType);
		$position->setReferenceId($referenceId);
		$position->setDescription($description);
		$position->setQuantity($quantity);
		$position->setUnit($unit !== '' ? $unit : 'Stk');
		$position->setUnitPriceNet($unitPriceNet);
		$position->setVatRatePercent($vatRatePercent);
		$position->setPositionOrder(count($this->positionMapper->findByInvoice($invoiceId)));
		return $this->positionMapper->insert($position);
	}

	/**
	 * @throws \OutOfBoundsException
	 * @throws \DomainException wenn die Rechnung nicht mehr im Entwurf ist
	 */
	public function removePosition(int $invoiceId, int $id): void {
		$invoice = $this->getInvoice($invoiceId);
		$this->requireDraft($invoice);

		$position = $this->positionMapper->findOne($invoiceId, $id);
		if ($position === null) {
			throw new \OutOfBoundsException("Position $id not found in invoice $invoiceId");
		}
		$this->positionMapper->delete($position);
	}

	private function requireDraft(Invoice $invoice): void {
		if ($invoice->getStatus() !== 'draft') {
			throw new \DomainException("Invoice {$invoice->getId()} is not in status 'draft' — positions are immutable once issued");
		}
	}

	/**
	 * Vergibt die Rechnungsnummer atomar, setzt status='issued' und legt ein
	 * Rechnungsdokument im Projektordner an (Rechnungen hängen seit
	 * ADR-0015 immer an einem Projekt) — das Dokument selbst bleibt
	 * optional, ein fehlender Ordnerzugriff lässt das Ausstellen nicht
	 * scheitern (siehe ADR-0013).
	 *
	 * @throws \OutOfBoundsException
	 * @throws \DomainException wenn die Rechnung bereits ausgestellt ist oder keine Positionen hat
	 */
	public function issue(int $id, IUser $issuer): Invoice {
		$invoice = $this->getInvoice($id);
		if ($invoice->getStatus() !== 'draft') {
			throw new \DomainException("Invoice $id is not in status 'draft'");
		}
		$positions = $this->positionMapper->findByInvoice($id);
		if ($positions === []) {
			throw new \DomainException("Invoice $id has no positions");
		}

		$year = (int) date('Y');
		$sequence = $this->nextSequence('invoice', $year);
		$invoice->setInvoiceNumber(InvoiceNumberFormatter::format(InvoiceNumberFormatter::INVOICE_PREFIX, $year, $sequence));
		$invoice->setStatus('issued');
		$invoice->setIssuedAt(time());
		$invoice->setUpdatedAt(time());
		$invoice = $this->mapper->update($invoice);

		$this->tryWriteDocument($invoice, $positions, $issuer);

		return $invoice;
	}

	private function tryWriteDocument(Invoice $invoice, array $positions, IUser $issuer): void {
		try {
			$project = $this->projectService->getProject($invoice->getProjectId());
			$folder = $this->folderService->ensureInvoiceFolder($issuer, $project->getProjectNumber());
			$html = $this->renderHtml($invoice, $positions);
			$fileName = $invoice->getInvoiceNumber() . '.html';
			$file = $folder->nodeExists($fileName) ? $folder->get($fileName) : $folder->newFile($fileName);
			$file->putContent($html);

			$invoice->setDocumentFileId($file->getId());
			$this->mapper->update($invoice);
		} catch (\Throwable) {
			// Dokumentablage ist optional (ADR-0013) — Ausstellen der
			// Rechnung selbst ist zu diesem Zeitpunkt bereits abgeschlossen.
		}
	}

	private function renderHtml(Invoice $invoice, array $positions): string {
		$calc = $this->calculate($positions);
		$rows = '';
		foreach ($positions as $p) {
			$rows .= sprintf(
				"<tr><td>%s</td><td>%s</td><td>%s %s</td><td>%s €</td><td>%s %%</td><td>%s €</td></tr>\n",
				htmlspecialchars($p->getDescription()),
				htmlspecialchars($p->getPositionType()),
				htmlspecialchars((string)$p->getQuantity()),
				htmlspecialchars($p->getUnit()),
				htmlspecialchars(number_format($p->getUnitPriceNet(), 2, ',', '.')),
				htmlspecialchars((string)$p->getVatRatePercent()),
				htmlspecialchars(number_format($p->getQuantity() * $p->getUnitPriceNet(), 2, ',', '.')),
			);
		}

		return sprintf(
			"<!DOCTYPE html>\n<html lang=\"de\"><head><meta charset=\"utf-8\"><title>%s</title></head><body>\n" .
			"<h1>Rechnung %s</h1>\n<p>%s</p>\n" .
			"<table border=\"1\" cellspacing=\"0\" cellpadding=\"4\">\n<thead><tr><th>Beschreibung</th><th>Typ</th><th>Menge</th><th>EP netto</th><th>MwSt.</th><th>Gesamt netto</th></tr></thead>\n<tbody>\n%s</tbody>\n</table>\n" .
			"<p>Netto-Zwischensumme: %s €<br>Brutto-Gesamt: %s €</p>\n</body></html>\n",
			htmlspecialchars((string)$invoice->getInvoiceNumber()),
			htmlspecialchars((string)$invoice->getInvoiceNumber()),
			htmlspecialchars($invoice->getTitle()),
			$rows,
			number_format($calc['netSubtotal'], 2, ',', '.'),
			number_format($calc['grossTotal'], 2, ',', '.'),
		);
	}

	/**
	 * Erfasst eine (Teil-)Zahlung und leitet den Status live daraus ab.
	 *
	 * @throws \OutOfBoundsException
	 * @throws \DomainException wenn die Rechnung noch nicht ausgestellt oder bereits storniert ist
	 */
	public function recordPayment(int $id, float $amount): Invoice {
		$invoice = $this->getInvoice($id);
		if (!in_array($invoice->getStatus(), ['issued', 'partially_paid', 'paid'], true)) {
			throw new \DomainException("Invoice $id must be issued before payments can be recorded");
		}

		$positions = $this->positionMapper->findByInvoice($id);
		$grossTotal = $this->calculate($positions)['grossTotal'];

		$invoice->setPaidAmount(round($invoice->getPaidAmount() + $amount, 2));
		$invoice->setStatus($invoice->getPaidAmount() >= $grossTotal ? 'paid' : 'partially_paid');
		$invoice->setUpdatedAt(time());
		return $this->mapper->update($invoice);
	}

	/** Wird auch von CreditNoteService::issue() für den Vollstorno genutzt. */
	public function markCancelled(int $id): Invoice {
		$invoice = $this->getInvoice($id);
		$invoice->setStatus('cancelled');
		$invoice->setUpdatedAt(time());
		return $this->mapper->update($invoice);
	}

	/**
	 * Atomare Sequenzvergabe je Jahr+Art (ADR-0013) — bewusst ohne Entity/
	 * Mapper, da es eine reine Zähloperation ist, kein abfragbares
	 * Fachobjekt.
	 */
	public function nextSequence(string $kind, int $year): int {
		$this->db->beginTransaction();
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')->from('erp_invoice_counters')
				->where($qb->expr()->eq('year', $qb->createNamedParameter($year, \PDO::PARAM_INT)))
				->andWhere($qb->expr()->eq('kind', $qb->createNamedParameter($kind)));
			$row = $qb->executeQuery()->fetch();

			if ($row === false) {
				$sequence = 1;
				$insert = $this->db->getQueryBuilder();
				$insert->insert('erp_invoice_counters')
					->setValue('year', $insert->createNamedParameter($year, \PDO::PARAM_INT))
					->setValue('kind', $insert->createNamedParameter($kind))
					->setValue('next_sequence', $insert->createNamedParameter($sequence + 1, \PDO::PARAM_INT));
				$insert->executeStatement();
			} else {
				$sequence = (int) $row['next_sequence'];
				$update = $this->db->getQueryBuilder();
				$update->update('erp_invoice_counters')
					->set('next_sequence', $update->createNamedParameter($sequence + 1, \PDO::PARAM_INT))
					->where($update->expr()->eq('id', $update->createNamedParameter((int)$row['id'], \PDO::PARAM_INT)));
				$update->executeStatement();
			}

			$this->db->commit();
			return $sequence;
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}
}
