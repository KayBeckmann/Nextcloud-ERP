<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use horstoeko\zugferd\codelists\ZugferdInvoiceType;
use horstoeko\zugferd\codelists\ZugferdVatCategoryCodes;
use horstoeko\zugferd\codelists\ZugferdVatTypeCodes;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdDocumentPdfMerger;
use horstoeko\zugferd\ZugferdProfiles;
use horstoeko\zugferd\ZugferdXsdValidator;
use OCA\ERP\Db\CompanyProfile;
use OCA\ERP\Db\InvoicePosition;
use OCA\ERP\EInvoice\CountryCodeResolver;
use OCA\ERP\EInvoice\ZugferdUnitCodeResolver;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IUser;

/**
 * Erzeugt EN16931/XRechnung-konforme CII-XML (ZUGFeRD-Profil "XRECHNUNG",
 * EN16931 via `horstoeko/zugferd`) für ausgestellte Rechnungen sowie den
 * dazugehörigen ZUGFeRD-Hybrid-PDF (ADR-0040). Reine CII-Erzeugung — kein
 * UBL-Syntax, siehe ADR-0040 für die Begründung.
 *
 * **Nicht Teil dieser Phase** (siehe ADR-0040):
 * - Volle KoSIT-Schematron-Geschäftsregelprüfung (`ZugferdKositValidator`
 *   benötigt eine Java-Laufzeit) — hier wird nur die strukturelle
 *   XSD-Validierung (`ZugferdXsdValidator`) geprüft.
 * - CreditNote-Export.
 * - Echte §19-UStG-Kleinunternehmer-Befreiungsgründe (wie bereits in
 *   ADR-0038 für das PDF dokumentiert).
 */
class XRechnungService {
	public function __construct(
		private InvoiceService $invoiceService,
		private CompanyProfileService $companyProfileService,
		private ContactsService $contactsService,
		private IRootFolder $rootFolder,
	) {
	}

	/**
	 * @throws \DomainException wenn Rechnung oder Firmenprofil nicht
	 *     exportierbar sind (fehlende Pflichtangaben, Entwurfsstatus, …)
	 * @throws \RuntimeException wenn die erzeugte XML die XSD-Validierung
	 *     nicht besteht (zeigt einen Mapping-Fehler in diesem Dienst an)
	 */
	public function generateXml(int $invoiceId): string {
		$document = $this->buildDocument($invoiceId);

		// Nextcloud deaktiviert global jedes Nachladen externer XML-Entities
		// (lib/base.php, XXE-Hardening) — das blockiert auch die legitimen
		// xsd:import/xsd:include-Verweise INNERHALB der mitgelieferten
		// EN16931-XSD-Dateien, die keinen Bezug zu nutzergesteuertem Input
		// haben. Für die Dauer dieser einen Validierung auf das PHP-Standard-
		// verhalten zurückschalten und Nextclouds Sperre danach exakt wieder
		// herstellen (derselbe Closure-Inhalt wie lib/base.php).
		libxml_set_external_entity_loader(null);
		try {
			$validator = new ZugferdXsdValidator($document);
			$validator->validate();
		} finally {
			libxml_set_external_entity_loader(static fn () => null);
		}
		if ($validator->hasValidationErrors()) {
			throw new \RuntimeException('Generated e-invoice XML failed XSD validation: ' . implode('; ', $validator->validationErrors()));
		}

		return $document->getContent();
	}

	/**
	 * ZUGFeRD-Hybrid-PDF: die bereits beim Ausstellen erzeugte und
	 * gespeicherte Rechnungs-PDF (ADR-0013/0022), ergänzt um die
	 * EN16931-XML als eingebettetes Anhang-PDF/A-3-Dokument.
	 *
	 * @throws \DomainException wenn Rechnung/Firmenprofil nicht exportierbar
	 *     sind oder noch keine PDF gespeichert ist
	 */
	public function generateZugferdPdf(int $invoiceId, IUser $user): string {
		$full = $this->invoiceService->getFullInvoice($invoiceId);
		$documentFileId = $full['documentFileId'] ?? null;
		if ($documentFileId === null) {
			throw new \DomainException('Invoice has no stored PDF document to embed the e-invoice XML into');
		}

		$xml = $this->generateXml($invoiceId);
		$pdfBytes = $this->readStoredPdf($user, (int) $documentFileId);

		$merger = new ZugferdDocumentPdfMerger($xml, $pdfBytes);
		$merger->setPdfAConformanceLevelToBasic();
		$merger->generateDocument();
		return $merger->downloadString();
	}

	private function readStoredPdf(IUser $user, int $fileId): string {
		$nodes = $this->rootFolder->getUserFolder($user->getUID())->getById($fileId);
		$file = $nodes[0] ?? null;
		if (!$file instanceof File) {
			throw new \DomainException("Stored invoice PDF (file $fileId) is not accessible");
		}
		return $file->getContent();
	}

