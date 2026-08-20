<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\OvertimeAction;
use OCA\ERP\Db\OvertimeActionMapper;

/**
 * Freigabe-Workflow für Überstunden-Abbau/-Auszahlung (ADR-0012). Bewusst
 * ohne automatische Herleitung der Stunden aus dem Zeitkonto — die Anzahl
 * wird beim Beantragen manuell angegeben (siehe "Nicht Teil dieser Phase").
 */
class OvertimeActionService {
	private const ACTION_TYPES = ['compensate', 'payout'];

	public function __construct(
		private OvertimeActionMapper $mapper,
	) {
	}

	/** @return OvertimeAction[] */
	public function listForUser(string $userId): array {
		return $this->mapper->findByUser($userId);
	}

	/** @return OvertimeAction[] */
	public function listByStatus(string $status): array {
		return $this->mapper->findByStatus($status);
	}

	/** @throws \OutOfBoundsException */
	public function get(int $id): OvertimeAction {
		$action = $this->mapper->findById($id);
		if ($action === null) {
			throw new \OutOfBoundsException("Overtime action $id not found");
		}
		return $action;
	}

	/** @throws \InvalidArgumentException wenn actionType ungültig ist */
	public function create(string $userId, float $hours, string $actionType, ?string $notes): OvertimeAction {
		if (!in_array($actionType, self::ACTION_TYPES, true)) {
			throw new \InvalidArgumentException("actionType must be one of: " . implode(', ', self::ACTION_TYPES));
		}

		$now = time();
		$action = new OvertimeAction();
		$action->setUserId($userId);
		$action->setHours($hours);
		$action->setActionType($actionType);
		$action->setStatus('requested');
		$action->setNotes($notes);
		$action->setCreatedAt($now);
		$action->setUpdatedAt($now);
		return $this->mapper->insert($action);
	}

	/** @throws \OutOfBoundsException|\DomainException */
	public function approve(int $id): OvertimeAction {
		$action = $this->get($id);
		if ($action->getStatus() !== 'requested') {
			throw new \DomainException("Overtime action $id is not in status 'requested'");
		}
		$action->setStatus('approved');
		$action->setUpdatedAt(time());
		return $this->mapper->update($action);
	}

	/** Markiert eine genehmigte Aktion als abgeschlossen (abgebaut/ausgezahlt). */
	public function complete(int $id): OvertimeAction {
		$action = $this->get($id);
		if ($action->getStatus() !== 'approved') {
			throw new \DomainException("Overtime action $id is not in status 'approved'");
		}
		$action->setStatus('done');
		$action->setUpdatedAt(time());
		return $this->mapper->update($action);
	}

	/** @throws \OutOfBoundsException|\DomainException */
	public function reject(int $id): OvertimeAction {
		$action = $this->get($id);
		if ($action->getStatus() !== 'requested') {
			throw new \DomainException("Overtime action $id is not in status 'requested'");
		}
		$action->setStatus('rejected');
		$action->setUpdatedAt(time());
		return $this->mapper->update($action);
	}
}
