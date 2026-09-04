<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\ArticleSupplierPriceMapper;
use OCA\ERP\Db\CompanyProfileMapper;
use OCA\ERP\Db\ContactLinkMapper;
use OCA\ERP\Db\DeliveryNoteGroupMapper;
use OCA\ERP\Db\DeliveryNoteMapper;
use OCA\ERP\Db\DeliveryNotePositionMapper;
use OCA\ERP\Db\InvoiceGroupMapper;
use OCA\ERP\Db\InvoiceMapper;
use OCA\ERP\Db\InvoicePositionMapper;
use OCA\ERP\Db\OrderGroupMapper;
use OCA\ERP\Db\OrderMapper;
use OCA\ERP\Db\OrderPositionMapper;
use OCA\ERP\Db\OvertimeActionMapper;
use OCA\ERP\Db\ProjectMapper;
use OCA\ERP\Db\QuoteGroupMapper;
use OCA\ERP\Db\QuoteMapper;
use OCA\ERP\Db\QuotePositionMapper;
use OCA\ERP\Db\StockLevelMapper;
use OCA\ERP\Db\StockMovementMapper;
use OCA\ERP\Db\TimeEntry;
use OCA\ERP\Db\TimeEntryMapper;
use OCA\ERP\Db\VehicleFuelLogMapper;
use OCA\ERP\Db\VehicleMapper;
use OCA\ERP\Db\WarehouseMapper;
use OCA\ERP\Service\AbsenceRequestService;
use OCA\ERP\Service\ArticleService;
use OCA\ERP\Service\CalendarProvisioningService;
use OCA\ERP\Service\CalendarService;
use OCA\ERP\Service\CompanyProfileService;
use OCA\ERP\Service\ContactsService;
use OCA\ERP\Service\CostService;
use OCA\ERP\Service\DocumentHtmlBuilder;
use OCA\ERP\Service\DocumentPdfService;
use OCA\ERP\Service\ErpFolderService;
use OCA\ERP\Service\InvoiceService;
use OCA\ERP\Service\OrderService;
use OCA\ERP\Service\OvertimeActionService;
use OCA\ERP\Service\ProjectService;
use OCA\ERP\Service\PurchaseSuggestionService;
use OCA\ERP\Service\QuoteService;
use OCA\ERP\Service\ReportingService;
use OCA\ERP\Service\StockService;
use OCA\ERP\Service\TimeAccountService;
use OCA\ERP\Service\VehicleService;
use OCA\ERP\Service\WorkScheduleService;
use OCP\Calendar\IManager as ICalendarManager;
use OCP\Contacts\IManager as IContactsManager;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use OCA\ERP\Tests\Unit\Support\ErpTestGroupTrait;
use Test\TestCase;

/**
 * @group DB
 */
final class ReportingServiceTest extends TestCase {
	use ErpTestGroupTrait;

	private const TEST_UID = 'phpunit-reporting-user';

