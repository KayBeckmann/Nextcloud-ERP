<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\Quote;
use OCA\ERP\Db\QuoteGroup;
use OCA\ERP\Db\QuoteGroupMapper;
use OCA\ERP\Db\QuoteMapper;
use OCA\ERP\Db\QuotePosition;
use OCA\ERP\Db\QuotePositionMapper;
use OCA\ERP\Quotes\QuoteCalculationService;
use OCP\IUser;

/**
 * Angebote (Roadmap Phase 5, ADR-0011). Preise/Sätze werden direkt auf der
 * Position gespeichert (Snapshot-Prinzip, kein Live-Lookup) — der Client
 * schickt den zum Zeitpunkt des Hinzufügens gültigen Preis/Satz mit.
 */
class QuoteService {
	public function __construct(
		private QuoteMapper $mapper,
		private QuoteGroupMapper $groupMapper,
		private QuotePositionMapper $positionMapper,
		private ErpFolderService $folderService,
		private ProjectService $projectService,
		private DocumentPdfService $pdfService,
	) {
	}

	/** @return Quote[] */
	public function listQuotes(?string $status = null, ?int $projectId = null): array {
		return $this->mapper->findAll($status, $projectId);
	}

	/** @throws \OutOfBoundsException */
	public function getQuote(int $id): Quote {
		$quote = $this->mapper->findById($id);
		if ($quote === null) {
			throw new \OutOfBoundsException("Quote $id not found");
		}
		return $quote;
	}

	/** Angebot inkl. Gruppen, Positionen und berechneter Summen. */
	public function getFullQuote(int $id): array {
		$quote = $this->getQuote($id);
		$groups = $this->groupMapper->findByQuote($id);
		$positions = $this->positionMapper->findByQuote($id);

		$calculation = QuoteCalculationService::calculate(
			array_map(static fn (QuoteGroup $g) => ['id' => $g->getId(), 'title' => $g->getTitle()], $groups),
			array_map(static fn (QuotePosition $p) => [
				'id' => $p->getId(),
				'groupId' => $p->getGroupId(),
				'quantity' => $p->getQuantity(),
				'unitPriceNet' => $p->getUnitPriceNet(),
				'vatRatePercent' => $p->getVatRatePercent(),
			], $positions),
		);

		return [
			...$quote->jsonSerialize(),
			'groups' => $groups,
			'positions' => $positions,
			'calculation' => $calculation,
		];
	}

	/** @throws \InvalidArgumentException wenn projectId nicht gesetzt ist (ADR-0015: Angebote hängen zwingend an Projekten) */
	public function createQuote(string $title, int $projectId, ?string $customerContactUid, ?string $notes): Quote {
		if ($projectId <= 0) {
			throw new \InvalidArgumentException('projectId is required');
		}
		$now = time();
		$quote = new Quote();
		$quote->setTitle($title);
		$quote->setProjectId($projectId);
		$quote->setCustomerContactUid($customerContactUid);
		$quote->setStatus('draft');
		$quote->setNotes($notes);
		$quote->setCreatedAt($now);
		$quote->setUpdatedAt($now);
		$quote = $this->mapper->insert($quote);

		$quote->setQuoteNumber(sprintf('A-%05d', $quote->getId()));
		return $this->mapper->update($quote);
	}

	/**
	 * @throws \OutOfBoundsException
	 * @throws \InvalidArgumentException wenn projectId nicht gesetzt ist (ADR-0015)
	 */
	public function updateQuote(
		int $id,
		string $title,
		string $status,
		int $projectId,
		?string $customerContactUid,
		?int $validUntil,
		?string $notes,
		?IUser $issuer = null,
	): Quote {
		if ($projectId <= 0) {
			throw new \InvalidArgumentException('projectId is required');
		}
		$quote = $this->getQuote($id);
		$quote->setTitle($title);
		$quote->setStatus($status);
		$quote->setProjectId($projectId);
		$quote->setCustomerContactUid($customerContactUid);
		$quote->setValidUntil($validUntil);
		$quote->setNotes($notes);
		$quote->setUpdatedAt(time());
		$becomesSent = $status === 'sent' && $quote->getSentAt() === null;
		if ($becomesSent) {
			$quote->setSentAt(time());
		}
		$quote = $this->mapper->update($quote);

		// PDF-Erzeugung (ADR-0021) nur beim erstmaligen Wechsel nach 'sent',
		// nicht bei jedem Update — $issuer ist optional, weil ältere/interne
		// Aufrufer (z. B. Tests, die nur den Status ändern) keinen Nutzer
		// mitgeben müssen; ohne $issuer entfällt nur die PDF-Ablage.
		if ($becomesSent && $issuer !== null) {
			$this->tryWriteDocument($quote, $issuer);
		}

		return $quote;
	}

