<?php

declare(strict_types=1);

namespace OCA\ERP\Costs;

/** Feste Kostenarten-Liste aus der Roadmap (Phase 10, ADR-0018). */
enum CostCategory: string {
	case Rent = 'rent';
	case PhoneInternet = 'phone_internet';
	case Software = 'software';
	case Salaries = 'salaries';
	case PayrollCosts = 'payroll_costs';
	case Insurance = 'insurance';
	case ProfessionalAssociation = 'professional_association';
	case TaxAdvisor = 'tax_advisor';
	case Vehicles = 'vehicles';
	case Tools = 'tools';
	case Energy = 'energy';
	case FinancingLeasing = 'financing_leasing';
	case Marketing = 'marketing';
	case Other = 'other';

	/** @return list<string> */
	public static function values(): array {
		return array_map(static fn (self $c) => $c->value, self::cases());
	}
}
