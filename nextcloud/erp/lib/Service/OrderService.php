<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\DeliveryNotePositionMapper;
use OCA\ERP\Db\InvoicePositionMapper;
use OCA\ERP\Db\Order;
use OCA\ERP\Db\OrderGroup;
use OCA\ERP\Db\OrderGroupMapper;
use OCA\ERP\Db\OrderMapper;
use OCA\ERP\Db\OrderPosition;
use OCA\ERP\Db\OrderPositionMapper;
use OCA\ERP\Db\QuoteGroupMapper;
use OCA\ERP\Db\QuoteMapper;
use OCA\ERP\Db\QuotePositionMapper;
use OCA\ERP\Projects\OrderStatus;
use OCA\ERP\Quotes\QuoteCalculationService;
use OCP\IUser;

/**
 * Aufträge pro Projekt (ADR-0010). Seit ADR-0016 mit eigenen Positionen und
 * Umwandlung aus einem Angebot — Grundlage der Belegkette Angebot -> Auftrag
 * -> Lieferschein/Rechnung. Anders als Rechnungen/Lieferscheine kennen
 * Aufträge kein GoBD-relevantes "Ausstellen" — Positionen bleiben jederzeit
 * änderbar (wie bei Angeboten, ADR-0011).
 */
class OrderService {
	private const VALID_POSITION_TYPES = ['article', 'product', 'labor', 'custom'];

	public function __construct(
		private OrderMapper $mapper,
		private OrderPositionMapper $positionMapper,
		private OrderGroupMapper $groupMapper,
		private QuoteMapper $quoteMapper,
		private QuotePositionMapper $quotePositionMapper,
		private QuoteGroupMapper $quoteGroupMapper,
		private InvoicePositionMapper $invoicePositionMapper,
		private DeliveryNotePositionMapper $deliveryNotePositionMapper,
		private ErpFolderService $folderService,
		private ProjectService $projectService,
		private DocumentPdfService $pdfService,
	) {
	}

	/** @return Order[] */
	public function listOrders(int $projectId): array {
		return $this->mapper->findByProject($projectId);
	}

	/** @throws \OutOfBoundsException */
	public function getOrder(int $id): Order {
		$order = $this->mapper->findById($id);
		if ($order === null) {
			throw new \OutOfBoundsException("Order $id not found");
		}
		return $order;
	}

	/**
	 * Auftrag inkl. Positionen (mit bereits berechneter/gelieferter Menge —
	 * informativ, siehe ADR-0016) und berechneter Netto-/MwSt.-Summen.
	 */
	public function getFullOrder(int $id): array {
		$order = $this->getOrder($id);
		$positions = $this->positionMapper->findByOrder($id);
		$groups = $this->groupMapper->findByOrder($id);

		$annotated = array_map(function (OrderPosition $p) {
			return [
				...$p->jsonSerialize(),
				'invoicedQuantity' => $this->invoicePositionMapper->sumQuantityForOrderPosition($p->getId()),
				'deliveredQuantity' => $this->deliveryNotePositionMapper->sumQuantityForOrderPosition($p->getId()),
			];
		}, $positions);

		$calculation = QuoteCalculationService::calculate(
			array_map(static fn (OrderGroup $g) => ['id' => $g->getId(), 'title' => $g->getTitle()], $groups),
			array_map(static fn (OrderPosition $p) => [
				'id' => $p->getId(),
				'groupId' => $p->getGroupId(),
				'quantity' => $p->getQuantity(),
				'unitPriceNet' => $p->getUnitPriceNet(),
				'vatRatePercent' => $p->getVatRatePercent(),
			], $positions),
		);

		return [
			...$order->jsonSerialize(),
			'groups' => $groups,
			'positions' => $annotated,
			'calculation' => $calculation,
		];
	}

	/** @throws \OutOfBoundsException wenn der Auftrag nicht existiert */
	public function addGroup(int $orderId, string $title): OrderGroup {
		$this->getOrder($orderId);
		$group = new OrderGroup();
		$group->setOrderId($orderId);
		$group->setTitle($title);
		$group->setPosition(count($this->groupMapper->findByOrder($orderId)));
		return $this->groupMapper->insert($group);
	}

	public function createOrder(int $projectId, string $title, ?string $description, ?string $customerContactUid = null, ?string $assignedUserId = null): Order {
		$now = time();
		$order = new Order();
		$order->setProjectId($projectId);
		$order->setTitle($title);
		$order->setStatus(OrderStatus::Draft->value);
		$order->setDescription($description);
		$order->setCustomerContactUid($customerContactUid);
		$order->setAssignedUserId($assignedUserId);
		$order->setCreatedAt($now);
		$order->setUpdatedAt($now);
		return $this->mapper->insert($order);
	}

	/** @throws \OutOfBoundsException */
	public function updateOrder(int $projectId, int $id, string $title, OrderStatus $status, ?string $description, ?string $customerContactUid = null, ?string $assignedUserId = null, ?IUser $issuer = null): Order {
		$order = $this->mapper->findOne($projectId, $id);
		if ($order === null) {
			throw new \OutOfBoundsException("Order $id not found in project $projectId");
		}
		// PDF-Erzeugung (ADR-0021) nur beim erstmaligen Wechsel nach
		// 'confirmed' — Aufträge haben (anders als Rechnungen/Angebote)
		// keinen eigenen Zeitstempel für diesen Übergang, deshalb dient
		// "noch kein Dokument abgelegt" als Erstmaligkeits-Signal.
		$becomesConfirmed = $order->getStatus() !== OrderStatus::Confirmed->value
			&& $status === OrderStatus::Confirmed
			&& $order->getDocumentFileId() === null;
		$order->setTitle($title);
		$order->setStatus($status->value);
		$order->setDescription($description);
		$order->setCustomerContactUid($customerContactUid);
		$order->setAssignedUserId($assignedUserId);
		$order->setUpdatedAt(time());
		$order = $this->mapper->update($order);

		if ($becomesConfirmed && $issuer !== null) {
			$this->tryWriteDocument($order, $issuer);
		}

		return $order;
	}

