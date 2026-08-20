<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\CreditNoteMapper;
use OCA\ERP\Db\CreditNotePositionMapper;
use OCA\ERP\Db\InvoiceMapper;
use OCA\ERP\Db\InvoicePositionMapper;
use OCA\ERP\Db\QuoteGroupMapper;
use OCA\ERP\Db\QuoteMapper;
use OCA\ERP\Db\QuotePositionMapper;
use OCA\ERP\Service\CreditNoteService;
use OCA\ERP\Service\ErpFolderService;
use OCA\ERP\Service\InvoiceService;
use OCA\ERP\Service\ProjectService;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use Test\TestCase;

/**
 * @group DB
 */
final class CreditNoteServiceTest extends TestCase {
	private const TEST_UID = 'phpunit-creditnote-user';

	private CreditNoteService $service;
	private InvoiceService $invoiceService;
	private CreditNoteMapper $mapper;
	private CreditNotePositionMapper $positionMapper;
	private InvoiceMapper $invoiceMapper;
	private InvoicePositionMapper $invoicePositionMapper;
	private IUser $user;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->mapper = new CreditNoteMapper($db);
		$this->positionMapper = new CreditNotePositionMapper($db);
		$this->invoiceMapper = new InvoiceMapper($db);
		$this->invoicePositionMapper = new InvoicePositionMapper($db);
		$folderService = new ErpFolderService(\OC::$server->get(IRootFolder::class));
		$projectService = new ProjectService(new \OCA\ERP\Db\ProjectMapper($db), $folderService);

		$this->invoiceService = new InvoiceService(
			$this->invoiceMapper,
			$this->invoicePositionMapper,
			new QuoteMapper($db),
			new QuotePositionMapper($db),
			$db,
			$folderService,
			$projectService,
		);
		$this->service = new CreditNoteService($this->mapper, $this->positionMapper, $this->invoicePositionMapper, $this->invoiceService);

		$userManager = \OC::$server->get(IUserManager::class);
		if ($userManager->userExists(self::TEST_UID)) {
			$userManager->get(self::TEST_UID)->delete();
		}
		$this->user = $userManager->createUser(self::TEST_UID, 'Phpunit-Test-Pass-1!');
		self::loginAsUser(self::TEST_UID);
	}

	protected function tearDown(): void {
		foreach ($this->invoiceMapper->findAll() as $invoice) {
			if (str_starts_with($invoice->getTitle(), 'phpunit-cn-')) {
				foreach ($this->mapper->findByInvoice($invoice->getId()) as $cn) {
					foreach ($this->positionMapper->findByCreditNote($cn->getId()) as $p) {
						$this->positionMapper->delete($p);
					}
					$this->mapper->delete($cn);
				}
				foreach ($this->invoicePositionMapper->findByInvoice($invoice->getId()) as $p) {
					$this->invoicePositionMapper->delete($p);
				}
				$this->invoiceMapper->delete($invoice);
			}
		}
		self::logout();
		$this->user->delete();
		parent::tearDown();
	}

	private function issuedInvoice(float $unitPriceNet = 100.0): \OCA\ERP\Db\Invoice {
		$invoice = $this->invoiceService->createDraft('phpunit-cn-invoice', 'invoice', null, null, null, null, null);
		$this->invoiceService->addPosition($invoice->getId(), 'custom', null, 'x', 1.0, 'Stk', $unitPriceNet, 19.0);
		return $this->invoiceService->issue($invoice->getId(), $this->user);
	}

	public function testFullCancellationCopiesInvoicePositions(): void {
		$invoice = $this->issuedInvoice();
		$creditNote = $this->service->createFullCancellation($invoice->getId(), 'phpunit-cn-storno');

		$full = $this->service->getFull($creditNote->getId());
		$this->assertCount(1, $full['positions']);
		$this->assertTrue($creditNote->getCancelsInvoice());
	}

	public function testIssueFullCancellationCancelsInvoice(): void {
		$invoice = $this->issuedInvoice();
		$creditNote = $this->service->createFullCancellation($invoice->getId(), 'phpunit-cn-storno');
		$this->service->issue($creditNote->getId());

		$reloaded = $this->invoiceService->getInvoice($invoice->getId());
		$this->assertSame('cancelled', $reloaded->getStatus());
	}

	public function testIssuePartialDoesNotCancelInvoice(): void {
		$invoice = $this->issuedInvoice();
		$creditNote = $this->service->createPartial($invoice->getId(), 'phpunit-cn-teilkorrektur');
		$this->service->addPosition($creditNote->getId(), 'Korrektur', 1.0, 'Stk', 20.0, 19.0);
		$this->service->issue($creditNote->getId());

		$reloaded = $this->invoiceService->getInvoice($invoice->getId());
		$this->assertSame('issued', $reloaded->getStatus());
	}

	public function testIssueAssignsNumberWithGPrefix(): void {
		$invoice = $this->issuedInvoice();
		$creditNote = $this->service->createFullCancellation($invoice->getId(), 'phpunit-cn-storno');
		$issued = $this->service->issue($creditNote->getId());

		$year = (int) date('Y');
		$this->assertMatchesRegularExpression("/^G-$year-\\d{5}$/", $issued->getCreditNoteNumber());
	}

	public function testIssueWithoutPositionsThrows(): void {
		$invoice = $this->issuedInvoice();
		$creditNote = $this->service->createPartial($invoice->getId(), 'phpunit-cn-leer');

		$this->expectException(\DomainException::class);
		$this->service->issue($creditNote->getId());
	}

	public function testAddPositionAfterIssueThrows(): void {
		$invoice = $this->issuedInvoice();
		$creditNote = $this->service->createFullCancellation($invoice->getId(), 'phpunit-cn-storno');
		$this->service->issue($creditNote->getId());

		$this->expectException(\DomainException::class);
		$this->service->addPosition($creditNote->getId(), 'x', 1.0, 'Stk', 1.0, 19.0);
	}

	public function testCreateForUnknownInvoiceThrows(): void {
		$this->expectException(\OutOfBoundsException::class);
		$this->service->createPartial(999999999, 'phpunit-cn-unknown');
	}
}
