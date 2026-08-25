<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\CompanyProfile;
use OCA\ERP\Db\CompanyProfileMapper;

/** Firmenprofil — systemweite Stammdaten unter Einstellungen (ADR-0022). */
class CompanyProfileService {
	public function __construct(
		private CompanyProfileMapper $mapper,
	) {
	}

	/** Nie noch nie gespeichert → leeres (nicht persistiertes) Profil, damit das Frontend ein Formular mit leeren Feldern zeigen kann. */
	public function get(): CompanyProfile {
		return $this->mapper->find() ?? new CompanyProfile();
	}

	public function update(
		?string $name,
		?string $addressLine,
		?string $postalCode,
		?string $city,
		?string $country,
		?string $taxId,
		?string $email,
		?string $phone,
		?string $footerText,
	): CompanyProfile {
		$profile = $this->mapper->find() ?? new CompanyProfile();
		$profile->setName($this->nullIfBlank($name));
		$profile->setAddressLine($this->nullIfBlank($addressLine));
		$profile->setPostalCode($this->nullIfBlank($postalCode));
		$profile->setCity($this->nullIfBlank($city));
		$profile->setCountry($this->nullIfBlank($country));
		$profile->setTaxId($this->nullIfBlank($taxId));
		$profile->setEmail($this->nullIfBlank($email));
		$profile->setPhone($this->nullIfBlank($phone));
		$profile->setFooterText($this->nullIfBlank($footerText));
		$profile->setUpdatedAt(time());

		return $profile->getId() === null
			? $this->mapper->insert($profile)
			: $this->mapper->update($profile);
	}

	private function nullIfBlank(?string $value): ?string {
		if ($value === null || trim($value) === '') {
			return null;
		}
		return $value;
	}
}
