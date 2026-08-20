<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\ProjectMapper;
use OCA\ERP\Db\TimeEntry;
use OCA\ERP\Db\TimeEntryMapper;
use OCA\ERP\Db\WorkTypeMapper;

/**
 * Zeiterfassung: legt Zeiteinträge an und friert dabei den effektiven
 * Verrechnungssatz als Snapshot ein (ADR-0012 — spätere Satzänderungen
 * dürfen bereits erfasste Zeiten nicht rückwirkend verändern).
 */
class TimeEntryService {
	public function __construct(
		private TimeEntryMapper $mapper,
		private ProjectMapper $projectMapper,
		private WorkTypeMapper $workTypeMapper,
		private RateService $rateService,
	) {
	}

	/** @return TimeEntry[] */
	public function listForUser(string $userId, ?string $fromDate = null, ?string $toDate = null): array {
		return $this->mapper->findByUser($userId, $fromDate, $toDate);
	}

	/** @return TimeEntry[] */
	public function listForProject(int $projectId): array {
		return $this->mapper->findByProject($projectId);
	}

	/** @throws \OutOfBoundsException */
	public function get(int $id): TimeEntry {
		$entry = $this->mapper->findById($id);
		if ($entry === null) {
			throw new \OutOfBoundsException("Time entry $id not found");
		}
		return $entry;
	}

	/**
	 * @param list<string> $groupIds Gruppen des erfassenden Users, für die
	 *   Satz-Auflösung (siehe RateService::resolveRate()).
	 * @throws \OutOfBoundsException wenn workTypeId oder projectId unbekannt sind
	 */
	public function create(
		string $userId,
		array $groupIds,
		int $workTypeId,
		?int $projectId,
		?int $orderId,
		string $entryDate,
		int $durationMinutes,
		int $breakMinutes,
		bool $billable,
		?string $notes,
	): TimeEntry {
		$workType = $this->workTypeMapper->findById($workTypeId);
		if ($workType === null) {
			throw new \OutOfBoundsException("Work type $workTypeId not found");
		}

		$customerContactUid = null;
		if ($projectId !== null) {
			$project = $this->projectMapper->findById($projectId);
			if ($project === null) {
				throw new \OutOfBoundsException("Project $projectId not found");
			}
			$customerContactUid = $project->getCustomerContactUid();
		}

		$rate = $this->rateService->resolveRate($userId, $groupIds, $workTypeId, $customerContactUid, $workType->getHourlyRate());

		$now = time();
		$entry = new TimeEntry();
		$entry->setUserId($userId);
		$entry->setProjectId($projectId);
		$entry->setOrderId($orderId);
		$entry->setWorkTypeId($workTypeId);
		$entry->setEntryDate($entryDate);
		$entry->setDurationMinutes($durationMinutes);
		$entry->setBreakMinutes($breakMinutes);
		$entry->setBillable($billable);
		$entry->setRateSnapshot($rate);
		$entry->setNotes($notes);
		$entry->setCreatedAt($now);
		$entry->setUpdatedAt($now);
		return $this->mapper->insert($entry);
	}

	/**
	 * Bearbeitet Datum/Dauer/Notizen eines Eintrags. Der Satz-Snapshot bleibt
	 * bewusst unangetastet — eine nachträgliche Korrektur der Arbeitszeit
	 * soll den zum Erfassungszeitpunkt gültigen Satz nicht neu auflösen.
	 *
	 * @throws \OutOfBoundsException
	 */
	public function update(int $id, string $entryDate, int $durationMinutes, int $breakMinutes, bool $billable, ?string $notes): TimeEntry {
		$entry = $this->get($id);
		$entry->setEntryDate($entryDate);
		$entry->setDurationMinutes($durationMinutes);
		$entry->setBreakMinutes($breakMinutes);
		$entry->setBillable($billable);
		$entry->setNotes($notes);
		$entry->setUpdatedAt(time());
		return $this->mapper->update($entry);
	}

	/** @throws \OutOfBoundsException */
	public function delete(int $id): void {
		$entry = $this->get($id);
		$this->mapper->delete($entry);
	}
}
