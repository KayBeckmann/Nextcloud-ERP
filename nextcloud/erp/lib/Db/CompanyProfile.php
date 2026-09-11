<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Firmenprofil (ADR-0022) — Singleton, immer id=1. Zeigt im Beleg-PDF als
 * Absender-Kopfblock. `footerText` ist bewusst ein freies Mehrzeilenfeld
 * statt eines vollständigen Impressum-Datenmodells (Bankverbindung,
 * Handelsregister, Geschäftsführer, …) — Kay befüllt es selbst.
 *
 * @method string|null getName()
 * @method void setName(?string $name)
 * @method string|null getAddressLine()
 * @method void setAddressLine(?string $addressLine)
 * @method string|null getPostalCode()
 * @method void setPostalCode(?string $postalCode)
 * @method string|null getCity()
 * @method void setCity(?string $city)
 * @method string|null getCountry()
 * @method void setCountry(?string $country)
 * @method string|null getTaxId()
 * @method void setTaxId(?string $taxId)
 * @method string|null getEmail()
 * @method void setEmail(?string $email)
 * @method string|null getPhone()
 * @method void setPhone(?string $phone)
 * @method string|null getHeaderText()
 * @method void setHeaderText(?string $headerText)
 * @method string|null getLegalForm()
 * @method void setLegalForm(?string $legalForm)
 * @method string|null getManagingDirector()
 * @method void setManagingDirector(?string $managingDirector)
 * @method string|null getCommercialRegister()
 * @method void setCommercialRegister(?string $commercialRegister)
 * @method string|null getVatId()
 * @method void setVatId(?string $vatId)
 * @method string|null getTaxNumber()
 * @method void setTaxNumber(?string $taxNumber)
 * @method string|null getBankName()
 * @method void setBankName(?string $bankName)
 * @method string|null getIban()
 * @method void setIban(?string $iban)
 * @method string|null getBic()
 * @method void setBic(?string $bic)
 * @method string|null getFooterText()
 * @method void setFooterText(?string $footerText)
 * @method int|null getLogoFileId()
 * @method void setLogoFileId(?int $logoFileId)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class CompanyProfile extends Entity implements \JsonSerializable {
	protected ?string $name = null;
	protected ?string $addressLine = null;
	protected ?string $postalCode = null;
	protected ?string $city = null;
	protected ?string $country = null;
	protected ?string $taxId = null;
	protected ?string $email = null;
	protected ?string $phone = null;
	protected ?string $headerText = null;
	protected ?string $legalForm = null;
	protected ?string $managingDirector = null;
	protected ?string $commercialRegister = null;
	protected ?string $vatId = null;
	protected ?string $taxNumber = null;
	protected ?string $bankName = null;
	protected ?string $iban = null;
	protected ?string $bic = null;
	protected ?string $footerText = null;
	protected ?int $logoFileId = null;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('logoFileId', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'name' => $this->getName(),
			'addressLine' => $this->getAddressLine(),
			'postalCode' => $this->getPostalCode(),
			'city' => $this->getCity(),
			'country' => $this->getCountry(),
			'taxId' => $this->getTaxId(),
			'email' => $this->getEmail(),
			'phone' => $this->getPhone(),
			'headerText' => $this->getHeaderText(), 'legalForm' => $this->getLegalForm(),
			'managingDirector' => $this->getManagingDirector(), 'commercialRegister' => $this->getCommercialRegister(),
			'vatId' => $this->getVatId(), 'taxNumber' => $this->getTaxNumber(),
			'bankName' => $this->getBankName(), 'iban' => $this->getIban(), 'bic' => $this->getBic(),
			'footerText' => $this->getFooterText(),
			'logoFileId' => $this->getLogoFileId(),
		];
	}
}
