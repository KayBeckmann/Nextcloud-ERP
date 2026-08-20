<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\CustomerContract;
use OCA\ERP\Db\CustomerContractMapper;
use OCA\ERP\Db\CustomerContractRateMapper;
use OCA\ERP\Db\StandardRate;
use OCA\ERP\Db\StandardRateMapper;
use OCA\ERP\Rates\RateResolutionService;

/**
 * Orchestriert DB-Zugriff für Verrechnungssätze und ruft die reine
 * RateResolutionService-Logik auf (ADR-0012).
 */
class RateService {
	public function __construct(
		private StandardRateMapper $standardRateMapper,
		private CustomerContractMapper $contractMapper,
		private CustomerContractRateMapper $contractRateMapper,
	) {
	}

	/** @return StandardRate[] */
	public function listStandardRates(): array {
		return $this->standardRateMapper->findAll();
	}

	/** Legt einen Satz an oder aktualisiert ihn, falls (workTypeId, principalType, principalId) schon existiert. */
	public function setStandardRate(int $workTypeId, ?string $principalType, ?string $principalId, float $rate): StandardRate {
		$existing = $this->standardRateMapper->findExisting($workTypeId, $principalType, $principalId);
		$now = time();
		if ($existing !== null) {
			$existing->setRate($rate);
			$existing->setUpdatedAt($now);
			return $this->standardRateMapper->update($existing);
		}

		$entry = new StandardRate();
		$entry->setWorkTypeId($workTypeId);
		$entry->setPrincipalType($principalType);
		$entry->setPrincipalId($principalId);
		$entry->setRate($rate);
		$entry->setCreatedAt($now);
		$entry->setUpdatedAt($now);
		return $this->standardRateMapper->insert($entry);
	}

	/**
	 * Löst den effektiven Satz für einen User + Arbeitsart auf (ADR-0012,
	 * 6-stufige Priorität). $customerContactUid wählt einen ggf. aktiven
	 * Kundenvertrag aus (erster zum jetzigen Zeitpunkt gültiger Vertrag).
	 *
	 * @param list<string> $groupIds
	 */
	public function resolveRate(
		string $userId,
		array $groupIds,
		int $workTypeId,
		?string $customerContactUid,
		?float $workTypeDefaultRate,
	): float {
		$standardRates = array_map(static fn (StandardRate $r) => [
			'workTypeId' => $r->getWorkTypeId(),
			'principalType' => $r->getPrincipalType(),
			'principalId' => $r->getPrincipalId(),
			'rate' => $r->getRate(),
		], $this->standardRateMapper->findAll());

		$contractRates = [];
		if ($customerContactUid !== null) {
			$contract = $this->findActiveContract($customerContactUid);
			if ($contract !== null) {
				$contractRates = array_map(static fn ($r) => [
					'workTypeId' => $r->getWorkTypeId(),
					'principalType' => $r->getPrincipalType(),
					'principalId' => $r->getPrincipalId(),
					'rate' => $r->getRate(),
				], $this->contractRateMapper->findByContract($contract->getId()));
			}
		}

		return RateResolutionService::resolve($contractRates, $standardRates, $workTypeId, $userId, $groupIds, $workTypeDefaultRate);
	}

	private function findActiveContract(string $customerContactUid): ?CustomerContract {
		$now = time();
		foreach ($this->contractMapper->findByCustomer($customerContactUid) as $contract) {
			$fromOk = $contract->getValidFrom() === null || $contract->getValidFrom() <= $now;
			$untilOk = $contract->getValidUntil() === null || $contract->getValidUntil() >= $now;
			if ($fromOk && $untilOk) {
				return $contract;
			}
		}
		return null;
	}
}
