<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\WorkSchedule;
use OCA\ERP\Db\WorkScheduleMapper;

/** Arbeitszeitmodell je User (ADR-0012) — aktuell nur ein Wochensoll, keine Staffelungen/Schichtpläne. */
class WorkScheduleService {
	public const DEFAULT_WEEKLY_HOURS = 40.0;

	public function __construct(
		private WorkScheduleMapper $mapper,
	) {
	}

	/** Liefert immer ein Ergebnis — ohne explizit hinterlegtes Modell gilt der Default. */
	public function getForUser(string $userId): WorkSchedule {
		$existing = $this->mapper->findByUser($userId);
		if ($existing !== null) {
			return $existing;
		}
		$fallback = new WorkSchedule();
		$fallback->setUserId($userId);
		$fallback->setWeeklyHours(self::DEFAULT_WEEKLY_HOURS);
		return $fallback;
	}

	public function setForUser(string $userId, float $weeklyHours): WorkSchedule {
		$existing = $this->mapper->findByUser($userId);
		$now = time();
		if ($existing !== null) {
			$existing->setWeeklyHours($weeklyHours);
			$existing->setUpdatedAt($now);
			return $this->mapper->update($existing);
		}

		$schedule = new WorkSchedule();
		$schedule->setUserId($userId);
		$schedule->setWeeklyHours($weeklyHours);
		$schedule->setCreatedAt($now);
		$schedule->setUpdatedAt($now);
		return $this->mapper->insert($schedule);
	}
}