	private function tryWriteDocument(Quote $quote, IUser $issuer): void {
		try {
			$project = $this->projectService->getProject($quote->getProjectId());
			$folder = $this->folderService->ensureQuoteFolder($issuer, $project->getProjectNumber());
			$html = $this->renderHtml($quote);
			$fileId = $this->pdfService->writePdf($folder, (string)$quote->getQuoteNumber(), $html);

			$quote->setDocumentFileId($fileId);
			$this->mapper->update($quote);
		} catch (\Throwable) {
			// Dokumentablage ist optional (ADR-0021).
		}
	}

	private function renderHtml(Quote $quote): string {
		$positions = $this->positionMapper->findByQuote($quote->getId());
		$calc = QuoteCalculationService::calculate([], array_map(static fn (QuotePosition $p) => [
			'id' => $p->getId(),
			'groupId' => null,
			'quantity' => $p->getQuantity(),
			'unitPriceNet' => $p->getUnitPriceNet(),
			'vatRatePercent' => $p->getVatRatePercent(),
		], $positions));

		$rows = '';
		foreach ($positions as $p) {
			$rows .= sprintf(
				"<tr><td>%s</td><td>%s %s</td><td>%s €</td><td>%s %%</td><td>%s €</td></tr>\n",
				htmlspecialchars($p->getDescription()),
				htmlspecialchars((string)$p->getQuantity()),
				htmlspecialchars($p->getUnit()),
				htmlspecialchars(number_format($p->getUnitPriceNet(), 2, ',', '.')),
				htmlspecialchars((string)$p->getVatRatePercent()),
				htmlspecialchars(number_format($p->getQuantity() * $p->getUnitPriceNet(), 2, ',', '.')),
			);
		}

		return sprintf(
			"<!DOCTYPE html>\n<html lang=\"de\"><head><meta charset=\"utf-8\"><title>%s</title></head><body>\n" .
			"<h1>Angebot %s</h1>\n<p>%s</p>\n" .
			"<table border=\"1\" cellspacing=\"0\" cellpadding=\"4\">\n<thead><tr><th>Beschreibung</th><th>Menge</th><th>EP netto</th><th>MwSt.</th><th>Gesamt netto</th></tr></thead>\n<tbody>\n%s</tbody>\n</table>\n" .
			"<p>Netto-Zwischensumme: %s €<br>Brutto-Gesamt: %s €</p>\n</body></html>\n",
			htmlspecialchars((string)$quote->getQuoteNumber()),
			htmlspecialchars((string)$quote->getQuoteNumber()),
			htmlspecialchars($quote->getTitle()),
			$rows,
			number_format($calc['netSubtotal'], 2, ',', '.'),
			number_format($calc['grossTotal'], 2, ',', '.'),
		);
	}

	/** @throws \OutOfBoundsException */
	public function addGroup(int $quoteId, string $title): QuoteGroup {
		$this->getQuote($quoteId);
		$group = new QuoteGroup();
		$group->setQuoteId($quoteId);
		$group->setTitle($title);
		$group->setPosition(count($this->groupMapper->findByQuote($quoteId)));
		return $this->groupMapper->insert($group);
	}

	/** @throws \OutOfBoundsException */
	public function addPosition(
		int $quoteId,
		?int $groupId,
		string $positionType,
		?int $referenceId,
		string $description,
		float $quantity,
		string $unit,
		float $unitPriceNet,
		float $vatRatePercent,
	): QuotePosition {
		$this->getQuote($quoteId);
		if ($groupId !== null && $this->groupMapper->findOne($quoteId, $groupId) === null) {
			throw new \OutOfBoundsException("Group $groupId not found in quote $quoteId");
		}

		$position = new QuotePosition();
		$position->setQuoteId($quoteId);
		$position->setGroupId($groupId);
		$position->setPositionType($positionType);
		$position->setReferenceId($referenceId);
		$position->setDescription($description);
		$position->setQuantity($quantity);
		$position->setUnit($unit !== '' ? $unit : 'Stk');
		$position->setUnitPriceNet($unitPriceNet);
		$position->setVatRatePercent($vatRatePercent);
		$position->setPositionOrder(count($this->positionMapper->findByQuote($quoteId)));
		return $this->positionMapper->insert($position);
	}

	/** @throws \OutOfBoundsException */
	public function removePosition(int $quoteId, int $id): void {
		$position = $this->positionMapper->findOne($quoteId, $id);
		if ($position === null) {
			throw new \OutOfBoundsException("Position $id not found in quote $quoteId");
		}
		$this->positionMapper->delete($position);
	}
}
