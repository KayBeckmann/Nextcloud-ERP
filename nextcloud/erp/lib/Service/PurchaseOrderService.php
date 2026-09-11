<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\PurchaseOrder;
use OCA\ERP\Db\PurchaseOrderMapper;
use OCA\ERP\Db\PurchaseOrderPosition;
use OCA\ERP\Db\PurchaseOrderPositionMapper;
use OCA\ERP\Db\PurchaseOrderReceipt;
use OCA\ERP\Db\PurchaseOrderReceiptMapper;
use OCA\ERP\Db\PurchaseOrderStatusChange;
use OCA\ERP\Db\PurchaseOrderStatusChangeMapper;

/**
 * Lieferantenbestellungen: ein Entwurf verändert niemals Lagerbestände.
 * Wareneingang wird bewusst erst über die nachfolgende Receive-Funktion
 * gebucht und erhält einen eigenen Audit-Trail (Roadmap P1).
 */
class PurchaseOrderService {
	public function __construct(
		private PurchaseOrderMapper $orderMapper,
		private PurchaseOrderPositionMapper $positionMapper,
		private PurchaseOrderStatusChangeMapper $statusMapper,
		private PurchaseOrderReceiptMapper $receiptMapper,
		private StockService $stockService,
	) {
	}

	/** @return PurchaseOrder[] */
	public function listAll(): array {
		return $this->orderMapper->findAll();
	}

	/** @return array{order: PurchaseOrder, positions: list<PurchaseOrderPosition>, statusChanges: list<PurchaseOrderStatusChange>} */
	public function get(int $purchaseOrderId): array {
		$order = $this->orderMapper->findById($purchaseOrderId);
		if ($order === null) {
			throw new \OutOfBoundsException('Purchase order not found');
		}
		return ['order' => $order, 'positions' => $this->positionMapper->findByPurchaseOrder($purchaseOrderId), 'statusChanges' => $this->statusMapper->findByPurchaseOrder($purchaseOrderId)];
	}

	/**
	 * @param list<array{articleId?: ?int,description:string,quantityOrdered:float,unit:string,supplierArticleNo?: ?string,unitPurchasePrice?: float,currency?: string,projectId?: ?int,warehouseId?: ?int}> $positions
	 */
	public function createDraft(string $supplierContactUid, array $positions, string $userId, ?string $supplierReference = null, ?string $notes = null): PurchaseOrder {
		if (trim($supplierContactUid) === '') {
			throw new \InvalidArgumentException('supplierContactUid must not be empty');
		}
		if ($positions === []) {
			throw new \InvalidArgumentException('A purchase order requires at least one position');
		}
		$now = time();
		$order = new PurchaseOrder();
		$order->setSupplierContactUid($supplierContactUid);
		$order->setSupplierReference($supplierReference);
		$order->setStatus('draft');
		$order->setNotes($notes);
		$order->setCreatedBy($userId);
		$order->setCreatedAt($now);
		$order->setUpdatedAt($now);
		$order = $this->orderMapper->insert($order);

		foreach ($positions as $index => $input) {
			$this->createPosition($order->getId(), $index + 1, $input);
		}
		$this->recordStatus($order->getId(), null, 'draft', $userId, 'Bestellentwurf angelegt');
		return $order;
	}

	/** @param array{articleId?: ?int,description:string,quantityOrdered:float,unit:string,supplierArticleNo?: ?string,unitPurchasePrice?: float,currency?: string,projectId?: ?int,warehouseId?: ?int} $input */
	private function createPosition(int $purchaseOrderId, int $positionOrder, array $input): PurchaseOrderPosition {
		if (trim($input['description']) === '') {
			throw new \InvalidArgumentException('description must not be empty');
		}
		if ($input['quantityOrdered'] <= 0) {
			throw new \InvalidArgumentException('quantityOrdered must be greater than 0');
		}
		if (trim($input['unit']) === '') {
			throw new \InvalidArgumentException('unit must not be empty');
		}
		$position = new PurchaseOrderPosition();
		$position->setPurchaseOrderId($purchaseOrderId);
		$position->setArticleId($input['articleId'] ?? null);
		$position->setDescription($input['description']);
		$position->setQuantityOrdered($input['quantityOrdered']);
		$position->setQuantityReceived(0.0);
		$position->setUnit($input['unit']);
		$position->setSupplierArticleNo($input['supplierArticleNo'] ?? null);
		$position->setUnitPurchasePrice($input['unitPurchasePrice'] ?? 0.0);
		$position->setCurrency($input['currency'] ?? 'EUR');
		$position->setProjectId($input['projectId'] ?? null);
		$position->setWarehouseId($input['warehouseId'] ?? null);
		$position->setPositionOrder($positionOrder);
		return $this->positionMapper->insert($position);
	}