	private function buildDocument(int $invoiceId): ZugferdDocumentBuilder {
		$full = $this->invoiceService->getFullInvoice($invoiceId);
		if (($full['invoiceNumber'] ?? null) === null || $full['status'] === 'draft') {
			throw new \DomainException("Invoice $invoiceId must be issued before it can be exported as an e-invoice");
		}

		$profile = $this->companyProfileService->get();
		$missingProfileFields = $this->companyProfileService->missingMandatoryFields($profile);
		if ($missingProfileFields !== []) {
			throw new \DomainException('Firmenprofil ist für den E-Rechnung-Export unvollständig: ' . implode(', ', $missingProfileFields));
		}

		$buyerContactUid = $full['customerContactUid'] ?? null;
		if ($buyerContactUid === null) {
			throw new \DomainException("Invoice $invoiceId has no linked customer contact");
		}
		$buyer = $this->contactsService->structuredAddressFor($buyerContactUid);
		if ($buyer['street'] === '' || $buyer['postalCode'] === '' || $buyer['city'] === '') {
			throw new \DomainException("Customer contact address is incomplete for e-invoice export (invoice $invoiceId)");
		}

		$issuedAt = $full['issuedAt'] ?? null;
		if ($issuedAt === null) {
			throw new \DomainException("Invoice $invoiceId has no issue date");
		}

		$calculation = $full['calculation'];
		$positions = $full['positions'];

		$document = ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_XRECHNUNG_3);
		$document
			->setDocumentInformation(
				(string) $full['invoiceNumber'],
				ZugferdInvoiceType::INVOICE,
				(new \DateTimeImmutable())->setTimestamp($issuedAt),
				'EUR',
			)
			->setDocumentSeller((string) $profile->getName())
			->setDocumentSellerAddress(
				$profile->getAddressLine(),
				null,
				null,
				$profile->getPostalCode(),
				$profile->getCity(),
				CountryCodeResolver::resolve($profile->getCountry()),
			)
			->setDocumentBuyer($buyer['displayName'])
			->setDocumentBuyerAddress(
				$buyer['street'],
				null,
				null,
				$buyer['postalCode'],
				$buyer['city'],
				CountryCodeResolver::resolve($buyer['country']),
			);

		$this->applySellerTaxRegistration($document, $profile);
		$this->applyPaymentMeansAndTerms($document, $profile, $full['dueDate'] ?? null);

		foreach ($positions as $index => $position) {
			$this->addPosition($document, $position, $index + 1);
		}

		foreach ($calculation['vatBreakdown'] as $bucket) {
			$categoryCode = $bucket['ratePercent'] > 0 ? ZugferdVatCategoryCodes::STAN_RATE : ZugferdVatCategoryCodes::ZERO_RATE_GOOD;
			$document->addDocumentTaxSimple(
				$categoryCode,
				ZugferdVatTypeCodes::VALUE_ADDED_TAX,
				$bucket['netBase'],
				$bucket['vatAmount'],
				$bucket['ratePercent'],
			);
		}

		$vatTotal = round($calculation['grossTotal'] - $calculation['netSubtotal'], 2);
		$document->setDocumentSummation(
			$calculation['grossTotal'],
			round($calculation['grossTotal'] - $full['paidAmount'], 2),
			$calculation['netSubtotalBeforeDiscount'],
			0.0,
			$calculation['documentDiscountAmount'],
			$calculation['netSubtotal'],
			$vatTotal,
			0.0,
			$full['paidAmount'],
		);

		return $document;
	}

	private function applySellerTaxRegistration(ZugferdDocumentBuilder $document, CompanyProfile $profile): void {
		if ($profile->getVatId() !== null) {
			$document->addDocumentSellerVATRegistrationNumber($profile->getVatId());
		}
		if ($profile->getTaxNumber() !== null) {
			$document->addDocumentSellerTaxNumber($profile->getTaxNumber());
		}
	}

	private function applyPaymentMeansAndTerms(ZugferdDocumentBuilder $document, CompanyProfile $profile, ?string $dueDate): void {
		if ($profile->getIban() !== null) {
			$document->addDocumentPaymentMeanToCreditTransfer(
				$profile->getIban(),
				$profile->getName(),
				null,
				$profile->getBic(),
			);
		}
		if ($dueDate !== null) {
			$document->addDocumentPaymentTerm(null, new \DateTimeImmutable($dueDate));
		}
	}

	private function addPosition(ZugferdDocumentBuilder $document, InvoicePosition $position, int $lineId): void {
		$quantity = $position->getQuantity();
		$unitPriceNet = $position->getUnitPriceNet();
		$vatRatePercent = $position->getVatRatePercent();
		$discountPercent = $position->getDiscountPercent();
		$unitCode = ZugferdUnitCodeResolver::resolve($position->getUnit());
		$categoryCode = $vatRatePercent > 0 ? ZugferdVatCategoryCodes::STAN_RATE : ZugferdVatCategoryCodes::ZERO_RATE_GOOD;
		// Dieselbe Rundungslogik wie InvoicePosition::jsonSerialize()['netTotal'].
		$lineNetTotal = round($quantity * $unitPriceNet * (1 - $discountPercent / 100), 2);

		$document->addNewPosition((string) $lineId)
			->setDocumentPositionProductDetails($position->getDescription())
			->setDocumentPositionNetPrice($unitPriceNet)
			->setDocumentPositionQuantity($quantity, $unitCode)
			->addDocumentPositionTax($categoryCode, ZugferdVatTypeCodes::VALUE_ADDED_TAX, $vatRatePercent, round($lineNetTotal * $vatRatePercent / 100, 2))
			->setDocumentPositionLineSummation($lineNetTotal);

		if ($discountPercent > 0) {
			$grossLineAmount = round($quantity * $unitPriceNet, 2);
			$document->addDocumentPositionAllowanceCharge(
				round($grossLineAmount - $lineNetTotal, 2),
				false,
				$discountPercent,
				$grossLineAmount,
				null,
				'Rabatt',
			);
		}
	}
}
