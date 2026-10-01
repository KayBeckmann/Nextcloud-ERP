<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\VacationEntitlement;
use OCA\ERP\Db\VacationEntitlementMapper;

/**
 * Jährlicher Urlaubsanspruch je User (ADR-0033) — analog zu
 * {@see WorkScheduleService}, kein Historie-/Jahresverlauf, nur der
 * aktuell gültige Wert.
 */
class VacationEntitlementService {
	// Gesetzlicher Mindesturlaub bei 5-Tage-Woche (§ 3 BUrlG) — ein
	// neutraler, rechtlich begründbarer Startwert, keine betriebliche
	// Empfehlung. Vor produktivem Einsatz je User den tatsächlichen
	// vertraglichen Anspruch pflegen.
	public const DEFAULT_DAYS_PER_YEAR = 20.0;

	public function __construct(
		private VacationEntitlementMapper $mapper,
	) {
	}

	/** Liefert immer ein Ergebnis — ohne explizit hinterlegten Anspruch gilt der gesetzliche Mindesturlaub. */
	public function getForUser(string $userId): VacationEntitlement {
		$existing = $this->mapper->findByUser($userId);
		if ($existing !== null) {
			return $existing;
		}
		$fallback = new VacationEntitlement();
		$fallback->setUserId($userId);
		$fallback->setDaysPerYear(self::DEFAULT_DAYS_PER_YEAR);
		return $fallback;
	}

	public function setForUser(string $userId, float $daysPerYear): VacationEntitlement {
		$existing = $this->mapper->findByUser($userId);
		$now = time();
		if ($existing !== null) {
			$existing->setDaysPerYear($daysPerYear);
			$existing->setUpdatedAt($now);
			return $this->mapper->update($existing);
		}

		$entitlement = new VacationEntitlement();
		$entitlement->setUserId($userId);
		$entitlement->setDaysPerYear($daysPerYear);
		$entitlement->setCreatedAt($now);
		$entitlement->setUpdatedAt($now);
		return $this->mapper->insert($entitlement);
	}
}
