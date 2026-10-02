<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\DeliveryNote;
use OCA\ERP\Db\DeliveryNoteGroup;
use OCA\ERP\Db\DeliveryNoteGroupMapper;
use OCA\ERP\Db\DeliveryNoteMapper;
use OCA\ERP\Db\DeliveryNotePosition;
use OCA\ERP\Db\DeliveryNotePositionMapper;
use OCA\ERP\Db\OrderGroupMapper;
use OCA\ERP\Db\OrderMapper;
use OCA\ERP\Db\OrderPositionMapper;
use OCP\IDBConnection;
use OCP\IUser;

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
		private DeliveryNoteGroupMapper $groupMapper,
		private OrderMapper $orderMapper,
		private OrderPositionMapper $orderPositionMapper,
		private OrderGroupMapper $orderGroupMapper,
		private ErpFolderService $folderService,
		private ProjectService $projectService,
		private DocumentPdfService $pdfService,
		private DocumentHtmlBuilder $htmlBuilder,
		private IDBConnection $db,
		private StockService $stockService,
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
			'groups' => $this->groupMapper->findByDeliveryNote($id),
			'positions' => $this->positionMapper->findByDeliveryNote($id),
		];
	}

	/** @throws \OutOfBoundsException wenn der Lieferschein nicht existiert */
	public function addGroup(int $deliveryNoteId, string $title): DeliveryNoteGroup {
		$this->get($deliveryNoteId);
		$group = new DeliveryNoteGroup();
		$group->setDeliveryNoteId($deliveryNoteId);
		$group->setTitle($title);
		$group->setPosition(count($this->groupMapper->findByDeliveryNote($deliveryNoteId)));
		return $this->groupMapper->insert($group);
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
	 * Restmenge nicht überschreiten. Die Prüfung läuft seit ADR-0029
	 * innerhalb einer Transaktion mit `SELECT ... FOR UPDATE` auf die
	 * betroffenen Auftragspositionen — zwei gleichzeitige Anfragen auf
	 * dieselbe Position werden damit serialisiert, statt beide gegen eine
	 * veraltete Summe zu prüfen (TOCTOU-Lücke, ADR-0016 "Nicht Teil dieser
	 * Phase").
	 *
	 * Löst seit ADR-0039 zusätzlich die automatische Reservierung auf: für
	 * Artikel-Positionen mit Lagerauswahl (`OrderPosition::warehouseId`)
	 * wird die gelieferte Menge aus der Reservierung freigegeben und — nur
	 * wenn `$createdByUserId` übergeben wird — als echter Warenabgang
	 * (`consumption`) gebucht. `$createdByUserId` ist bewusst optional
	 * (Default `null`), damit bestehende Aufrufer ohne Bezug zu
	 * Lager-Reservierungen unverändert funktionieren.
	 *
	 * @param array<int, array{orderPositionId: int, quantity: float}> $positions
	 * @throws \OutOfBoundsException wenn Auftrag oder Auftragsposition nicht existiert
	 * @throws \InvalidArgumentException wenn positions leer ist oder eine Menge <= 0 ist
	 * @throws \DomainException wenn eine Position keine Ware ist oder die Restmenge überschritten wird
	 */
	public function createFromOrder(int $orderId, array $positions, ?string $notes, ?string $createdByUserId = null): DeliveryNote {
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
			$orderPosition = $this->orderPositionMapper->findOne($orderId, $orderPositionId);
			if ($orderPosition === null) {
				throw new \OutOfBoundsException("Order position $orderPositionId not found in order $orderId");
			}
			$orderPositions[] = $orderPosition;
		}

		$deliveryNote = $this->createDraft($order->getProjectId(), $orderId, $notes);

		// Nur die tatsächlich referenzierten Gruppen kopieren (Nutzerwunsch
		// 2026-08-21) — keine leeren Gruppen im Ziel.
		$groupIdMap = [];
		foreach ($orderPositions as $op) {
			$sourceGroupId = $op->getGroupId();
			if ($sourceGroupId === null || isset($groupIdMap[$sourceGroupId])) {
				continue;
			}
			$sourceGroup = $this->orderGroupMapper->findOne($orderId, $sourceGroupId);
			if ($sourceGroup === null) {
				continue;
			}
			$group = new DeliveryNoteGroup();
			$group->setDeliveryNoteId($deliveryNote->getId());
			$group->setTitle($sourceGroup->getTitle());
			$group->setPosition($sourceGroup->getPosition());
			$groupIdMap[$sourceGroupId] = $this->groupMapper->insert($group)->getId();
		}

		// Betroffene Auftragspositionen in fester Reihenfolge (aufsteigende
		// ID) sperren — eine feste Sperr-Reihenfolge über alle Aufrufer
		// schließt Deadlocks aus, falls zwei Anfragen mehrere gemeinsame
		// Positionen in unterschiedlicher Reihenfolge anfragen.
		$uniqueIds = array_unique(array_map(static fn ($op) => $op->getId(), $orderPositions));
		sort($uniqueIds);

		$this->db->beginTransaction();
		try {
			$lockedById = [];
			foreach ($uniqueIds as $id) {
				$lockedById[$id] = $this->orderPositionMapper->findOneForUpdate($orderId, $id);
			}

			foreach ($positions as $index => $selection) {
				$quantity = (float)($selection['quantity'] ?? 0);
				$orderPosition = $lockedById[$orderPositions[$index]->getId()];

				if (!in_array($orderPosition->getPositionType(), self::ORDER_CONVERTIBLE_TYPES, true)) {
					throw new \DomainException("Order position {$orderPosition->getId()} has type '{$orderPosition->getPositionType()}' — only article/product can become a delivery note position");
				}
				if ($quantity <= 0) {
					throw new \InvalidArgumentException('quantity must be greater than 0');
				}
				$alreadyDelivered = $this->positionMapper->sumQuantityForOrderPosition($orderPosition->getId());
				if ($alreadyDelivered + $quantity > $orderPosition->getQuantity() + 0.0001) {
					throw new \DomainException("Order position {$orderPosition->getId()}: requested quantity exceeds remaining deliverable quantity");
				}

				$position = new DeliveryNotePosition();
				$position->setDeliveryNoteId($deliveryNote->getId());
				$position->setGroupId($orderPosition->getGroupId() !== null ? ($groupIdMap[$orderPosition->getGroupId()] ?? null) : null);
				$position->setPositionType($orderPosition->getPositionType());
				$position->setReferenceId($orderPosition->getReferenceId());
				$position->setDescription($orderPosition->getDescription());
				$position->setQuantity($quantity);
				$position->setUnit($orderPosition->getUnit());
				$position->setPositionOrder(count($this->positionMapper->findByDeliveryNote($deliveryNote->getId())));
				$position->setOrderPositionId($orderPosition->getId());
				$this->positionMapper->insert($position);

				// ADR-0039: gelieferte Menge aus der Reservierung freigeben
				// und — sofern ein User zum Attribuieren übergeben wurde —
				// als echten Warenabgang buchen.
				if ($orderPosition->getPositionType() === 'article'
					&& $orderPosition->getWarehouseId() !== null
					&& $orderPosition->getReferenceId() !== null
				) {
					$this->stockService->release($orderPosition->getReferenceId(), $orderPosition->getWarehouseId(), $quantity);
					if ($createdByUserId !== null) {
						$this->stockService->recordMovement(
							$orderPosition->getReferenceId(),
							$orderPosition->getWarehouseId(),
							-$quantity,
							'consumption',
							'delivery_note',
							$deliveryNote->getId(),
							$createdByUserId,
							null,
						);
					}
				}
			}
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}

		return $deliveryNote;
	}

	/**
	 * @throws \OutOfBoundsException wenn der Lieferschein oder die Gruppe nicht existiert
	 * @throws \DomainException wenn nicht mehr im Entwurf
	 * @throws \InvalidArgumentException wenn positionType unbekannt ist
	 */
	public function addPosition(int $deliveryNoteId, ?int $groupId, string $positionType, ?int $referenceId, string $description, float $quantity, string $unit): DeliveryNotePosition {
		if (!in_array($positionType, self::VALID_POSITION_TYPES, true)) {
			throw new \InvalidArgumentException('positionType must be one of: ' . implode(', ', self::VALID_POSITION_TYPES));
		}
		$deliveryNote = $this->get($deliveryNoteId);
		if ($deliveryNote->getStatus() !== 'draft') {
			throw new \DomainException("Delivery note $deliveryNoteId is not in status 'draft'");
		}
		if ($groupId !== null && $this->groupMapper->findOne($deliveryNoteId, $groupId) === null) {
			throw new \OutOfBoundsException("Group $groupId not found in delivery note $deliveryNoteId");
		}

		$position = new DeliveryNotePosition();
		$position->setDeliveryNoteId($deliveryNoteId);
		$position->setGroupId($groupId);
		$position->setPositionType($positionType);
		$position->setReferenceId($referenceId);
		$position->setDescription($description);
		$position->setQuantity($quantity);
		$position->setUnit($unit !== '' ? $unit : 'Stk');
		$position->setPositionOrder(count($this->positionMapper->findByDeliveryNote($deliveryNoteId)));
		return $this->positionMapper->insert($position);
	}

	/**
	 * Bereits angelegte Position korrigieren (ADR-0022) — ohne Preis-/
	 * Rabattfelder, weil Lieferscheine keine Preise führen.
	 *
	 * @throws \OutOfBoundsException
	 * @throws \DomainException wenn nicht mehr im Entwurf
	 */
	public function updatePosition(int $deliveryNoteId, int $id, string $description, float $quantity, string $unit): DeliveryNotePosition {
		$deliveryNote = $this->get($deliveryNoteId);
		if ($deliveryNote->getStatus() !== 'draft') {
			throw new \DomainException("Delivery note $deliveryNoteId is not in status 'draft'");
		}
		$position = $this->positionMapper->findOne($deliveryNoteId, $id);
		if ($position === null) {
			throw new \OutOfBoundsException("Position $id not found in delivery note $deliveryNoteId");
		}
		$position->setDescription($description);
		$position->setQuantity($quantity);
		$position->setUnit($unit !== '' ? $unit : 'Stk');
		return $this->positionMapper->update($position);
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
	public function issue(int $id, ?IUser $issuer = null): DeliveryNote {
		$deliveryNote = $this->get($id);
		if ($deliveryNote->getStatus() !== 'draft') {
			throw new \DomainException("Delivery note $id is not in status 'draft'");
		}
		$positions = $this->positionMapper->findByDeliveryNote($id);
		if ($positions === []) {
			throw new \DomainException("Delivery note $id has no positions");
		}

		$deliveryNote->setStatus('issued');
		$deliveryNote->setDeliveredAt(time());
		$deliveryNote->setUpdatedAt(time());
		$customerContactUid = $deliveryNote->getOrderId() === null ? null : $this->orderMapper->findById($deliveryNote->getOrderId())?->getCustomerContactUid();
		$deliveryNote->setLayoutSnapshot($this->htmlBuilder->snapshot('delivery_note', (string) $deliveryNote->getDeliveryNoteNumber(), (string) ($deliveryNote->getNotes() ?? ''), $deliveryNote->getCreatedAt(), null, $customerContactUid));
		$deliveryNote = $this->mapper->update($deliveryNote);

		if ($issuer !== null) {
			$this->tryWriteDocument($deliveryNote, $positions, $issuer);
		}

		return $deliveryNote;
	}

	/** @param DeliveryNotePosition[] $positions */
	private function tryWriteDocument(DeliveryNote $deliveryNote, array $positions, IUser $issuer): void {
		try {
			$project = $this->projectService->getProject($deliveryNote->getProjectId());
			$folder = $this->folderService->ensureDeliveryNoteFolder($issuer, $project->getProjectNumber());
			$html = $this->renderHtml($deliveryNote, $positions);
			$fileId = $this->pdfService->writePdf($folder, (string)$deliveryNote->getDeliveryNoteNumber(), $html);

			$deliveryNote->setDocumentFileId($fileId);
			$this->mapper->update($deliveryNote);
		} catch (\Throwable) {
			// Dokumentablage ist optional (ADR-0013/ADR-0021).
		}
	}

	/** @param DeliveryNotePosition[] $positions */
	private function renderHtml(DeliveryNote $deliveryNote, array $positions): string {
		$groups = $this->groupMapper->findByDeliveryNote($deliveryNote->getId());
		$groupsForCalc = array_map(static fn (DeliveryNoteGroup $g) => ['id' => $g->getId(), 'title' => $g->getTitle()], $groups);

		// Lieferscheine haben keinen eigenen Kunden — der kommt (falls
		// vorhanden) vom referenzierten Auftrag.
		$customerContactUid = null;
		if ($deliveryNote->getOrderId() !== null) {
			$order = $this->orderMapper->findById($deliveryNote->getOrderId());
			$customerContactUid = $order?->getCustomerContactUid();
		}

		$deliveryNoteNumber = (string) $deliveryNote->getDeliveryNoteNumber();
		$html = $this->htmlBuilder->header('Lieferschein', $deliveryNoteNumber, (string) ($deliveryNote->getNotes() ?? ''), $deliveryNote->getCreatedAt(), null, $customerContactUid, 'delivery_note', null, $deliveryNote->getLayoutSnapshot());
		$html .= $this->htmlBuilder->positionsTable($groupsForCalc, array_map(static fn (DeliveryNotePosition $p) => $p->jsonSerialize(), $positions), false, 'delivery_note', $deliveryNote->getLayoutSnapshot());
		$html .= $this->htmlBuilder->footer('delivery_note', $deliveryNoteNumber, (string) ($deliveryNote->getNotes() ?? ''), $deliveryNote->getCreatedAt(), null, $customerContactUid, null, $deliveryNote->getLayoutSnapshot());

		return $this->htmlBuilder->wrap($deliveryNoteNumber, $html);
	}
}
