<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\CompanyProfileMapper;
use OCA\ERP\Db\ContactLinkMapper;
use OCA\ERP\Db\DeliveryNoteGroupMapper;
use OCA\ERP\Db\DeliveryNoteMapper;
use OCA\ERP\Db\DeliveryNotePositionMapper;
use OCA\ERP\Db\InvoiceDunningStepMapper;
use OCA\ERP\Db\InvoiceGroupMapper;
use OCA\ERP\Db\InvoiceMapper;
use OCA\ERP\Db\InvoicePaymentMapper;
use OCA\ERP\Db\InvoicePositionMapper;
use OCA\ERP\Db\OrderGroupMapper;
use OCA\ERP\Db\OrderMapper;
use OCA\ERP\Db\OrderPositionMapper;
use OCA\ERP\Db\ProjectMapper;
use OCA\ERP\Db\QuoteGroupMapper;
use OCA\ERP\Db\QuoteMapper;
use OCA\ERP\Db\QuotePositionMapper;
use OCA\ERP\Service\CompanyProfileService;
use OCA\ERP\Service\ContactsService;
use OCA\ERP\Service\DatevExportService;
use OCA\ERP\Service\DocumentHtmlBuilder;
use OCA\ERP\Service\DocumentPdfService;
use OCA\ERP\Service\ErpFolderService;
use OCA\ERP\Service\InvoiceService;
use OCA\ERP\Service\ProjectService;
use OCA\ERP\Tests\Unit\Support\ErpIntegrationTestCase;
use OCA\ERP\Tests\Unit\Support\ErpTestGroupTrait;
use OCP\Contacts\IManager as IContactsManager;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;

/**
 * @group DB
 */
final class DatevExportServiceTest extends ErpIntegrationTestCase {
	use ErpTestGroupTrait;

	private const TEST_UID = 'phpunit-datev-user';

	private InvoiceService $invoiceService;
	private InvoiceMapper $invoiceMapper;
	private InvoicePositionMapper $positionMapper;
	private InvoiceGroupMapper $groupMapper;
	private DatevExportService $service;
	private IUser $user;
	private int $projectId;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->invoiceMapper = new InvoiceMapper($db);
		$this->positionMapper = new InvoicePositionMapper($db);
		$this->groupMapper = new InvoiceGroupMapper($db);
		$folderService = new ErpFolderService(\OC::$server->get(IRootFolder::class));
		$projectMapper = new ProjectMapper($db);
		$projectService = new ProjectService($projectMapper, $folderService);
		$pdfService = new DocumentPdfService();
		$htmlBuilder = new DocumentHtmlBuilder(
			new CompanyProfileService(new CompanyProfileMapper($db)),
			new ContactsService(new ContactLinkMapper($db), \OC::$server->get(IContactsManager::class)),
		);

		$this->invoiceService = new InvoiceService(
			$this->invoiceMapper,
			$this->positionMapper,
			$this->groupMapper,
			new QuoteMapper($db),
			new QuotePositionMapper($db),
			new QuoteGroupMapper($db),
			new OrderMapper($db),
			new OrderPositionMapper($db),
			new OrderGroupMapper($db),
			new DeliveryNoteMapper($db),
			new DeliveryNotePositionMapper($db),
			new DeliveryNoteGroupMapper($db),
			$db,
			$folderService,
			$projectService,
			$pdfService,
			$htmlBuilder,
			new InvoicePaymentMapper($db),
			new InvoiceDunningStepMapper($db),
		);
		$this->service = new DatevExportService($this->invoiceService);

		$userManager = \OC::$server->get(IUserManager::class);
		if ($userManager->userExists(self::TEST_UID)) {
			$userManager->get(self::TEST_UID)->delete();
		}
		$this->user = $userManager->createUser(self::TEST_UID, 'Phpunit-Test-Pass-1!');
		$this->addToErpGroup($this->user);
		self::loginAsUser(self::TEST_UID);