	private ReportingService $service;
	private QuoteService $quoteService;
	private OrderService $orderService;
	private InvoiceService $invoiceService;
	private ProjectService $projectService;
	private ArticleService $articleService;
	private TimeEntryMapper $timeEntryMapper;
	private ProjectMapper $projectMapper;
	private QuoteMapper $quoteMapper;
	private InvoiceMapper $invoiceMapper;
	private IUser $user;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);

		$folderService = new ErpFolderService(\OC::$server->get(IRootFolder::class));
		$this->projectMapper = new ProjectMapper($db);
		$this->projectService = new ProjectService($this->projectMapper, $folderService);
		$pdfService = new DocumentPdfService();
		$htmlBuilder = new DocumentHtmlBuilder(
			new CompanyProfileService(new CompanyProfileMapper($db)),
			new ContactsService(new ContactLinkMapper($db), \OC::$server->get(IContactsManager::class)),
		);

		$this->quoteMapper = new QuoteMapper($db);
		$quotePositionMapper = new QuotePositionMapper($db);
		$quoteGroupMapper = new QuoteGroupMapper($db);
		$this->quoteService = new QuoteService($this->quoteMapper, $quoteGroupMapper, $quotePositionMapper, $folderService, $this->projectService, $pdfService, $htmlBuilder);

		$orderMapper = new OrderMapper($db);
		$orderPositionMapper = new OrderPositionMapper($db);
		$orderGroupMapper = new OrderGroupMapper($db);
		$invoicePositionMapper = new InvoicePositionMapper($db);
		$deliveryNotePositionMapper = new DeliveryNotePositionMapper($db);
		$this->orderService = new OrderService(
			$orderMapper,
			$orderPositionMapper,
			$orderGroupMapper,
			$this->quoteMapper,
			$quotePositionMapper,
			$quoteGroupMapper,
			$invoicePositionMapper,
			$deliveryNotePositionMapper,
			$folderService,
			$this->projectService,
			$pdfService,
			$htmlBuilder,
		);

		$this->invoiceMapper = new InvoiceMapper($db);
		$invoiceGroupMapper = new InvoiceGroupMapper($db);
		$this->invoiceService = new InvoiceService(
			$this->invoiceMapper,
			$invoicePositionMapper,
			$invoiceGroupMapper,
			$this->quoteMapper,
			$quotePositionMapper,
			$quoteGroupMapper,
			$orderMapper,
			$orderPositionMapper,
			$orderGroupMapper,
			new DeliveryNoteMapper($db),
			$deliveryNotePositionMapper,
			new DeliveryNoteGroupMapper($db),
			$db,
			$folderService,
			$this->projectService,
			$pdfService,
			$htmlBuilder,
		);

		$stockService = new StockService(new StockLevelMapper($db), new StockMovementMapper($db));
		$articleSupplierPriceMapper = new ArticleSupplierPriceMapper($db);
		$this->articleService = new ArticleService(new \OCA\ERP\Db\ArticleMapper($db), $articleSupplierPriceMapper);
		$purchaseSuggestionService = new PurchaseSuggestionService(
			new StockLevelMapper($db),
			new \OCA\ERP\Db\ArticleMapper($db),
			$articleSupplierPriceMapper,
			new WarehouseMapper($db),
		);

		$vehicleService = new VehicleService(new VehicleMapper($db), new VehicleFuelLogMapper($db), new WarehouseMapper($db), $folderService);

		$costService = new CostService(new \OCA\ERP\Db\CostEntryMapper($db), new \OCA\ERP\Db\CostSettingsMapper($db));

		$this->timeEntryMapper = new TimeEntryMapper($db);
		$timeAccountService = new TimeAccountService(new WorkScheduleService(new \OCA\ERP\Db\WorkScheduleMapper($db)), $this->timeEntryMapper);

		$calendarService = new CalendarService(
			new \OCA\ERP\Db\CalendarLinkMapper($db),
			\OC::$server->get(ICalendarManager::class),
			\OC::$server->get(CalendarProvisioningService::class),
		);
		$absenceRequestService = new AbsenceRequestService(
			new \OCA\ERP\Db\AbsenceRequestMapper($db),
			new \OCA\ERP\Db\AbsenceTypeMapper($db),
			$calendarService,
			\OC::$server->get(IUserManager::class),
		);
		$overtimeActionService = new OvertimeActionService(new OvertimeActionMapper($db));

		$this->service = new ReportingService(
			$this->quoteService,
			$this->orderService,
			$this->invoiceService,
			$this->projectService,
			$stockService,
			$purchaseSuggestionService,
			$vehicleService,
			new VehicleFuelLogMapper($db),
			$costService,
			$timeAccountService,
			$this->timeEntryMapper,
			$articleSupplierPriceMapper,
			$absenceRequestService,
			$overtimeActionService,
		);

		$userManager = \OC::$server->get(IUserManager::class);
		if ($userManager->userExists(self::TEST_UID)) {
			$userManager->get(self::TEST_UID)->delete();
		}
		$this->user = $userManager->createUser(self::TEST_UID, 'Phpunit-Test-Pass-1!');
		$this->addToErpGroup($this->user);
		self::loginAsUser(self::TEST_UID);
	}

	protected function tearDown(): void {
		foreach ($this->projectMapper->findAll() as $project) {
			if (str_starts_with($project->getTitle(), 'phpunit-reporting-')) {
				$this->projectMapper->delete($project);
			}
		}
		$userManager = \OC::$server->get(IUserManager::class);
		if ($userManager->userExists(self::TEST_UID)) {
			$this->removeFromErpGroup($this->user);
			$userManager->get(self::TEST_UID)->delete();
		}
		parent::tearDown();
	}

	private function createProject(string $suffix): int {
		return $this->projectService->createProject($this->user, 'phpunit-reporting-' . $suffix, null, null, null)->getId();
	}

	public function testDashboardSummaryCountsIncreaseWithOpenQuoteAndInvoice(): void {
		$before = $this->service->dashboardSummary(self::TEST_UID);

		$projectId = $this->createProject('dashboard');
		$quote = $this->quoteService->createQuote('phpunit-reporting-quote', $projectId, null, null);
		$this->quoteService->updateQuote($quote->getId(), $quote->getTitle(), 'sent', $projectId, null, null, null);

		$invoice = $this->invoiceService->createDraft('phpunit-reporting-invoice', 'invoice', $projectId, null, null, null, null);
		$this->invoiceService->addPosition($invoice->getId(), null, 'custom', null, 'Testposition', 1.0, 'Stk', 100.0, 19.0);
		$this->invoiceService->issue($invoice->getId(), $this->user);

		$after = $this->service->dashboardSummary(self::TEST_UID);

		$this->assertSame($before['openQuotes']['count'] + 1, $after['openQuotes']['count']);
		$this->assertSame($before['openInvoices']['count'] + 1, $after['openInvoices']['count']);
		$this->assertEqualsWithDelta($before['openInvoices']['grossTotal'] + 119.0, $after['openInvoices']['grossTotal'], 0.01);
	}

	public function testDashboardSummaryIncludesOwnTimeAccountAndInternalHourlyRate(): void {
		$summary = $this->service->dashboardSummary(self::TEST_UID);

		$this->assertSame(self::TEST_UID, $summary['timeAccount']['userId']);
		$this->assertIsFloat($summary['internalHourlyRate']);
		$this->assertIsInt($summary['ownPendingRequests']);
	}

	public function testProjectProfitLossHasNullSollWithoutOrderOrQuote(): void {
		$projectId = $this->createProject('no-order');
		$result = $this->service->projectProfitLoss($projectId);

		$this->assertNull($result['sollNet']);
		$this->assertSame(0.0, $result['invoicedNet']);
		$this->assertSame(0.0, $result['result']);
	}

	public function testProjectProfitLossThrowsForUnknownProject(): void {
		$this->expectException(\OutOfBoundsException::class);
		$this->service->projectProfitLoss(999999999);
	}

	public function testProjectProfitLossUsesOrderNetTotalAsSoll(): void {
		$projectId = $this->createProject('order-soll');
		$order = $this->orderService->createOrder($projectId, 'phpunit-reporting-order', null);
		$this->orderService->addPosition($order->getId(), null, 'custom', null, 'Testposition', 2.0, 'Stk', 50.0, 19.0);

		$result = $this->service->projectProfitLoss($projectId);
		$this->assertSame(100.0, $result['sollNet']);
	}

	public function testProjectProfitLossComputesMaterialCostFromCheapestSupplierPrice(): void {
		$projectId = $this->createProject('material-cost');
		$article = $this->articleService->create('phpunit-reporting-article', null, null, 'Stk', null, null, null);
		$this->articleService->addSupplierPrice($article->getId(), 'supplier-a', null, 30.0, 'EUR', null, null);
		$this->articleService->addSupplierPrice($article->getId(), 'supplier-b', null, 20.0, 'EUR', null, null);

		$invoice = $this->invoiceService->createDraft('phpunit-reporting-material-invoice', 'invoice', $projectId, null, null, null, null);
		$this->invoiceService->addPosition($invoice->getId(), null, 'article', $article->getId(), 'phpunit-reporting-article', 3.0, 'Stk', 40.0, 19.0);
		$this->invoiceService->issue($invoice->getId(), $this->user);

		$result = $this->service->projectProfitLoss($projectId);

		// Ist-Umsatz: 3 × 40 € = 120 € netto.
		$this->assertSame(120.0, $result['invoicedNet']);
		// Materialkosten: 3 × günstigster Einkaufspreis (20 €) = 60 €.
		$this->assertSame(60.0, $result['materialCost']);
		$this->assertSame(60.0, $result['result']);
	}

	public function testProjectProfitLossComputesLaborCostFromTimeEntries(): void {
		$projectId = $this->createProject('labor-cost');
		$entry = new TimeEntry();
		$entry->setUserId(self::TEST_UID);
		$entry->setProjectId($projectId);
		$entry->setWorkTypeId(1);
		$entry->setEntryDate(date('Y-m-d'));
		$entry->setDurationMinutes(120);
		$entry->setBreakMinutes(0);
		$entry->setBillable(true);
		$entry->setRateSnapshot(0.0);
		$entry->setCreatedAt(time());
		$entry->setUpdatedAt(time());
		$this->timeEntryMapper->insert($entry);

		$result = $this->service->projectProfitLoss($projectId);
		// 2h Zeiterfassung → laborCost muss > 0 sein (interner Stundensatz
		// hängt vom aktuellen CostSettings-Stand ab, siehe ADR-0018 Default).
		$this->assertGreaterThan(0.0, $result['laborCost']);

		$this->timeEntryMapper->delete($entry);
	}

	public function testExportInvoicesCsvContainsIssuedInvoiceButNotDraft(): void {
		$projectId = $this->createProject('csv-export');

		$draft = $this->invoiceService->createDraft('phpunit-reporting-csv-draft', 'invoice', $projectId, null, null, null, null);
		$this->invoiceService->addPosition($draft->getId(), null, 'custom', null, 'Entwurf', 1.0, 'Stk', 10.0, 19.0);

		$issued = $this->invoiceService->createDraft('phpunit-reporting-csv-issued', 'invoice', $projectId, null, null, null, null);
		$this->invoiceService->addPosition($issued->getId(), null, 'custom', null, 'Ausgestellt', 1.0, 'Stk', 200.0, 19.0);
		$issued = $this->invoiceService->issue($issued->getId(), $this->user);

		$csv = $this->service->exportInvoicesCsv(null, null, null);

		$this->assertStringContainsString($issued->getInvoiceNumber(), $csv);
		$this->assertStringContainsString('238,00', $csv);
		$this->assertStringNotContainsString('phpunit-reporting-csv-draft', $csv);
	}

	public function testExportInvoicesCsvFiltersByDateRange(): void {
		$projectId = $this->createProject('csv-date-filter');
		$invoice = $this->invoiceService->createDraft('phpunit-reporting-csv-old', 'invoice', $projectId, null, null, null, null);
		$this->invoiceService->addPosition($invoice->getId(), null, 'custom', null, 'x', 1.0, 'Stk', 10.0, 19.0);
		$invoice = $this->invoiceService->issue($invoice->getId(), $this->user);

		$futureFrom = date('Y-m-d', strtotime('+10 days'));
		$csv = $this->service->exportInvoicesCsv($futureFrom, null, null);

		$this->assertStringNotContainsString($invoice->getInvoiceNumber(), $csv);
	}
}
