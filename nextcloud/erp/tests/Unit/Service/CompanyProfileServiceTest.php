<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\CompanyProfileMapper;
use OCA\ERP\Service\CompanyProfileService;
use OCP\IDBConnection;
use Test\TestCase;

/**
 * @group DB
 *
 * `erp_company_profile` ist ein Singleton (keine WHERE-Klausel in
 * CompanyProfileMapper::find()) — dieser Test sichert die zum Testlauf
 * tatsächlich vorhandene Zeile und stellt sie in tearDown() exakt wieder
 * her, statt sie zu löschen/zu überschreiben.
 */
final class CompanyProfileServiceTest extends TestCase {
	private CompanyProfileService $service;
	private CompanyProfileMapper $mapper;
	private ?array $originalProfile = null;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->mapper = new CompanyProfileMapper($db);
		$this->service = new CompanyProfileService($this->mapper);

		$existing = $this->mapper->find();
		$this->originalProfile = $existing?->jsonSerialize();
	}

	protected function tearDown(): void {
		if ($this->originalProfile !== null) {
			$p = $this->originalProfile;
			$this->service->update(
				$p['name'], $p['addressLine'], $p['postalCode'], $p['city'], $p['country'], $p['taxId'],
				$p['email'], $p['phone'], $p['footerText'], $p['headerText'], $p['legalForm'],
				$p['managingDirector'], $p['commercialRegister'], $p['vatId'], $p['taxNumber'],
				$p['bankName'], $p['iban'], $p['bic'],
			);
		} else {
			$restored = $this->mapper->find();
			if ($restored !== null) {
				$this->mapper->delete($restored);
			}
		}
		parent::tearDown();
	}

	public function testMissingMandatoryFieldsListsEverythingOnEmptyProfile(): void {
		$this->service->update(null, null, null, null, null, null, null, null, null);

		$missing = $this->service->missingMandatoryFields();

		$this->assertContains('Name/Firma', $missing);
		$this->assertContains('Anschrift', $missing);
		$this->assertContains('PLZ/Ort', $missing);
		$this->assertContains('Steuernummer oder USt-IdNr.', $missing);
	}

	public function testMissingMandatoryFieldsIsEmptyWhenFullyFilled(): void {
		$this->service->update(
			'phpunit-company',
			'Musterstr. 1',
			'12345',
			'Musterstadt',
			'Deutschland',
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			'DE123456789',
			null,
			null,
			null,
			null,
		);

		$this->assertSame([], $this->service->missingMandatoryFields());
	}

	public function testTaxNumberAloneSatisfiesTheTaxFieldRequirement(): void {
		$this->service->update(
			'phpunit-company', 'Musterstr. 1', '12345', 'Musterstadt', 'Deutschland',
			null, null, null, null, null, null, null, null,
			null, '123/456/78901', null, null, null,
		);

		$this->assertSame([], $this->service->missingMandatoryFields());
	}

	public function testMissingOnlyTaxFieldReportsOnlyThat(): void {
		$this->service->update(
			'phpunit-company', 'Musterstr. 1', '12345', 'Musterstadt', 'Deutschland',
			null, null, null, null, null, null, null, null,
			null, null, null, null, null,
		);

		$this->assertSame(['Steuernummer oder USt-IdNr.'], $this->service->missingMandatoryFields());
	}
}