	private function tryWriteDocument(Order $order, IUser $issuer): void {
		try {
			$project = $this->projectService->getProject($order->getProjectId());
			$folder = $this->folderService->ensureOrderFolder($issuer, $project->getProjectNumber());
			$html = $this->renderHtml($order);
			$fileId = $this->pdfService->writePdf($folder, sprintf('AU-%05d', $order->getId()), $html);

			$order->setDocumentFileId($fileId);
			$this->mapper->update($order);
		} catch (\Throwable) {
			// Dokumentablage ist optional (ADR-0021).
		}
	}

	private function renderHtml(Order $order): string {
		$positions = $this->positionMapper->findByOrder($order->getId());
		$calc = QuoteCalculationService::calculate([], array_map(static fn (OrderPosition $p) => [
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

		$orderNumber = sprintf('AU-%05d', $order->getId());
		return sprintf(
			"<!DOCTYPE html>\n<html lang=\"de\"><head><meta charset=\"utf-8\"><title>%s</title></head><body>\n" .
			"<h1>Auftrag %s</h1>\n<p>%s</p>\n" .
			"<table border=\"1\" cellspacing=\"0\" cellpadding=\"4\">\n<thead><tr><th>Beschreibung</th><th>Menge</th><th>EP netto</th><th>MwSt.</th><th>Gesamt netto</th></tr></thead>\n<tbody>\n%s</tbody>\n</table>\n" .
			"<p>Netto-Zwischensumme: %s €<br>Brutto-Gesamt: %s €</p>\n</body></html>\n",
			htmlspecialchars($orderNumber),
			htmlspecialchars($orderNumber),
			htmlspecialchars($order->getTitle()),
			$rows,
			number_format($calc['netSubtotal'], 2, ',', '.'),
			number_format($calc['grossTotal'], 2, ',', '.'),
		);
	}

	/**
	 * Legt einen Auftrag an und übernimmt alle Gruppen und Positionen eines
	 * Angebots 1:1 (Snapshot-Kopie, keine Live-Referenz danach) — ADR-0016,
	 * Gruppen-Erhalt Nutzerwunsch 2026-08-21. Gruppen werden zuerst
	 * angelegt und auf die neue ID gemappt, damit die Positionen die
	 * richtige (neue) group_id bekommen.
	 *
	 * @throws \OutOfBoundsException wenn das Angebot nicht existiert
	 */
	public function createFromQuote(int $quoteId, ?string $title = null): Order {
		$quote = $this->quoteMapper->findById($quoteId);
		if ($quote === null) {
			throw new \OutOfBoundsException("Quote $quoteId not found");
		}

		$order = $this->createOrder($quote->getProjectId(), $title ?? $quote->getTitle(), null, $quote->getCustomerContactUid());
		$order->setQuoteId($quoteId);
		$order = $this->mapper->update($order);

		$groupIdMap = [];
		foreach ($this->quoteGroupMapper->findByQuote($quoteId) as $qg) {
			$group = new OrderGroup();
			$group->setOrderId($order->getId());
			$group->setTitle($qg->getTitle());
			$group->setPosition($qg->getPosition());
			$group = $this->groupMapper->insert($group);
			$groupIdMap[$qg->getId()] = $group->getId();
		}

		foreach ($this->quotePositionMapper->findByQuote($quoteId) as $qp) {
			$position = new OrderPosition();
			$position->setOrderId($order->getId());
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

		return $order;
	}

	/**
	 * @throws \OutOfBoundsException wenn der Auftrag oder die Gruppe nicht existiert
	 * @throws \InvalidArgumentException wenn positionType unbekannt ist
	 */
	public function addPosition(
		int $orderId,
		?int $groupId,
		string $positionType,
		?int $referenceId,
		string $description,
		float $quantity,
		string $unit,
		float $unitPriceNet,
		float $vatRatePercent,
	): OrderPosition {
		$this->getOrder($orderId);
		if ($groupId !== null && $this->groupMapper->findOne($orderId, $groupId) === null) {
			throw new \OutOfBoundsException("Group $groupId not found in order $orderId");
		}
		if (!in_array($positionType, self::VALID_POSITION_TYPES, true)) {
			throw new \InvalidArgumentException('positionType must be one of: ' . implode(', ', self::VALID_POSITION_TYPES));
		}

		$position = new OrderPosition();
		$position->setOrderId($orderId);
		$position->setGroupId($groupId);
		$position->setPositionType($positionType);
		$position->setReferenceId($referenceId);
		$position->setDescription($description);
		$position->setQuantity($quantity);
		$position->setUnit($unit !== '' ? $unit : 'Stk');
		$position->setUnitPriceNet($unitPriceNet);
		$position->setVatRatePercent($vatRatePercent);
		$position->setPositionOrder(count($this->positionMapper->findByOrder($orderId)));
		return $this->positionMapper->insert($position);
	}

	/** @throws \OutOfBoundsException */
	public function removePosition(int $orderId, int $id): void {
		$position = $this->positionMapper->findOne($orderId, $id);
		if ($position === null) {
			throw new \OutOfBoundsException("Position $id not found in order $orderId");
		}
		$this->positionMapper->delete($position);
	}
}
