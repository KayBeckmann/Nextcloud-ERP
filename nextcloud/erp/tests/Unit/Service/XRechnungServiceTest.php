<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\CompanyProfile;
use OCA\ERP\Db\InvoicePosition;
use OCA\ERP\Service\CompanyProfileService;
use OCA\ERP\Service\ContactsService;
use OCA\ERP\Service\InvoiceService;
use OCA\ERP\Service\XRechnungService;
use OCP\Files\IRootFolder;
use PHPUnit\Framework\TestCase;

/**
 * Bewusst PHPUnit\Framework\TestCase mit gemockten Services statt
 * @group DB — XRechnungService hat keine eigene DB-Abhängigkeit, sondern
 * baut ausschließlich auf den bereits geladenen Daten von getFullInvoice()
 * auf (ADR-0040). Ein erfolgreicher generateXml()-Aufruf beweist dabei
 * bereits die XSD-Gültigkeit der erzeugten XML — die Methode wirft bei
 * einem Validierungsfehler selbst eine RuntimeException.
 */
final class XRechnungServiceTest extends TestCase {
	private InvoiceService $invoiceService;
	private CompanyProfileService $companyProfileService;
	private ContactsService $contactsService;
	private XRechnungService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->invoiceService = $this->createMock(InvoiceService::class);
		$this->companyProfileService = $this->createMock(CompanyProfileService::class);
		$this->contactsService = $this->createMock(ContactsService::class);
		$this->service = new XRechnungService(
			$this->invoiceService,
			$this->companyProfileService,
			$this->contactsService,
			$this->createMock(IRootFolder::class),
		);

