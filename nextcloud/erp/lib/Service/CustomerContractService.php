<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\CustomerContract;
use OCA\ERP\Db\CustomerContractMapper;
use OCA\ERP\Db\CustomerContractRate;
use OCA\ERP\Db\CustomerContractRateMapper;

/** Kundenverträge + vertragliche Verrechnungssätze (ADR-0012). */
class CustomerContractService {
	public function __construct(
		private CustomerContractMapper $mapper,
		private CustomerContractRateMapper $rateMapper,
	) {
	}

	/** @return CustomerContract[] */
	public function listForCustomer(string $customerContactUid): array {
		return $this->mapper->findByCustomer($customerContactUid);
	}

	/** @throws \OutOfBoundsException */
	public function get(int $id): CustomerContract {
		$contract = $this->mapper->findById($id);
		if ($contract === null) {
			throw new \OutOfBoundsException("Customer contract $id not found");
		}
		return $contract;
	}

	public function getWithRates(int $id): array {
		$contract = $this->get($id);
		return [
			...$contract->jsonSerialize(),
			'rates' => $this->rateMapper->findByContract($id),
		];
	}

	public function create(string $customerContactUid, string $title, ?int $validFrom, ?int $validUntil, ?string $notes): CustomerContract {
		$now = time();
		$contract = new CustomerContract();
		$contract->setCustomerContactUid($customerContactUid);
		$contract->setTitle($title);
		$contract->setValidFrom($validFrom);
		$contract->setValidUntil($validUntil);
		$contract->setNotes($notes);
		$contract->setCreatedAt($now);
		$contract->setUpdatedAt($now);
		return $this->mapper->insert($contract);
	}

	/** @throws \OutOfBoundsException */
	public function addRate(int $contractId, int $workTypeId, ?string $principalType, ?string $principalId, float $rate): CustomerContractRate {
		$this->get($contractId);
		$entry = new CustomerContractRate();
		$entry->setContractId($contractId);
		$entry->setWorkTypeId($workTypeId);
		$entry->setPrincipalType($principalType);
		$entry->setPrincipalId($principalId);
		$entry->setRate($rate);
		return $this->rateMapper->insert($entry);
	}

	/** @throws \OutOfBoundsException */
	public function removeRate(int $contractId, int $id): void {
		$rate = $this->rateMapper->findOne($contractId, $id);
		if ($rate === null) {
			throw new \OutOfBoundsException("Contract rate $id not found");
		}
		$this->rateMapper->delete($rate);
	}
}
