<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\DeliveryNote;
use OCA\ERP\Db\DeliveryNoteMapper;
use OCA\ERP\Db\DeliveryNotePosition;
use OCA\ERP\Db\DeliveryNotePositionMapper;
use OCA\ERP\Db\OrderMapper;
use OCA\ERP\Db\OrderPositionMapper;

/**
 * Lieferscheine (ADR-0015) — dokumentieren nur die gelieferte Menge, keinen
 * Wert (keine Preise/MwSt., anders als Rechnungspositionen). Nummer wird
 * sofort bei Anlage vergeben (analog zu Angeboten, `L-%05d`) — Lieferscheine
 * unterliegen nicht derselben GoBD-Nummernlücken-Anforderung wie Rechnungen
 * (ADR-0013).
 */
class DeliveryNoteService {
	private const VALID_POSITION_TYPES = ['article', 'product', 'custom'];
	// Auftrag -> Lieferschein übernimmt bewusst nur Artikel/Produkt, keine
	// Arbeitsstunden ("keine Zeiten" — Nutzerwunsch, ADR-0016).
	private const ORDER_CONVERTIBLE_TYPES = ['article', 'product'];

	public function __construct(
		private DeliveryNoteMapper $mapper,
		private DeliveryNotePositionMapper $positionMapper,
		private OrderMapper $orderMapper,
		private OrderPositionMapper $orderPositionMapper,
	) {
	}

	/** @return DeliveryNote[] */
	public function listForProject(int $projectId): array {
		return $this->mapper->findByProject($projectId);
	}

	/** @throws \OutOfBoundsException */
	public function get(int $id): DeliveryNote {
		$deliveryNote = $this->mapper->findById($id);
		if ($deliveryNote === null) {
			throw new \OutOfBoundsException("Delivery note $id not found");
		}
		return $deliveryNote;
	}

	public function getFull(int $id): array {
		$deliveryNote = $this->get($id);
		return [
			...$deliveryNote->jsonSerialize(),
			'positions' => $this->positionMapper->findByDeliveryNote($id),
		];
	}

	/** @throws \InvalidArgumentException wenn projectId nicht gesetzt ist */
	public function createDraft(int $projectId, ?int $orderId, ?string $notes): DeliveryNote {
		if ($projectId <= 0) {
			throw new \InvalidArgumentException('projectId is required');
		}

		$now = time();
		$deliveryNote = new DeliveryNote();
		$deliveryNote->setProjectId($projectId);
		$deliveryNote->setOrderId($orderId);
		$deliveryNote->setStatus('draft');
		$deliveryNote->setNotes($notes);
		$deliveryNote->setCreatedAt($now);
		$deliveryNote->setUpdatedAt($now);
		$deliveryNote = $this->mapper->insert($deliveryNote);

		$deliveryNote->setDeliveryNoteNumber(sprintf('L-%05d', $deliveryNote->getId()));
		return $this->mapper->update($deliveryNote);
	}

