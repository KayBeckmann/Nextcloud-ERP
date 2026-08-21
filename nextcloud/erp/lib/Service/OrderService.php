<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\DeliveryNotePositionMapper;
use OCA\ERP\Db\InvoicePositionMapper;
use OCA\ERP\Db\Order;
use OCA\ERP\Db\OrderMapper;
use OCA\ERP\Db\OrderPosition;
use OCA\ERP\Db\OrderPositionMapper;
use OCA\ERP\Db\QuoteMapper;
use OCA\ERP\Db\QuotePositionMapper;
use OCA\ERP\Projects\OrderStatus;
use OCA\ERP\Quotes\QuoteCalculationService;

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
		private QuoteMapper $quoteMapper,
		private QuotePositionMapper $quotePositionMapper,
		private InvoicePositionMapper $invoicePositionMapper,
		private DeliveryNotePositionMapper $deliveryNotePositionMapper,
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

		$annotated = array_map(function (OrderPosition $p) {
			return [
				...$p->jsonSerialize(),
				'invoicedQuantity' => $this->invoicePositionMapper->sumQuantityForOrderPosition($p->getId()),
				'deliveredQuantity' => $this->deliveryNotePositionMapper->sumQuantityForOrderPosition($p->getId()),
			];
		}, $positions);

		$calculation = QuoteCalculationService::calculate([], array_map(static fn (OrderPosition $p) => [
			'id' => $p->getId(),
			'groupId' => null,
			'quantity' => $p->getQuantity(),
			'unitPriceNet' => $p->getUnitPriceNet(),
			'vatRatePercent' => $p->getVatRatePercent(),
		], $positions));

		return [
			...$order->jsonSerialize(),
			'positions' => $annotated,
			'calculation' => $calculation,
		];
	}

	public function createOrder(int $projectId, string $title, ?string $description, ?string $customerContactUid = null): Order {
		$now = time();
		$order = new Order();
		$order->setProjectId($projectId);
		$order->setTitle($title);
		$order->setStatus(OrderStatus::Draft->value);
		$order->setDescription($description);
		$order->setCustomerContactUid($customerContactUid);
		$order->setCreatedAt($now);
		$order->setUpdatedAt($now);
		return $this->mapper->insert($order);
	}

	/** @throws \OutOfBoundsException */
	public function updateOrder(int $projectId, int $id, string $title, OrderStatus $status, ?string $description, ?string $customerContactUid = null): Order {
		$order = $this->mapper->findOne($projectId, $id);
		if ($order === null) {
			throw new \OutOfBoundsException("Order $id not found in project $projectId");
		}
		$order->setTitle($title);
		$order->setStatus($status->value);
		$order->setDescription($description);
		$order->setCustomerContactUid($customerContactUid);
		$order->setUpdatedAt(time());
		return $this->mapper->update($order);
	}

	/**
	 * Legt einen Auftrag an und übernimmt alle Positionen eines Angebots 1:1
	 * (Snapshot-Kopie, keine Live-Referenz danach) — ADR-0016.
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

		foreach ($this->quotePositionMapper->findByQuote($quoteId) as $qp) {
			$position = new OrderPosition();
			$position->setOrderId($order->getId());
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
	 * @throws \OutOfBoundsException wenn der Auftrag nicht existiert
	 * @throws \InvalidArgumentException wenn positionType unbekannt ist
	 */
	public function addPosition(
		int $orderId,
		string $positionType,
		?int $referenceId,
		string $description,
		float $quantity,
		string $unit,
		float $unitPriceNet,
		float $vatRatePercent,
	): OrderPosition {
		$this->getOrder($orderId);
		if (!in_array($positionType, self::VALID_POSITION_TYPES, true)) {
			throw new \InvalidArgumentException('positionType must be one of: ' . implode(', ', self::VALID_POSITION_TYPES));
		}

		$position = new OrderPosition();
		$position->setOrderId($orderId);
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
