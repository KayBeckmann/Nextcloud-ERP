<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\InvoiceMapper;
use OCA\ERP\Db\InvoicePositionMapper;
use OCA\ERP\Db\ProjectMapper;
use OCA\ERP\Db\QuoteGroupMapper;
use OCA\ERP\Db\QuoteMapper;
use OCA\ERP\Db\QuotePositionMapper;
use OCA\ERP\Service\ErpFolderService;
use OCA\ERP\Service\InvoiceService;
use OCA\ERP\Service\ProjectService;
use OCA\ERP\Service\QuoteService;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use Test\TestCase;

/**
 * @group DB
 */
final class InvoiceServiceTest extends TestCase {
	private const TEST_UID = 'phpunit-invoice-user';

	private InvoiceService $service;
	private InvoiceMapper $mapper;
	private InvoicePositionMapper $positionMapper;
	private QuoteService $quoteService;
	private ProjectMapper $projectMapper;
	private ErpFolderService $folderService;
	private IUser $user;
	private int $projectId;
	private string $projectNumber;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->mapper = new InvoiceMapper($db);
		$this->positionMapper = new InvoicePositionMapper($db);
		$quoteMapper = new QuoteMapper($db);
		$quotePositionMapper = new QuotePositionMapper($db);
		$this->quoteService = new QuoteService($quoteMapper, new QuoteGroupMapper($db), $quotePositionMapper);
		$this->folderService = new ErpFolderService(\OC::$server->get(IRootFolder::class));
		$this->projectMapper = new ProjectMapper($db);
		$projectService = new ProjectService($this->projectMapper, $this->folderService);

		$this->service = new InvoiceService($this->mapper, $this->positionMapper, $quoteMapper, $quotePositionMapper, $db, $this->folderService, $projectService);

		$userManager = \OC::$server->get(IUserManager::class);
		if ($userManager->userExists(self::TEST_UID)) {
			$userManager->get(self::TEST_UID)->delete();
		}
		$this->user = $userManager->createUser(self::TEST_UID, 'Phpunit-Test-Pass-1!');
		self::loginAsUser(self::TEST_UID);

