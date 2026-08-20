<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\TimeEntryMapper;
use OCA\ERP\TimeAccount\TimeAccountCalculator;

/**
 * Orchestriert das Zeitkonto: holt Wochensoll + gebuchte Minuten aus der DB
 * und ruft die reine {@see TimeAccountCalculator}-Logik auf. Kein
 * gespeicherter Saldo — jede Abfrage rechnet live (ADR-0012).
 */
class TimeAccountService {
	public function __construct(
		private WorkScheduleService $workScheduleService,
		private TimeEntryMapper $timeEntryMapper,
	) {
	}

	/**
	 * @return array{userId: string, fromDate: string, toDate: string, weeklyHours: float, workdays: int, sollHours: float, istHours: float, balanceHours: float}
	 * @throws \InvalidArgumentException wenn toDate vor fromDate liegt
	 */
	public function getAccount(string $userId, string $fromDate, string $toDate): array {
		if ($toDate < $fromDate) {
			throw new \InvalidArgumentException('toDate must not be before fromDate');
		}

		$schedule = $this->workScheduleService->getForUser($userId);
		$actualMinutes = $this->timeEntryMapper->sumDurationMinutes($userId, $fromDate, $toDate);
		$result = TimeAccountCalculator::calculate($schedule->getWeeklyHours(), $fromDate, $toDate, $actualMinutes);

		return [
			'userId' => $userId,
			'fromDate' => $fromDate,
			'toDate' => $toDate,
			'weeklyHours' => $schedule->getWeeklyHours(),
			...$result,
		];
	}
}