		$project = $projectService->createProject($this->user, 'phpunit-datev-project', null, null, null);
		$this->projectId = $project->getId();
	}

	protected function tearDown(): void {
		foreach ($this->invoiceMapper->findAll() as $invoice) {
			if (str_starts_with($invoice->getTitle(), 'phpunit-datev')) {
				foreach ($this->positionMapper->findByInvoice($invoice->getId()) as $p) {
					$this->positionMapper->delete($p);
				}
				$this->invoiceMapper->delete($invoice);
			}
		}
		$userManager = \OC::$server->get(IUserManager::class);
		if ($userManager->userExists(self::TEST_UID)) {
			$userManager->get(self::TEST_UID)->delete();
		}
		parent::tearDown();
	}

	private function issuedInvoice(string $title = 'phpunit-datev-1', float $unitPriceNet = 100.0, float $vat = 19.0): \OCA\ERP\Db\Invoice {
		$invoice = $this->invoiceService->createDraft($title, 'invoice', $this->projectId, null, 'phpunit-datev-customer', null, null);
		$this->invoiceService->addPosition($invoice->getId(), null, 'custom', null, 'Testposition', 1.0, 'Stk', $unitPriceNet, $vat);
		return $this->invoiceService->issue($invoice->getId(), $this->user);
	}

	public function testRejectsFromAfterTo(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->exportBuchungsstapel('2026-02-01', '2026-01-01', 1001, 1, self::TEST_UID);
	}

	public function testRejectsInvalidDateFormat(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->exportBuchungsstapel('01.01.2026', '2026-01-31', 1001, 1, self::TEST_UID);
	}

	public function testRejectsBeraternummerOutOfRange(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->exportBuchungsstapel('2026-01-01', '2026-12-31', 0, 1, self::TEST_UID);
	}

	public function testRejectsMandantennummerOutOfRange(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->exportBuchungsstapel('2026-01-01', '2026-12-31', 1001, 100000, self::TEST_UID);
	}

	public function testHeaderRowContainsExtfMetadata(): void {
		$csv = $this->service->exportBuchungsstapel('2026-01-01', '2026-12-31', 1001, 42, self::TEST_UID);
		$lines = explode("\r\n", $csv);
		// BOM steht vor der ersten Zeile.
		$this->assertStringStartsWith("\u{FEFF}\"EXTF\"", $lines[0]);
		$this->assertStringContainsString('"Buchungsstapel"', $lines[0]);
		$this->assertStringContainsString(';1001;42;', $lines[0]);
		$this->assertStringContainsString('"EUR"', $lines[0]);
	}

	public function testColumnHeaderRowHas125ColumnsWithExpectedKeyNames(): void {
		$csv = $this->service->exportBuchungsstapel('2026-01-01', '2026-12-31', 1001, 1, self::TEST_UID);
		$lines = explode("\r\n", $csv);
		$columns = str_getcsv($lines[1], ';', '"', '\\');
		$this->assertCount(125, $columns);
		$this->assertSame('Umsatz (ohne Soll/Haben-Kz)', $columns[0]);
		$this->assertSame('Soll/Haben-Kennzeichen', $columns[1]);
		$this->assertSame('Konto', $columns[6]);
		$this->assertSame('Gegenkonto (ohne BU-Schlüssel)', $columns[7]);
		$this->assertSame('Belegdatum', $columns[9]);
		$this->assertSame('Belegfeld 1', $columns[10]);
		$this->assertSame('Buchungstext', $columns[13]);
	}

	public function testIssuedInvoiceProducesBookingRowWithStandardRevenueAccount(): void {
		$invoice = $this->issuedInvoice('phpunit-datev-std', 100.0, 19.0);

		$csv = $this->service->exportBuchungsstapel('2026-01-01', '2026-12-31', 1001, 1, self::TEST_UID);
		$row = $this->findRowFor($csv, $invoice->getInvoiceNumber());

		$this->assertNotNull($row);
		$this->assertSame('119,00', $row[0]); // Umsatz = Brutto
		$this->assertSame('S', $row[1]);
		$this->assertSame('10000', $row[6]); // Konto = Debitoren-Sammelkonto (Default)
		$this->assertSame('8400', $row[7]); // Gegenkonto = SKR03 Erlöse 19% USt
		$this->assertSame($invoice->getInvoiceNumber(), $row[10]);
	}

	public function testNonStandardVatRateUsesFallbackAccountAndMarksForReview(): void {
		$invoice = $this->issuedInvoice('phpunit-datev-exotic', 100.0, 12.5);

		$csv = $this->service->exportBuchungsstapel('2026-01-01', '2026-12-31', 1001, 1, self::TEST_UID);
		$row = $this->findRowFor($csv, $invoice->getInvoiceNumber());

		$this->assertNotNull($row);
		$this->assertSame('8400', $row[7]); // Fallback-Konto
		$this->assertStringContainsString('PRÜFEN 12.5%', $row[13]);
	}

	public function testDraftAndCancelledInvoicesAreExcluded(): void {
		$draft = $this->invoiceService->createDraft('phpunit-datev-draft', 'invoice', $this->projectId, null, null, null, null);
		$this->invoiceService->addPosition($draft->getId(), null, 'custom', null, 'x', 1.0, 'Stk', 10.0, 19.0);

		$cancelled = $this->issuedInvoice('phpunit-datev-cancelled', 50.0, 19.0);
		$this->invoiceService->markCancelled($cancelled->getId());

		$csv = $this->service->exportBuchungsstapel('2026-01-01', '2026-12-31', 1001, 1, self::TEST_UID);

		$this->assertStringNotContainsString('phpunit-datev-draft', $csv);
		$this->assertNull($this->findRowFor($csv, $cancelled->getInvoiceNumber()));
	}

	public function testDateRangeFiltering(): void {
		$invoice = $this->issuedInvoice('phpunit-datev-old', 50.0, 19.0);
		$futureFrom = date('Y-m-d', strtotime('+30 days'));

		$csv = $this->service->exportBuchungsstapel($futureFrom, date('Y-m-d', strtotime('+60 days')), 1001, 1, self::TEST_UID);

		$this->assertNull($this->findRowFor($csv, $invoice->getInvoiceNumber()));
	}

	public function testCustomDebtorAccountIsUsed(): void {
		$invoice = $this->issuedInvoice('phpunit-datev-custom-acct', 100.0, 19.0);

		$csv = $this->service->exportBuchungsstapel('2026-01-01', '2026-12-31', 1001, 1, self::TEST_UID, 12000);
		$row = $this->findRowFor($csv, $invoice->getInvoiceNumber());

		$this->assertNotNull($row);
		$this->assertSame('12000', $row[6]);
	}

	/** @return list<string>|null */
	private function findRowFor(string $csv, ?string $invoiceNumber): ?array {
		foreach (explode("\r\n", $csv) as $i => $line) {
			if ($i < 2 || $line === '') {
				continue; // Zeile 0 = Kopf, Zeile 1 = Spaltennamen.
			}
			$columns = str_getcsv($line, ';', '"', '\\');
			if (($columns[10] ?? null) === $invoiceNumber) {
				return $columns;
			}
		}
		return null;
	}
}