	public function transitionStatus(int $purchaseOrderId, string $toStatus, string $userId, ?string $notes = null): PurchaseOrder {
		$order = $this->orderMapper->findById($purchaseOrderId);
		if ($order === null) {
			throw new \OutOfBoundsException('Purchase order not found');
		}
		$allowed = [
			'draft' => ['approved', 'cancelled'],
			'approved' => ['sent', 'cancelled'],
			'sent' => ['cancelled'],
			'partially_received' => ['cancelled'],
			'received' => [],
			'cancelled' => [],
		];
		$fromStatus = $order->getStatus();
		if (!in_array($toStatus, $allowed[$fromStatus] ?? [], true)) {
			throw new \DomainException("Transition from $fromStatus to $toStatus is not allowed");
		}
		$order->setStatus($toStatus);
		$order->setUpdatedAt(time());
		$order = $this->orderMapper->update($order);
		$this->recordStatus($order->getId(), $fromStatus, $toStatus, $userId, $notes);
		return $order;
	}

	/**
	 * Bucht einen bewussten Teil-/Vollwareneingang. Ein Entwurf oder Versand
	 * verändert den Bestand nicht; die Buchung erhält eine eigene Referenz.
	 */
	public function receive(int $positionId, float $quantity, int $warehouseId, string $userId, ?string $notes = null): PurchaseOrder {
		if ($quantity <= 0) {
			throw new \InvalidArgumentException('quantity must be greater than 0');
		}
		$position = $this->positionMapper->findById($positionId);
		if ($position === null) {
			throw new \OutOfBoundsException('Purchase order position not found');
		}
		if ($position->getArticleId() === null) {
			throw new \DomainException('Free-text positions cannot be received into stock');
		}
		$order = $this->orderMapper->findById($position->getPurchaseOrderId());
		if ($order === null) {
			throw new \OutOfBoundsException('Purchase order not found');
		}
		if (!in_array($order->getStatus(), ['sent', 'partially_received'], true)) {
			throw new \DomainException('Goods can only be received for a sent purchase order');
		}
		if ($position->getQuantityReceived() + $quantity > $position->getQuantityOrdered()) {
			throw new \DomainException('Received quantity exceeds ordered quantity');
		}

		$receipt = new PurchaseOrderReceipt();
		$receipt->setPurchaseOrderPositionId($positionId);
		$receipt->setWarehouseId($warehouseId);
		$receipt->setQuantity($quantity);
		$receipt->setReceivedBy($userId);
		$receipt->setReceivedAt(time());
		$receipt->setNotes($notes);
		$receipt = $this->receiptMapper->insert($receipt);
		$this->stockService->recordMovement($position->getArticleId(), $warehouseId, $quantity, 'receipt', 'purchase_order_receipt', $receipt->getId(), $userId, $notes);
		$position->setQuantityReceived(round($position->getQuantityReceived() + $quantity, 2));
		$this->positionMapper->update($position);

		$allReceived = true;
		foreach ($this->positionMapper->findByPurchaseOrder($order->getId()) as $candidate) {
			if ($candidate->getQuantityReceived() < $candidate->getQuantityOrdered()) {
				$allReceived = false;
				break;
			}
		}
		$fromStatus = $order->getStatus();
		$order->setStatus($allReceived ? 'received' : 'partially_received');
		$order->setUpdatedAt(time());
		$order = $this->orderMapper->update($order);
		$this->recordStatus($order->getId(), $fromStatus, $order->getStatus(), $userId, $notes);
		return $order;
	}

	private function recordStatus(int $purchaseOrderId, ?string $fromStatus, string $toStatus, string $userId, ?string $notes): void {
		$change = new PurchaseOrderStatusChange();
		$change->setPurchaseOrderId($purchaseOrderId);
		$change->setFromStatus($fromStatus);
		$change->setToStatus($toStatus);
		$change->setChangedBy($userId);
		$change->setChangedAt(time());
		$change->setNotes($notes);
		$this->statusMapper->insert($change);
	}
}
