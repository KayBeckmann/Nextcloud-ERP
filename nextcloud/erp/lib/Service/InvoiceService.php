<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\DeliveryNoteGroupMapper;
use OCA\ERP\Db\DeliveryNoteMapper;
use OCA\ERP\Db\DeliveryNotePositionMapper;
use OCA\ERP\Db\Invoice;
use OCA\ERP\Db\InvoiceGroup;
use OCA\ERP\Db\InvoiceGroupMapper;
use OCA\ERP\Db\InvoiceMapper;
use OCA\ERP\Db\InvoicePosition;
use OCA\ERP\Db\InvoicePositionMapper;
use OCA\ERP\Db\OrderGroupMapper;
use OCA\ERP\Db\OrderMapper;
use OCA\ERP\Db\OrderPositionMapper;
use OCA\ERP\Db\QuoteGroupMapper;
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
		private InvoiceGroupMapper $groupMapper,
		private QuoteMapper $quoteMapper,
		private QuotePositionMapper $quotePositionMapper,
		private QuoteGroupMapper $quoteGroupMapper,
		private OrderMapper $orderMapper,
		private OrderPositionMapper $orderPositionMapper,
		private OrderGroupMapper $orderGroupMapper,
		private DeliveryNoteMapper $deliveryNoteMapper,
		private DeliveryNotePositionMapper $deliveryNotePositionMapper,
		private DeliveryNoteGroupMapper $deliveryNoteGroupMapper,
		private IDBConnection $db,
		private ErpFolderService $folderService,
		private ProjectService $projectService,
		private DocumentPdfService $pdfService,
		private DocumentHtmlBuilder $htmlBuilder,
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

	/**
	 * Rechnung inkl. Positionen, berechneter Netto-/MwSt.-Summen und —
	 * sofern die Rechnung an einem Auftrag hängt — den Geschwister-
	 * Rechnungen desselben Auftrags (`relatedInvoices`, ADR-0016). Damit
	 * lässt sich in der Schlussrechnung am Ende auflisten, welche
	 * Teilrechnungen/Teilzahlungen bereits erfolgt sind — ohne
	 * automatische Verrechnung (siehe ADR-0016, "Nicht Teil dieser Phase").
	 */
	public function getFullInvoice(int $id): array {
		$invoice = $this->getInvoice($id);
		$positions = $this->positionMapper->findByInvoice($id);
		$groups = $this->groupMapper->findByInvoice($id);
		$calculation = $this->calculate($positions, $groups, $invoice->getDiscountPercent());

		$relatedInvoices = [];
		if ($invoice->getOrderId() !== null) {
			foreach ($this->mapper->findByOrder($invoice->getOrderId(), $id) as $related) {
				$relatedPositions = $this->positionMapper->findByInvoice($related->getId());
				$relatedInvoices[] = [
					...$related->jsonSerialize(),
					'grossTotal' => $this->calculate($relatedPositions, [], $related->getDiscountPercent())['grossTotal'],
				];
			}
		}

		return [
			...$invoice->jsonSerialize(),
			'groups' => $groups,
			'positions' => $positions,
			'calculation' => $calculation,
			'isOverdue' => $this->isOverdue($invoice),
			'relatedInvoices' => $relatedInvoices,
		];
	}

	/** @throws \OutOfBoundsException wenn die Rechnung nicht existiert */
	public function addGroup(int $invoiceId, string $title): InvoiceGroup {
		$this->getInvoice($invoiceId);
		$group = new InvoiceGroup();
		$group->setInvoiceId($invoiceId);
		$group->setTitle($title);
		$group->setPosition(count($this->groupMapper->findByInvoice($invoiceId)));
		return $this->groupMapper->insert($group);
	}

	/** @param InvoiceGroup[] $groups */
	private function calculate(array $positions, array $groups = [], float $documentDiscountPercent = 0.0): array {
		return QuoteCalculationService::calculate(
			array_map(static fn (InvoiceGroup $g) => ['id' => $g->getId(), 'title' => $g->getTitle()], $groups),
			array_map(static fn (InvoicePosition $p) => [
				'id' => $p->getId(),
				'groupId' => $p->getGroupId(),
				'quantity' => $p->getQuantity(),
				'unitPriceNet' => $p->getUnitPriceNet(),
				'vatRatePercent' => $p->getVatRatePercent(),
				'discountPercent' => $p->getDiscountPercent(),
			], $positions),
			$documentDiscountPercent,
		);
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
	 * Legt eine Rechnung an und übernimmt alle Gruppen und Positionen eines
	 * Angebots 1:1 (Snapshot-Kopie, keine Live-Referenz auf das Angebot
	 * danach). Gruppen-Erhalt: Nutzerwunsch 2026-08-21.
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

		$groupIdMap = [];
		foreach ($this->quoteGroupMapper->findByQuote($quoteId) as $qg) {
			$group = new InvoiceGroup();
			$group->setInvoiceId($invoice->getId());
			$group->setTitle($qg->getTitle());
			$group->setPosition($qg->getPosition());
			$group = $this->groupMapper->insert($group);
			$groupIdMap[$qg->getId()] = $group->getId();
		}

		foreach ($this->quotePositionMapper->findByQuote($quoteId) as $qp) {
			$position = new InvoicePosition();
			$position->setInvoiceId($invoice->getId());
			$position->setGroupId($qp->getGroupId() !== null ? ($groupIdMap[$qp->getGroupId()] ?? null) : null);
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
	 * Legt eine Rechnung aus ausgewählten Auftragspositionen an (ADR-0016)
	 * — mit `type='partial'` und einer Teilmenge/Teilmengen ergibt das eine
	 * Teilrechnung durch Positionsauswahl. Jede erzeugte Position bekommt
	 * `order_position_id` gesetzt. Die Gruppen der ausgewählten Positionen
	 * werden mitkopiert (nur die tatsächlich referenzierten, Nutzerwunsch
	 * 2026-08-21).
	 *
	 * @param array<int, array{orderPositionId: int, quantity?: float}> $positions
	 * @throws \OutOfBoundsException wenn Auftrag oder Auftragsposition nicht existiert
	 * @throws \InvalidArgumentException wenn positions leer ist
	 */
	public function createFromOrder(int $orderId, string $title, string $type, ?string $dueDate, ?string $notes, array $positions): Invoice {
		$order = $this->orderMapper->findById($orderId);
		if ($order === null) {
			throw new \OutOfBoundsException("Order $orderId not found");
		}
		if ($positions === []) {
			throw new \InvalidArgumentException('positions must not be empty');
		}

		$orderPositions = [];
		foreach ($positions as $selection) {
			$orderPositionId = (int)($selection['orderPositionId'] ?? 0);
			$op = $this->orderPositionMapper->findOne($orderId, $orderPositionId);
			if ($op === null) {
				throw new \OutOfBoundsException("Order position $orderPositionId not found in order $orderId");
			}
			$orderPositions[] = $op;
		}

		$invoice = $this->createDraft($title, $type, $order->getProjectId(), $orderId, $order->getCustomerContactUid(), $dueDate, $notes);

		$groupIdMap = $this->copyReferencedGroups(
			$orderPositions,
			fn (int $groupId) => $this->orderGroupMapper->findOne($orderId, $groupId),
			function (string $groupTitle, int $groupPosition) use ($invoice) {
				$group = new InvoiceGroup();
				$group->setInvoiceId($invoice->getId());
				$group->setTitle($groupTitle);
				$group->setPosition($groupPosition);
				return $this->groupMapper->insert($group)->getId();
			},
		);

		foreach ($positions as $index => $selection) {
			$op = $orderPositions[$index];
			$quantity = isset($selection['quantity']) ? (float)$selection['quantity'] : $op->getQuantity();

			$position = new InvoicePosition();
			$position->setInvoiceId($invoice->getId());
			$position->setGroupId($op->getGroupId() !== null ? ($groupIdMap[$op->getGroupId()] ?? null) : null);
			$position->setPositionType($op->getPositionType());
			$position->setReferenceId($op->getReferenceId());
			$position->setDescription($op->getDescription());
			$position->setQuantity($quantity);
			$position->setUnit($op->getUnit());
			$position->setUnitPriceNet($op->getUnitPriceNet());
			$position->setVatRatePercent($op->getVatRatePercent());
			$position->setPositionOrder(count($this->positionMapper->findByInvoice($invoice->getId())));
			$position->setOrderPositionId($op->getId());
			$this->positionMapper->insert($position);
		}

		return $invoice;
	}

	/**
	 * Kopiert nur die Gruppen, die von den übergebenen Quellpositionen
	 * tatsächlich referenziert werden (keine leeren Gruppen im Ziel).
	 *
	 * @param array<int, object{getGroupId(): ?int}> $sourcePositions
	 * @param callable(int): (object{getTitle(): string, getPosition(): int}|null) $findSourceGroup
	 * @param callable(string, int): int $createTargetGroup
	 * @return array<int, int> alte Gruppen-ID => neue Gruppen-ID
	 */
	private function copyReferencedGroups(array $sourcePositions, callable $findSourceGroup, callable $createTargetGroup): array {
		$groupIdMap = [];
		foreach ($sourcePositions as $sp) {
			$sourceGroupId = $sp->getGroupId();
			if ($sourceGroupId === null || isset($groupIdMap[$sourceGroupId])) {
				continue;
			}
			$sourceGroup = $findSourceGroup($sourceGroupId);
			if ($sourceGroup === null) {
				continue;
			}
			$groupIdMap[$sourceGroupId] = $createTargetGroup($sourceGroup->getTitle(), $sourceGroup->getPosition());
		}
		return $groupIdMap;
	}

	/**
	 * Legt eine Rechnung aus ausgewählten Lieferscheinpositionen an
	 * (ADR-0016). Lieferscheinpositionen führen bewusst keine Preise
	 * (ADR-0015) — ist die Lieferscheinposition mit einer Auftragsposition
	 * verknüpft, wird deren Preis/MwSt.-Satz übernommen; ohne Verknüpfung
	 * müssen `unitPriceNet`/`vatRatePercent` mitgeschickt werden. Gruppen
	 * der ausgewählten Positionen werden mitkopiert (Nutzerwunsch
	 * 2026-08-21).
	 *
	 * @param array<int, array{deliveryNotePositionId: int, unitPriceNet?: float, vatRatePercent?: float}> $positions
	 * @throws \OutOfBoundsException wenn Lieferschein/-position nicht existiert
	 * @throws \InvalidArgumentException wenn positions leer ist oder bei einer unverknüpften Position Preis/MwSt. fehlt
	 */
	public function createFromDeliveryNote(int $deliveryNoteId, string $title, string $type, ?string $dueDate, ?string $notes, array $positions): Invoice {
		$deliveryNote = $this->deliveryNoteMapper->findById($deliveryNoteId);
		if ($deliveryNote === null) {
			throw new \OutOfBoundsException("Delivery note $deliveryNoteId not found");
		}
		if ($positions === []) {
			throw new \InvalidArgumentException('positions must not be empty');
		}

		$dnPositions = [];
		foreach ($positions as $selection) {
			$dnPositionId = (int)($selection['deliveryNotePositionId'] ?? 0);
			$dnPosition = $this->deliveryNotePositionMapper->findOne($deliveryNoteId, $dnPositionId);
			if ($dnPosition === null) {
				throw new \OutOfBoundsException("Delivery note position $dnPositionId not found in delivery note $deliveryNoteId");
			}
			$dnPositions[] = $dnPosition;
		}

		$invoice = $this->createDraft($title, $type, $deliveryNote->getProjectId(), $deliveryNote->getOrderId(), null, $dueDate, $notes);
		$invoice->setDeliveryNoteId($deliveryNoteId);
		$invoice = $this->mapper->update($invoice);

		$groupIdMap = $this->copyReferencedGroups(
			$dnPositions,
			fn (int $groupId) => $this->deliveryNoteGroupMapper->findOne($deliveryNoteId, $groupId),
			function (string $groupTitle, int $groupPosition) use ($invoice) {
				$group = new InvoiceGroup();
				$group->setInvoiceId($invoice->getId());
				$group->setTitle($groupTitle);
				$group->setPosition($groupPosition);
				return $this->groupMapper->insert($group)->getId();
			},
		);

		foreach ($positions as $index => $selection) {
			$dnPosition = $dnPositions[$index];

			$orderPosition = $dnPosition->getOrderPositionId() !== null
				? $this->orderPositionMapper->findById($dnPosition->getOrderPositionId())
				: null;

			$unitPriceNet = $selection['unitPriceNet'] ?? $orderPosition?->getUnitPriceNet();
			$vatRatePercent = $selection['vatRatePercent'] ?? $orderPosition?->getVatRatePercent();
			if ($unitPriceNet === null || $vatRatePercent === null) {
				throw new \InvalidArgumentException("Delivery note position {$dnPosition->getId()} has no linked order position — unitPriceNet/vatRatePercent must be provided");
			}

			$position = new InvoicePosition();
			$position->setInvoiceId($invoice->getId());
			$position->setGroupId($dnPosition->getGroupId() !== null ? ($groupIdMap[$dnPosition->getGroupId()] ?? null) : null);
			$position->setPositionType($dnPosition->getPositionType());
			$position->setReferenceId($dnPosition->getReferenceId());
			$position->setDescription($dnPosition->getDescription());
			$position->setQuantity($dnPosition->getQuantity());
			$position->setUnit($dnPosition->getUnit());
			$position->setUnitPriceNet((float)$unitPriceNet);
			$position->setVatRatePercent((float)$vatRatePercent);
			$position->setPositionOrder(count($this->positionMapper->findByInvoice($invoice->getId())));
			$position->setOrderPositionId($dnPosition->getOrderPositionId());
			$this->positionMapper->insert($position);
		}

		return $invoice;
	}

	/**
	 * @throws \OutOfBoundsException wenn die Rechnung oder die Gruppe nicht existiert
	 * @throws \DomainException wenn die Rechnung nicht mehr im Entwurf ist
	 */
	public function addPosition(
		int $invoiceId,
		?int $groupId,
		string $positionType,
		?int $referenceId,
		string $description,
		float $quantity,
		string $unit,
		float $unitPriceNet,
		float $vatRatePercent,
		float $discountPercent = 0.0,
	): InvoicePosition {
		$invoice = $this->getInvoice($invoiceId);
		$this->requireDraft($invoice);
		if ($groupId !== null && $this->groupMapper->findOne($invoiceId, $groupId) === null) {
			throw new \OutOfBoundsException("Group $groupId not found in invoice $invoiceId");
		}

		$position = new InvoicePosition();
		$position->setInvoiceId($invoiceId);
		$position->setGroupId($groupId);
		$position->setPositionType($positionType);
		$position->setReferenceId($referenceId);
		$position->setDescription($description);
		$position->setQuantity($quantity);
		$position->setUnit($unit !== '' ? $unit : 'Stk');
		$position->setUnitPriceNet($unitPriceNet);
		$position->setVatRatePercent($vatRatePercent);
		$position->setDiscountPercent($discountPercent);
		$position->setPositionOrder(count($this->positionMapper->findByInvoice($invoiceId)));
		return $this->positionMapper->insert($position);
	}

	/**
	 * Bereits angelegte Position korrigieren (ADR-0022) — wie addPosition()
	 * nur solange die Rechnung im Entwurf ist.
	 *
	 * @throws \OutOfBoundsException
	 * @throws \DomainException wenn die Rechnung nicht mehr im Entwurf ist
	 */
	public function updatePosition(
		int $invoiceId,
		int $id,
		string $description,
		float $quantity,
		string $unit,
		float $unitPriceNet,
		float $vatRatePercent,
		float $discountPercent = 0.0,
	): InvoicePosition {
		$invoice = $this->getInvoice($invoiceId);
		$this->requireDraft($invoice);

		$position = $this->positionMapper->findOne($invoiceId, $id);
		if ($position === null) {
			throw new \OutOfBoundsException("Position $id not found in invoice $invoiceId");
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

	/**
	 * Rabatt auf den gesamten Beleg setzen (ADR-0022) — eigener, schmaler
	 * Endpunkt statt eines generischen update(), weil Invoice (anders als
	 * Quote/Order) sonst kein bearbeitbares Metadaten-Set hat.
	 *
	 * @throws \OutOfBoundsException
	 * @throws \DomainException wenn die Rechnung nicht mehr im Entwurf ist
	 */
	public function updateDiscount(int $id, float $discountPercent): Invoice {
		$invoice = $this->getInvoice($id);
		$this->requireDraft($invoice);
		$invoice->setDiscountPercent($discountPercent);
		$invoice->setUpdatedAt(time());
		return $this->mapper->update($invoice);
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
			$fileId = $this->pdfService->writePdf($folder, (string)$invoice->getInvoiceNumber(), $html);

			$invoice->setDocumentFileId($fileId);
			$this->mapper->update($invoice);
		} catch (\Throwable) {
			// Dokumentablage ist optional (ADR-0013) — Ausstellen der
			// Rechnung selbst ist zu diesem Zeitpunkt bereits abgeschlossen.
		}
	}

	private function renderHtml(Invoice $invoice, array $positions): string {
		$groups = $this->groupMapper->findByInvoice($invoice->getId());
		$groupsForCalc = array_map(static fn (InvoiceGroup $g) => ['id' => $g->getId(), 'title' => $g->getTitle()], $groups);
		$calc = $this->calculate($positions, $groups, $invoice->getDiscountPercent());

		$invoiceNumber = (string) $invoice->getInvoiceNumber();
		$html = $this->htmlBuilder->header($this->typeLabel($invoice->getType()), $invoiceNumber, $invoice->getTitle(), $invoice->getCreatedAt(), null, $invoice->getCustomerContactUid());
		$html .= $this->htmlBuilder->positionsTable($groupsForCalc, array_map(static fn (InvoicePosition $p) => $p->jsonSerialize(), $positions), true);
		$html .= $this->htmlBuilder->summary($calc);
		$html .= $this->htmlBuilder->footer();

		return $this->htmlBuilder->wrap($invoiceNumber, $html);
	}

	private function typeLabel(string $type): string {
		return match ($type) {
			'partial' => 'Teilrechnung',
			'final' => 'Schlussrechnung',
			default => 'Rechnung',
		};
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
		$grossTotal = $this->calculate($positions, [], $invoice->getDiscountPercent())['grossTotal'];

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