	/**
	 * Legt einen Lieferschein aus ausgewählten Auftragspositionen an
	 * (ADR-0016) — nur `article`/`product`, keine Arbeitsstunden. Jede
	 * Auswahl `{orderPositionId, quantity}` darf die noch nicht gelieferte
	 * Restmenge nicht überschreiten (informative Prüfung, kein Locking).
	 *
	 * @param array<int, array{orderPositionId: int, quantity: float}> $positions
	 * @throws \OutOfBoundsException wenn Auftrag oder Auftragsposition nicht existiert
	 * @throws \InvalidArgumentException wenn positions leer ist oder eine Menge <= 0 ist
	 * @throws \DomainException wenn eine Position keine Ware ist oder die Restmenge überschritten wird
	 */
	public function createFromOrder(int $orderId, array $positions, ?string $notes): DeliveryNote {
		$order = $this->orderMapper->findById($orderId);
		if ($order === null) {
			throw new \OutOfBoundsException("Order $orderId not found");
		}
		if ($positions === []) {
			throw new \InvalidArgumentException('positions must not be empty');
		}

		$deliveryNote = $this->createDraft($order->getProjectId(), $orderId, $notes);

		foreach ($positions as $selection) {
			$orderPositionId = (int)($selection['orderPositionId'] ?? 0);
			$quantity = (float)($selection['quantity'] ?? 0);

			$orderPosition = $this->orderPositionMapper->findOne($orderId, $orderPositionId);
			if ($orderPosition === null) {
				throw new \OutOfBoundsException("Order position $orderPositionId not found in order $orderId");
			}
			if (!in_array($orderPosition->getPositionType(), self::ORDER_CONVERTIBLE_TYPES, true)) {
				throw new \DomainException("Order position $orderPositionId has type '{$orderPosition->getPositionType()}' — only article/product can become a delivery note position");
			}
			if ($quantity <= 0) {
				throw new \InvalidArgumentException('quantity must be greater than 0');
			}
			$alreadyDelivered = $this->positionMapper->sumQuantityForOrderPosition($orderPositionId);
			if ($alreadyDelivered + $quantity > $orderPosition->getQuantity() + 0.0001) {
				throw new \DomainException("Order position $orderPositionId: requested quantity exceeds remaining deliverable quantity");
			}

			$position = new DeliveryNotePosition();
			$position->setDeliveryNoteId($deliveryNote->getId());
			$position->setPositionType($orderPosition->getPositionType());
			$position->setReferenceId($orderPosition->getReferenceId());
			$position->setDescription($orderPosition->getDescription());
			$position->setQuantity($quantity);
			$position->setUnit($orderPosition->getUnit());
			$position->setPositionOrder(count($this->positionMapper->findByDeliveryNote($deliveryNote->getId())));
			$position->setOrderPositionId($orderPositionId);
			$this->positionMapper->insert($position);
		}

		return $deliveryNote;
	}

	/**
	 * @throws \OutOfBoundsException
	 * @throws \DomainException wenn nicht mehr im Entwurf
	 * @throws \InvalidArgumentException wenn positionType unbekannt ist
	 */
	public function addPosition(int $deliveryNoteId, string $positionType, ?int $referenceId, string $description, float $quantity, string $unit): DeliveryNotePosition {
		if (!in_array($positionType, self::VALID_POSITION_TYPES, true)) {
			throw new \InvalidArgumentException('positionType must be one of: ' . implode(', ', self::VALID_POSITION_TYPES));
		}
		$deliveryNote = $this->get($deliveryNoteId);
		if ($deliveryNote->getStatus() !== 'draft') {
			throw new \DomainException("Delivery note $deliveryNoteId is not in status 'draft'");
		}

		$position = new DeliveryNotePosition();
		$position->setDeliveryNoteId($deliveryNoteId);
		$position->setPositionType($positionType);
		$position->setReferenceId($referenceId);
		$position->setDescription($description);
		$position->setQuantity($quantity);
		$position->setUnit($unit !== '' ? $unit : 'Stk');
		$position->setPositionOrder(count($this->positionMapper->findByDeliveryNote($deliveryNoteId)));
		return $this->positionMapper->insert($position);
	}

	/**
	 * @throws \OutOfBoundsException
	 * @throws \DomainException wenn nicht mehr im Entwurf
	 */
	public function removePosition(int $deliveryNoteId, int $id): void {
		$deliveryNote = $this->get($deliveryNoteId);
		if ($deliveryNote->getStatus() !== 'draft') {
			throw new \DomainException("Delivery note $deliveryNoteId is not in status 'draft'");
		}
		$position = $this->positionMapper->findOne($deliveryNoteId, $id);
		if ($position === null) {
			throw new \OutOfBoundsException("Position $id not found in delivery note $deliveryNoteId");
		}
		$this->positionMapper->delete($position);
	}

	/**
	 * @throws \OutOfBoundsException
	 * @throws \DomainException wenn bereits ausgestellt oder keine Positionen vorhanden
	 */
	public function issue(int $id): DeliveryNote {
		$deliveryNote = $this->get($id);
		if ($deliveryNote->getStatus() !== 'draft') {
			throw new \DomainException("Delivery note $id is not in status 'draft'");
		}
		if ($this->positionMapper->findByDeliveryNote($id) === []) {
			throw new \DomainException("Delivery note $id has no positions");
		}

		$deliveryNote->setStatus('issued');
		$deliveryNote->setDeliveredAt(time());
		$deliveryNote->setUpdatedAt(time());
		return $this->mapper->update($deliveryNote);
	}
}
