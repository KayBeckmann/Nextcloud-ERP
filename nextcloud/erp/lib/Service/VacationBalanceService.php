<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Absence\VacationBalanceCalculator;
use OCA\ERP\Db\AbsenceRequestMapper;
use OCA\ERP\Db\AbsenceTypeMapper;

/**
 * Orchestriert den Resturlaub-Zähler (ADR-0033): holt Jahresanspruch +
 * genehmigte, urlaubswirksame Abwesenheitsanträge aus der DB und ruft die
 * reine {@see VacationBalanceCalculator}-Logik auf. Kein gespeicherter
 * Zähler — jede Abfrage rechnet live, analog zu {@see TimeAccountService}.
 */
class VacationBalanceService {
	public function __construct(
		private VacationEntitlementService $entitlementService,
		private AbsenceRequestMapper $absenceRequestMapper,
		private AbsenceTypeMapper $absenceTypeMapper,
	) {
	}

	/**
	 * @return array{userId: string, year: int, entitlementDaysPerYear: float, usedDays: int, remainingDays: float}
	 */
	public function getForUser(string $userId, int $year): array {
		$vacationTypeIds = array_map(
			static fn ($t) => $t->getId(),
			array_filter($this->absenceTypeMapper->findAll(), static fn ($t) => $t->getAffectsVacationBalance()),
		);

		$yearStart = sprintf('%04d-01-01', $year);
		$yearEnd = sprintf('%04d-12-31', $year);
		$approvedRequests = [];
		foreach ($this->absenceRequestMapper->findByUser($userId) as $request) {
			if ($request->getStatus() !== 'approved') {
				continue;
			}
			if (!in_array($request->getAbsenceTypeId(), $vacationTypeIds, true)) {
				continue;
			}
			// Vereinfachung: ein Antrag zählt zu dem Jahr, in dem er
			// beginnt — ein über den Jahreswechsel laufender Antrag wird
			// nicht anteilig aufgeteilt (ADR-0033, "Nicht Teil dieser
			// Phase").
			if ($request->getStartDate() < $yearStart || $request->getStartDate() > $yearEnd) {
				continue;
			}
			$approvedRequests[] = ['startDate' => $request->getStartDate(), 'endDate' => $request->getEndDate()];
		}

		$entitlement = $this->entitlementService->getForUser($userId)->getDaysPerYear();

		return [
			'userId' => $userId,
			'year' => $year,
			...VacationBalanceCalculator::calculate($entitlement, $approvedRequests),
		];
	}
}
