<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\AbsenceType;
use OCA\ERP\Db\AbsenceTypeMapper;

class AbsenceTypeService {
	public function __construct(
		private AbsenceTypeMapper $mapper,
	) {
	}

	/** @return AbsenceType[] */
	public function listAll(): array {
		return $this->mapper->findAll();
	}

	/** @throws \OutOfBoundsException */
	public function get(int $id): AbsenceType {
		$type = $this->mapper->findById($id);
		if ($type === null) {
			throw new \OutOfBoundsException("Absence type $id not found");
		}
		return $type;
	}

	public function create(string $name, bool $affectsVacationBalance): AbsenceType {
		$type = new AbsenceType();
		$type->setName($name);
		$type->setAffectsVacationBalance($affectsVacationBalance);
		return $this->mapper->insert($type);
	}
}
