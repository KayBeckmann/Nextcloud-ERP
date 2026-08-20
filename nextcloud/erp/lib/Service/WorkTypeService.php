<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\WorkType;
use OCA\ERP\Db\WorkTypeMapper;

/** Arbeitsarten (Monteur, Meister, ...) — Stammdaten unter Einstellungen (ADR-0011). */
class WorkTypeService {
	public function __construct(
		private WorkTypeMapper $mapper,
	) {
	}

	/** @return WorkType[] */
	public function listAll(): array {
		return $this->mapper->findAll();
	}

	public function create(string $name, float $hourlyRate, ?int $vatRateId, bool $active): WorkType {
		$now = time();
		$workType = new WorkType();
		$workType->setName($name);
		$workType->setHourlyRate($hourlyRate);
		$workType->setVatRateId($vatRateId);
		$workType->setActive($active);
		$workType->setCreatedAt($now);
		$workType->setUpdatedAt($now);
		return $this->mapper->insert($workType);
	}

	/** @throws \OutOfBoundsException */
	public function update(int $id, string $name, float $hourlyRate, ?int $vatRateId, bool $active): WorkType {
		$workType = $this->mapper->findById($id);
		if ($workType === null) {
			throw new \OutOfBoundsException("Work type $id not found");
		}
		$workType->setName($name);
		$workType->setHourlyRate($hourlyRate);
		$workType->setVatRateId($vatRateId);
		$workType->setActive($active);
		$workType->setUpdatedAt(time());
		return $this->mapper->update($workType);
	}
}
