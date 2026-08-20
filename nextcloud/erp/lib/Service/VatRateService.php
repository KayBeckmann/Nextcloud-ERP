<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\VatRate;
use OCA\ERP\Db\VatRateMapper;

/** MwSt.-Sätze — systemweite Stammdaten unter Einstellungen (ADR-0011). */
class VatRateService {
	public function __construct(
		private VatRateMapper $mapper,
	) {
	}

	/** @return VatRate[] */
	public function listAll(): array {
		return $this->mapper->findAll();
	}

	public function create(string $name, float $percentage, bool $isDefault, bool $active): VatRate {
		$now = time();
		if ($isDefault) {
			$this->clearExistingDefault();
		}
		$rate = new VatRate();
		$rate->setName($name);
		$rate->setPercentage($percentage);
		$rate->setIsDefault($isDefault);
		$rate->setActive($active);
		$rate->setCreatedAt($now);
		$rate->setUpdatedAt($now);
		return $this->mapper->insert($rate);
	}

	/** @throws \OutOfBoundsException */
	public function update(int $id, string $name, float $percentage, bool $isDefault, bool $active): VatRate {
		$rate = $this->mapper->findById($id);
		if ($rate === null) {
			throw new \OutOfBoundsException("VAT rate $id not found");
		}
		if ($isDefault && !$rate->getIsDefault()) {
			$this->clearExistingDefault();
		}
		$rate->setName($name);
		$rate->setPercentage($percentage);
		$rate->setIsDefault($isDefault);
		$rate->setActive($active);
		$rate->setUpdatedAt(time());
		return $this->mapper->update($rate);
	}

	/** Nur ein Satz darf gleichzeitig Standard sein. */
	private function clearExistingDefault(): void {
		foreach ($this->mapper->findAll() as $existing) {
			if ($existing->getIsDefault()) {
				$existing->setIsDefault(false);
				$existing->setUpdatedAt(time());
				$this->mapper->update($existing);
			}
		}
	}
}