		$project = $projectService->createProject($this->user, 'phpunit-invoice-project', null, null, null);
		$this->projectId = $project->getId();
		$this->projectNumber = $project->getProjectNumber();
	}

	protected function tearDown(): void {
		foreach ($this->mapper->findAll() as $invoice) {
			if (str_starts_with($invoice->getTitle(), 'phpunit-')) {
				foreach ($this->positionMapper->findByInvoice($invoice->getId()) as $p) {
					$this->positionMapper->delete($p);
				}
				$this->mapper->delete($invoice);
			}
		}
		$this->projectMapper->delete($this->projectMapper->findById($this->projectId));
		self::logout();
		$this->user->delete();
		parent::tearDown();
	}

	private function draftWithOnePosition(float $unitPriceNet = 100.0, float $vat = 19.0): \OCA\ERP\Db\Invoice {
		$invoice = $this->service->createDraft('phpunit-invoice-1', 'invoice', $this->projectId, null, 'cust-1', null, null);
		$this->service->addPosition($invoice->getId(), 'custom', null, 'Testposition', 1.0, 'Stk', $unitPriceNet, $vat);
		return $invoice;
	}

	public function testCreateDraftHasNoInvoiceNumberYet(): void {
		$invoice = $this->service->createDraft('phpunit-invoice-2', 'invoice', $this->projectId, null, null, null, null);
		$this->assertNull($invoice->getInvoiceNumber());
		$this->assertSame('draft', $invoice->getStatus());
	}

	/** ADR-0015: Rechnungen hängen zwingend an einem Projekt. */
	public function testCreateDraftWithoutProjectThrows(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->createDraft('phpunit-invoice-no-project', 'invoice', 0, null, null, null, null);
	}

	public function testIssueAssignsSequentialNumberInCurrentYear(): void {
		$invoice = $this->draftWithOnePosition();
		$issued = $this->service->issue($invoice->getId(), $this->user);

		$year = (int) date('Y');
		$this->assertMatchesRegularExpression("/^R-$year-\\d{5}$/", $issued->getInvoiceNumber());
		$this->assertSame('issued', $issued->getStatus());
		$this->assertNotNull($issued->getIssuedAt());
	}

	public function testIssueWithoutPositionsThrows(): void {
		$invoice = $this->service->createDraft('phpunit-invoice-3', 'invoice', $this->projectId, null, null, null, null);
		$this->expectException(\DomainException::class);
		$this->service->issue($invoice->getId(), $this->user);
	}

	public function testIssueTwiceThrows(): void {
		$invoice = $this->draftWithOnePosition();
		$this->service->issue($invoice->getId(), $this->user);

		$this->expectException(\DomainException::class);
		$this->service->issue($invoice->getId(), $this->user);
	}

	public function testAddPositionAfterIssueThrows(): void {
		$invoice = $this->draftWithOnePosition();
		$this->service->issue($invoice->getId(), $this->user);

		$this->expectException(\DomainException::class);
		$this->service->addPosition($invoice->getId(), 'custom', null, 'x', 1.0, 'Stk', 1.0, 19.0);
	}

	/**
	 * Regressionstest analog zu QuotePosition (ADR-0011/Phase 5):
	 * 'custom' ist zufällig der PHP-Default von InvoicePosition::$positionType.
	 */
	public function testCustomPositionTypeIsPersistedCorrectly(): void {
		$invoice = $this->service->createDraft('phpunit-invoice-4', 'invoice', $this->projectId, null, null, null, null);
		$position = $this->service->addPosition($invoice->getId(), 'custom', null, 'Anfahrt', 1.0, 'psch.', 25.0, 19.0);

		$this->assertSame('custom', $position->getPositionType());
		$reloaded = (new InvoicePositionMapper(\OC::$server->get(IDBConnection::class)))
			->findOne($invoice->getId(), $position->getId());
		$this->assertNotNull($reloaded);
		$this->assertSame('custom', $reloaded->getPositionType());
	}

	public function testCreateFromQuoteCopiesPositions(): void {
		$quote = $this->quoteService->createQuote('phpunit-quote-for-invoice', $this->projectId, 'cust-2', null);
		$this->quoteService->addPosition($quote->getId(), null, 'labor', null, 'Arbeitsstunden', 3.0, 'Std', 55.0, 19.0);

		$invoice = $this->service->createFromQuote($quote->getId(), 'phpunit-invoice-5', 'invoice', null, null);
		$full = $this->service->getFullInvoice($invoice->getId());

		$this->assertCount(1, $full['positions']);
		$this->assertSame('labor', $full['positions'][0]->getPositionType());
		$this->assertSame($quote->getId(), $invoice->getQuoteId());
		$this->assertSame('cust-2', $invoice->getCustomerContactUid());
		$this->assertSame($this->projectId, $invoice->getProjectId());
	}

	public function testRecordPaymentTransitionsToPartiallyPaidThenPaid(): void {
		$invoice = $this->draftWithOnePosition(100.0, 19.0); // gross = 119.00
		$issued = $this->service->issue($invoice->getId(), $this->user);

		$partial = $this->service->recordPayment($issued->getId(), 50.0);
		$this->assertSame('partially_paid', $partial->getStatus());
		$this->assertSame(50.0, $partial->getPaidAmount());

		$paid = $this->service->recordPayment($issued->getId(), 69.0);
		$this->assertSame('paid', $paid->getStatus());
		$this->assertSame(119.0, $paid->getPaidAmount());
	}

	public function testRecordPaymentBeforeIssueThrows(): void {
		$invoice = $this->draftWithOnePosition();
		$this->expectException(\DomainException::class);
		$this->service->recordPayment($invoice->getId(), 10.0);
	}

	public function testGetFullInvoiceCalculatesGrossTotal(): void {
		$invoice = $this->draftWithOnePosition(100.0, 19.0);
		$full = $this->service->getFullInvoice($invoice->getId());
		$this->assertSame(100.0, $full['calculation']['netSubtotal']);
		$this->assertSame(119.0, $full['calculation']['grossTotal']);
	}

	public function testIssueWritesDocumentToProjectFolder(): void {
		$invoice = $this->draftWithOnePosition();
		$issued = $this->service->issue($invoice->getId(), $this->user);

		$this->assertNotNull($issued->getDocumentFileId());

		$invoiceFolder = $this->folderService->ensureInvoiceFolder($this->user, $this->projectNumber);
		$this->assertTrue($invoiceFolder->nodeExists($issued->getInvoiceNumber() . '.html'));
	}
}
