<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\DeliveryNote;
use OCA\ERP\Db\DeliveryNoteMapper;
use OCA\ERP\Db\DeliveryNotePosition;
use OCA\ERP\Db\DeliveryNotePositionMapper;

/**
 * Lieferscheine (ADR-0015) — dokumentieren nur die gelieferte Menge, keinen
 * Wert (keine Preise/MwSt., anders als Rechnungspositionen). Nummer wird
 * sofort bei Anlage vergeben (analog zu Angeboten, `L-%05d`) — Lieferscheine
 * unterliegen nicht derselben GoBD-Nummernlücken-Anforderung wie Rechnungen
 * (ADR-0013).
 */
class DeliveryNoteService {
	private const VALID_POSITION_TYPES = ['article', 'product', 'custom'];

	public function __construct(
		private DeliveryNoteMapper $mapper,
		private DeliveryNotePositionMapper $positionMapper,
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