		$this->companyProfileService->method('get')->willReturn($this->sellerProfile());
		$this->companyProfileService->method('missingMandatoryFields')->willReturn([]);
		$this->contactsService->method('structuredAddressFor')->willReturn([
			'displayName' => 'Muster GmbH',
			'street' => 'Musterstraße 1',
			'postalCode' => '12345',
			'city' => 'Musterstadt',
			'country' => 'Deutschland',
		]);
	}

	private function sellerProfile(): CompanyProfile {
		$profile = new CompanyProfile();
		$profile->setName('Acme GmbH');
		$profile->setAddressLine('Hauptstraße 1');
		$profile->setPostalCode('54321');
		$profile->setCity('Beispielstadt');
		$profile->setCountry('Deutschland');
		$profile->setVatId('DE123456789');
		$profile->setIban('DE89370400440532013000');
		$profile->setBic('COBADEFFXXX');
		return $profile;
	}

	private function position(float $quantity, float $unitPriceNet, float $vatRatePercent, float $discountPercent = 0.0, string $unit = 'Stk'): InvoicePosition {
		$position = new InvoicePosition();
		$position->setDescription('Testleistung');
		$position->setQuantity($quantity);
		$position->setUnit($unit);
		$position->setUnitPriceNet($unitPriceNet);
		$position->setVatRatePercent($vatRatePercent);
		$position->setDiscountPercent($discountPercent);
		return $position;
	}

	/** @param list<InvoicePosition> $positions */
	private function fullInvoice(array $positions, array $overrides = []): array {
		$netSubtotal = 0.0;
		$vatBase = [];
		foreach ($positions as $p) {
			$lineNet = round($p->getQuantity() * $p->getUnitPriceNet() * (1 - $p->getDiscountPercent() / 100), 2);
			$netSubtotal += $lineNet;
			$rateKey = number_format($p->getVatRatePercent(), 2, '.', '');
			$vatBase[$rateKey] = ($vatBase[$rateKey] ?? 0.0) + $lineNet;
		}
		$netSubtotal = round($netSubtotal, 2);
		$vatBreakdown = [];
		$vatTotal = 0.0;
		foreach ($vatBase as $rateKey => $base) {
			$amount = round($base * (float) $rateKey / 100, 2);
			$vatTotal += $amount;
			$vatBreakdown[] = ['ratePercent' => (float) $rateKey, 'netBase' => $base, 'vatAmount' => $amount];
		}
		$grossTotal = round($netSubtotal + $vatTotal, 2);

		return array_merge([
			'invoiceNumber' => 'R-2026-0001',
			'status' => 'issued',
			'customerContactUid' => 'contact-1',
			'issuedAt' => 1780000000,
			'dueDate' => '2026-11-01',
			'paidAmount' => 0.0,
			'documentFileId' => 42,
			'positions' => $positions,
			'calculation' => [
				'netSubtotalBeforeDiscount' => $netSubtotal,
				'documentDiscountAmount' => 0.0,
				'netSubtotal' => $netSubtotal,
				'vatBreakdown' => $vatBreakdown,
				'grossTotal' => $grossTotal,
			],
		], $overrides);
	}

	public function testGenerateXmlProducesXsdValidDocumentForSimplePosition(): void {
		$full = $this->fullInvoice([$this->position(2.0, 100.0, 19.0)]);
		$this->invoiceService->method('getFullInvoice')->willReturn($full);

		$xml = $this->service->generateXml(1);

		self::assertStringContainsString('R-2026-0001', $xml);
		self::assertStringContainsString('Acme GmbH', $xml);
		self::assertStringContainsString('Muster GmbH', $xml);
	}

	public function testGenerateXmlProducesXsdValidDocumentWithPositionDiscount(): void {
		$full = $this->fullInvoice([$this->position(2.0, 100.0, 19.0, 10.0)]);
		$this->invoiceService->method('getFullInvoice')->willReturn($full);

		$xml = $this->service->generateXml(1);

		self::assertStringContainsString('R-2026-0001', $xml);
	}

	public function testGenerateXmlProducesXsdValidDocumentWithZeroVatPosition(): void {
		$full = $this->fullInvoice([$this->position(1.0, 50.0, 0.0)]);
		$this->invoiceService->method('getFullInvoice')->willReturn($full);

		$xml = $this->service->generateXml(1);

		self::assertStringContainsString('R-2026-0001', $xml);
	}

	public function testGenerateXmlRejectsDraftInvoice(): void {
		$full = $this->fullInvoice([$this->position(1.0, 10.0, 19.0)], ['status' => 'draft', 'invoiceNumber' => null]);
		$this->invoiceService->method('getFullInvoice')->willReturn($full);

		$this->expectException(\DomainException::class);
		$this->service->generateXml(1);
	}

	public function testGenerateXmlRejectsInvoiceWithoutCustomerContact(): void {
		$full = $this->fullInvoice([$this->position(1.0, 10.0, 19.0)], ['customerContactUid' => null]);
		$this->invoiceService->method('getFullInvoice')->willReturn($full);

		$this->expectException(\DomainException::class);
		$this->service->generateXml(1);
	}

	public function testGenerateXmlRejectsIncompleteBuyerAddress(): void {
		$this->contactsService = $this->createMock(ContactsService::class);
		$this->contactsService->method('structuredAddressFor')->willReturn([
			'displayName' => 'Muster GmbH', 'street' => '', 'postalCode' => '', 'city' => '', 'country' => '',
		]);
		$this->service = new XRechnungService($this->invoiceService, $this->companyProfileService, $this->contactsService, $this->createMock(IRootFolder::class));
		$full = $this->fullInvoice([$this->position(1.0, 10.0, 19.0)]);
		$this->invoiceService->method('getFullInvoice')->willReturn($full);

		$this->expectException(\DomainException::class);
		$this->service->generateXml(1);
	}

	public function testGenerateXmlRejectsIncompleteCompanyProfile(): void {
		$this->companyProfileService = $this->createMock(CompanyProfileService::class);
		$this->companyProfileService->method('get')->willReturn(new CompanyProfile());
		$this->companyProfileService->method('missingMandatoryFields')->willReturn(['Name/Firma']);
		$this->service = new XRechnungService($this->invoiceService, $this->companyProfileService, $this->contactsService, $this->createMock(IRootFolder::class));
		$full = $this->fullInvoice([$this->position(1.0, 10.0, 19.0)]);
		$this->invoiceService->method('getFullInvoice')->willReturn($full);

		$this->expectException(\DomainException::class);
		$this->service->generateXml(1);
	}

	public function testGenerateZugferdPdfRejectsInvoiceWithoutStoredPdf(): void {
		$full = $this->fullInvoice([$this->position(1.0, 10.0, 19.0)], ['documentFileId' => null]);
		$this->invoiceService->method('getFullInvoice')->willReturn($full);

		$this->expectException(\DomainException::class);
		$this->service->generateZugferdPdf(1, $this->createMock(\OCP\IUser::class));
	}
}
