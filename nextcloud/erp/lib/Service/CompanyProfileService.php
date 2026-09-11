<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\CompanyProfile;
use OCA\ERP\Db\CompanyProfileMapper;
use OCP\IUser;

/** Firmenprofil — systemweite Stammdaten unter Einstellungen (ADR-0022). */
class CompanyProfileService {
	public function __construct(
		private CompanyProfileMapper $mapper,
		private ?ErpFolderService $folderService = null,
		private ?CompanyLogoValidator $logoValidator = null,
	) {
		$this->logoValidator ??= new CompanyLogoValidator();
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
		?string $headerText = null,
		?string $legalForm = null,
		?string $managingDirector = null,
		?string $commercialRegister = null,
		?string $vatId = null,
		?string $taxNumber = null,
		?string $bankName = null,
		?string $iban = null,
		?string $bic = null,
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
		$profile->setHeaderText($this->nullIfBlank($headerText));
		$profile->setLegalForm($this->nullIfBlank($legalForm));
		$profile->setManagingDirector($this->nullIfBlank($managingDirector));
		$profile->setCommercialRegister($this->nullIfBlank($commercialRegister));
		$profile->setVatId($this->nullIfBlank($vatId));
		$profile->setTaxNumber($this->nullIfBlank($taxNumber));
		$profile->setBankName($this->nullIfBlank($bankName));
		$profile->setIban($this->nullIfBlank($iban));
		$profile->setBic($this->nullIfBlank($bic));
		$profile->setFooterText($this->nullIfBlank($footerText));
		$profile->setUpdatedAt(time());

		return $profile->getId() === null
			? $this->mapper->insert($profile)
			: $this->mapper->update($profile);
	}

	public function uploadLogo(IUser $user, string $base64Content): CompanyProfile {
		$bytes = base64_decode($base64Content, true);
		if ($bytes === false) {
			throw new \InvalidArgumentException('logo must be valid base64');
		}
		$extension = $this->logoValidator->validate($bytes);
		if ($this->folderService === null) {
			throw new \LogicException('Company logo storage is not configured');
		}
		$folder = $this->folderService->ensureCompanyLogoFolder($user);
		// Never use a client-controlled filename; the reference persisted is a file ID only.
		$file = $folder->newFile('company-logo-' . bin2hex(random_bytes(16)) . '.' . $extension);
		$file->putContent($bytes);
		$profile = $this->mapper->find() ?? new CompanyProfile();
		$profile->setLogoFileId($file->getId());
		$profile->setUpdatedAt(time());
		return $profile->getId() === null ? $this->mapper->insert($profile) : $this->mapper->update($profile);
	}

	private function nullIfBlank(?string $value): ?string {
		if ($value === null || trim($value) === '') {
			return null;
		}
		return $value;
	}
}
