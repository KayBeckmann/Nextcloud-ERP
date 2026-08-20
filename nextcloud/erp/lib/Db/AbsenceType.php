<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getName()
 * @method void setName(string $name)
 * @method bool getAffectsVacationBalance()
 * @method void setAffectsVacationBalance(bool $affectsVacationBalance)
 */
class AbsenceType extends Entity implements \JsonSerializable {
	protected string $name = '';
	protected bool $affectsVacationBalance = false;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('affectsVacationBalance', 'boolean');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'name' => $this->getName(),
			'affectsVacationBalance' => $this->getAffectsVacationBalance(),
		];
	}
}
