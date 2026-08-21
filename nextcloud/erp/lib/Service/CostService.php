<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Costs\CostCalculationService;
use OCA\ERP\Costs\CostCategory;
use OCA\ERP\Db\CostEntry;
use OCA\ERP\Db\CostEntryMapper;
use OCA\ERP\Db\CostSettings;
use OCA\ERP\Db\CostSettingsMapper;

/**
 * Betriebliche Kosten und Kalkulation (Roadmap Phase 10, ADR-0018).
 * Lädt die Rohdaten und delegiert die eigentliche Berechnung an
 * CostCalculationService (pure, DB-frei).
 */
class CostService {
	public function __construct(
		private CostEntryMapper $entryMapper,
		private CostSettingsMapper $settingsMapper,
	) {
	}

	/** @return CostEntry[] */
	public function listEntries(int $year): array {
		return $this->entryMapper->findByYear($year);
	}

	/** @throws \OutOfBoundsException */
	public function getEntry(int $id): CostEntry {
		$entry = $this->entryMapper->findById($id);
		if ($entry === null) {
			throw new \OutOfBoundsException("Cost entry $id not found");
		}
		return $entry;
	}

	/**
	 * @throws \InvalidArgumentException wenn category/month unbekannt bzw.
	 *         außerhalb 1–12 liegt
	 */
	public function createEntry(string $category, string $title, float $monthlyAmount, int $year, int $month, ?string $notes): CostEntry {
		if (CostCategory::tryFrom($category) === null) {
			throw new \InvalidArgumentException('category must be one of: ' . implode(', ', CostCategory::values()));
		}
		if ($month < 1 || $month > 12) {
			throw new \InvalidArgumentException('month must be between 1 and 12');
		}
		if (trim($title) === '') {
			throw new \InvalidArgumentException('title must not be empty');
		}

		$now = time();
		$entry = new CostEntry();
		$entry->setCategory($category);
		$entry->setTitle($title);
		$entry->setMonthlyAmount($monthlyAmount);
		$entry->setYear($year);
		$entry->setMonth($month);
		$entry->setNotes($notes);
		$entry->setCreatedAt($now);
		$entry->setUpdatedAt($now);
		return $this->entryMapper->insert($entry);
	}

	/** @throws \OutOfBoundsException|\InvalidArgumentException */
	public function updateEntry(int $id, string $category, string $title, float $monthlyAmount, int $year, int $month, ?string $notes): CostEntry {
		$entry = $this->getEntry($id);
		if (CostCategory::tryFrom($category) === null) {
			throw new \InvalidArgumentException('category must be one of: ' . implode(', ', CostCategory::values()));
		}
		if ($month < 1 || $month > 12) {
			throw new \InvalidArgumentException('month must be between 1 and 12');
		}
		if (trim($title) === '') {
			throw new \InvalidArgumentException('title must not be empty');
		}

		$entry->setCategory($category);
		$entry->setTitle($title);
		$entry->setMonthlyAmount($monthlyAmount);
		$entry->setYear($year);
		$entry->setMonth($month);
		$entry->setNotes($notes);
		$entry->setUpdatedAt(time());
		return $this->entryMapper->update($entry);
	}

	/** @throws \OutOfBoundsException */
	public function removeEntry(int $id): void {
		$this->entryMapper->delete($this->getEntry($id));
	}

	/** Legt bei Bedarf Standardeinstellungen für das Jahr an (1.600 Std., 0% Aufschlag). */
	public function getSettings(int $year): CostSettings {
		$settings = $this->settingsMapper->findByYear($year);
		if ($settings !== null) {
			return $settings;
		}

		$now = time();
		$settings = new CostSettings();
		$settings->setYear($year);
		$settings->setCreatedAt($now);
		$settings->setUpdatedAt($now);
		return $this->settingsMapper->insert($settings);
	}

	/** @throws \InvalidArgumentException wenn ein Wert negativ ist */
	public function updateSettings(int $year, float $productiveHoursPerYear, float $materialSurchargePercent, float $productSurchargePercent): CostSettings {
		if ($productiveHoursPerYear < 0 || $materialSurchargePercent < 0 || $productSurchargePercent < 0) {
			throw new \InvalidArgumentException('values must not be negative');
		}

		$settings = $this->getSettings($year);
		$settings->setProductiveHoursPerYear($productiveHoursPerYear);
		$settings->setMaterialSurchargePercent($materialSurchargePercent);
		$settings->setProductSurchargePercent($productSurchargePercent);
		$settings->setUpdatedAt(time());
		return $this->settingsMapper->update($settings);
	}

	/**
	 * Jahresübersicht: Kostenposten, Einstellungen, Jahressumme,
	 * Kategorie-Aufschlüsselung und der daraus berechnete interne
	 * Stundensatz (ADR-0018, rein informativ).
	 */
	public function getYearOverview(int $year): array {
		$entries = $this->listEntries($year);
		$settings = $this->getSettings($year);

		$entryData = array_map(static fn (CostEntry $e) => [
			'category' => $e->getCategory(),
			'monthlyAmount' => $e->getMonthlyAmount(),
		], $entries);

		$annualCosts = CostCalculationService::sumAnnualCosts($entryData);

		return [
			'year' => $year,
			'entries' => $entries,
			'settings' => $settings,
			'annualCosts' => $annualCosts,
			'costsByCategory' => CostCalculationService::sumByCategory($entryData),
			'internalHourlyRate' => CostCalculationService::calculateInternalHourlyRate($annualCosts, $settings->getProductiveHoursPerYear()),
		];
	}
}
