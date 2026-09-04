<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

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
use OCA\ERP\Db\ProjectMapper;
use OCA\ERP\Db\QuoteGroupMapper;
use OCA\ERP\Db\QuoteMapper;
use OCA\ERP\Db\QuotePositionMapper;
use OCA\ERP\Service\CompanyProfileService;
use OCA\ERP\Service\ContactsService;
use OCA\ERP\Service\DocumentHtmlBuilder;
use OCA\ERP\Service\DocumentPdfService;
use OCA\ERP\Service\ErpFolderService;
use OCA\ERP\Service\InvoiceService;
use OCA\ERP\Service\ProjectService;
use OCA\ERP\Service\QuoteService;
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
final class InvoiceServiceTest extends TestCase {
	use ErpTestGroupTrait;

	private const TEST_UID = 'phpunit-invoice-user';

	private InvoiceService $service;
	private InvoiceMapper $mapper;
	private InvoicePositionMapper $positionMapper;
	private InvoiceGroupMapper $groupMapper;
	private QuoteService $quoteService;
	private ProjectMapper $projectMapper;
	private ErpFolderService $folderService;
	private OrderMapper $orderMapper;
	private OrderPositionMapper $orderPositionMapper;
	private OrderGroupMapper $orderGroupMapper;
	private DeliveryNoteMapper $deliveryNoteMapper;
	private DeliveryNotePositionMapper $deliveryNotePositionMapper;
	private DeliveryNoteGroupMapper $deliveryNoteGroupMapper;
	private IUser $user;
	private int $projectId;
	private string $projectNumber;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->mapper = new InvoiceMapper($db);
		$this->positionMapper = new InvoicePositionMapper($db);
		$this->groupMapper = new InvoiceGroupMapper($db);
		$quoteMapper = new QuoteMapper($db);
		$quotePositionMapper = new QuotePositionMapper($db);
		$quoteGroupMapper = new QuoteGroupMapper($db);
		$this->folderService = new ErpFolderService(\OC::$server->get(IRootFolder::class));
		$this->projectMapper = new ProjectMapper($db);
		$projectService = new ProjectService($this->projectMapper, $this->folderService);
		$pdfService = new DocumentPdfService();
		$htmlBuilder = new DocumentHtmlBuilder(
			new CompanyProfileService(new CompanyProfileMapper($db)),
			new ContactsService(new ContactLinkMapper($db), \OC::$server->get(IContactsManager::class)),
		);
		$this->quoteService = new QuoteService($quoteMapper, $quoteGroupMapper, $quotePositionMapper, $this->folderService, $projectService, $pdfService, $htmlBuilder);

		$this->orderMapper = new OrderMapper($db);
		$this->orderPositionMapper = new OrderPositionMapper($db);
		$this->orderGroupMapper = new OrderGroupMapper($db);
		$this->deliveryNoteMapper = new DeliveryNoteMapper($db);
		$this->deliveryNotePositionMapper = new DeliveryNotePositionMapper($db);
		$this->deliveryNoteGroupMapper = new DeliveryNoteGroupMapper($db);

		$this->service = new InvoiceService(
			$this->mapper,
			$this->positionMapper,
			$this->groupMapper,
			$quoteMapper,
			$quotePositionMapper,
			$quoteGroupMapper,
			$this->orderMapper,
			$this->orderPositionMapper,
			$this->orderGroupMapper,
			$this->deliveryNoteMapper,
			$this->deliveryNotePositionMapper,
			$this->deliveryNoteGroupMapper,
			$db,
			$this->folderService,
			$projectService,
			$pdfService,
			$htmlBuilder,
		);

		$userManager = \OC::$server->get(IUserManager::class);
		if ($userManager->userExists(self::TEST_UID)) {
			$userManager->get(self::TEST_UID)->delete();
		}
		$this->user = $userManager->createUser(self::TEST_UID, 'Phpunit-Test-Pass-1!');
		$this->addToErpGroup($this->user);
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
				foreach ($this->groupMapper->findByInvoice($invoice->getId()) as $g) {
					$this->groupMapper->delete($g);
				}
				$this->mapper->delete($invoice);
			}
		}
		foreach ($this->deliveryNoteMapper->findByProject($this->projectId) as $dn) {
			foreach ($this->deliveryNotePositionMapper->findByDeliveryNote($dn->getId()) as $p) {
				$this->deliveryNotePositionMapper->delete($p);
			}
			foreach ($this->deliveryNoteGroupMapper->findByDeliveryNote($dn->getId()) as $g) {
				$this->deliveryNoteGroupMapper->delete($g);
			}
			$this->deliveryNoteMapper->delete($dn);
		}
		foreach ($this->orderMapper->findByProject($this->projectId) as $order) {
			foreach ($this->orderPositionMapper->findByOrder($order->getId()) as $p) {
				$this->orderPositionMapper->delete($p);
			}
			foreach ($this->orderGroupMapper->findByOrder($order->getId()) as $g) {
				$this->orderGroupMapper->delete($g);
			}
			$this->orderMapper->delete($order);
		}
		$this->projectMapper->delete($this->projectMapper->findById($this->projectId));
		self::logout();
		$this->removeFromErpGroup($this->user);
		$this->user->delete();
		parent::tearDown();
	}

	private function draftWithOnePosition(float $unitPriceNet = 100.0, float $vat = 19.0): \OCA\ERP\Db\Invoice {
		$invoice = $this->service->createDraft('phpunit-invoice-1', 'invoice', $this->projectId, null, 'cust-1', null, null);
		$this->service->addPosition($invoice->getId(), null, 'custom', null, 'Testposition', 1.0, 'Stk', $unitPriceNet, $vat);
		return $invoice;
	}

	private function createOrderWithPosition(string $positionType = 'article', float $quantity = 5.0, float $unitPriceNet = 10.0, ?int $groupId = null): array {
		$order = new \OCA\ERP\Db\Order();
		$order->setProjectId($this->projectId);
		$order->setTitle('phpunit-order-for-invoice');
		$order->setStatus('draft');
		$order->setCustomerContactUid('cust-order');
		$order->setCreatedAt(time());
		$order->setUpdatedAt(time());
		$order = $this->orderMapper->insert($order);

		$position = new \OCA\ERP\Db\OrderPosition();
		$position->setOrderId($order->getId());
		$position->setGroupId($groupId);
		$position->setPositionType($positionType);
		$position->setDescription('Testposition');
		$position->setQuantity($quantity);
		$position->setUnit('Stk');
		$position->setUnitPriceNet($unitPriceNet);
		$position->setVatRatePercent(19.0);
		$position = $this->orderPositionMapper->insert($position);

		return [$order, $position];
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
		$this->service->addPosition($invoice->getId(), null, 'custom', null, 'x', 1.0, 'Stk', 1.0, 19.0);
	}

	/**
	 * Regressionstest analog zu QuotePosition (ADR-0011/Phase 5):
	 * 'custom' ist zufällig der PHP-Default von InvoicePosition::$positionType.
	 */
	public function testCustomPositionTypeIsPersistedCorrectly(): void {
		$invoice = $this->service->createDraft('phpunit-invoice-4', 'invoice', $this->projectId, null, null, null, null);
		$position = $this->service->addPosition($invoice->getId(), null, 'custom', null, 'Anfahrt', 1.0, 'psch.', 25.0, 19.0);

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

	/** ADR-0021: Rechnungsdokument ist seither ein echtes PDF, kein HTML mehr. */
	public function testIssueWritesDocumentToProjectFolder(): void {
		$invoice = $this->draftWithOnePosition();
		$issued = $this->service->issue($invoice->getId(), $this->user);

		$this->assertNotNull($issued->getDocumentFileId());

		$invoiceFolder = $this->folderService->ensureInvoiceFolder($this->user, $this->projectNumber);
		$matches = array_filter(
			$invoiceFolder->getDirectoryListing(),
			static fn ($node) => str_starts_with($node->getName(), $issued->getInvoiceNumber() . '_') && str_ends_with($node->getName(), '.pdf'),
		);
		$this->assertNotEmpty($matches);
	}

	public function testCreateFromOrderCopiesSelectedPositionWithPartialQuantity(): void {
		[$order, $position] = $this->createOrderWithPosition('article', 10.0, 12.5);

		$invoice = $this->service->createFromOrder($order->getId(), 'phpunit-invoice-partial', 'partial', null, null, [
			['orderPositionId' => $position->getId(), 'quantity' => 4.0],
		]);

		$this->assertSame($order->getId(), $invoice->getOrderId());
		$this->assertSame('cust-order', $invoice->getCustomerContactUid());
		$this->assertSame('partial', $invoice->getType());

		$full = $this->service->getFullInvoice($invoice->getId());
		$this->assertCount(1, $full['positions']);
		$this->assertSame(4.0, $full['positions'][0]->getQuantity());
		$this->assertSame(12.5, $full['positions'][0]->getUnitPriceNet());
		$this->assertSame($position->getId(), $full['positions'][0]->getOrderPositionId());
	}

	public function testCreateFromOrderWithEmptyPositionsThrows(): void {
		[$order] = $this->createOrderWithPosition();
		$this->expectException(\InvalidArgumentException::class);
		$this->service->createFromOrder($order->getId(), 'phpunit-invoice-empty', 'invoice', null, null, []);
	}

	public function testCreateFromUnknownOrderThrows(): void {
		$this->expectException(\OutOfBoundsException::class);
		$this->service->createFromOrder(999999999, 'phpunit-invoice-no-order', 'invoice', null, null, [
			['orderPositionId' => 1, 'quantity' => 1.0],
		]);
	}

	public function testCreateFromDeliveryNoteInheritsPriceFromLinkedOrderPosition(): void {
		[$order, $orderPosition] = $this->createOrderWithPosition('product', 5.0, 30.0);
		$deliveryNote = $this->deliveryNoteMapper->insert((function () use ($order) {
			$dn = new \OCA\ERP\Db\DeliveryNote();
			$dn->setProjectId($this->projectId);
			$dn->setOrderId($order->getId());
			$dn->setStatus('draft');
			$dn->setCreatedAt(time());
			$dn->setUpdatedAt(time());
			return $dn;
		})());
		$dnPosition = new \OCA\ERP\Db\DeliveryNotePosition();
		$dnPosition->setDeliveryNoteId($deliveryNote->getId());
		$dnPosition->setPositionType('product');
		$dnPosition->setDescription('Testposition');
		$dnPosition->setQuantity(2.0);
		$dnPosition->setUnit('Stk');
		$dnPosition->setOrderPositionId($orderPosition->getId());
		$dnPosition = $this->deliveryNotePositionMapper->insert($dnPosition);

		$invoice = $this->service->createFromDeliveryNote($deliveryNote->getId(), 'phpunit-invoice-from-dn', 'invoice', null, null, [
			['deliveryNotePositionId' => $dnPosition->getId()],
		]);

		$this->assertSame($deliveryNote->getId(), $invoice->getDeliveryNoteId());
		$full = $this->service->getFullInvoice($invoice->getId());
		$this->assertCount(1, $full['positions']);
		$this->assertSame(30.0, $full['positions'][0]->getUnitPriceNet());
		$this->assertSame(2.0, $full['positions'][0]->getQuantity());
	}

	public function testCreateFromDeliveryNoteWithoutLinkedOrderPositionRequiresExplicitPrice(): void {
		$deliveryNote = $this->deliveryNoteMapper->insert((function () {
			$dn = new \OCA\ERP\Db\DeliveryNote();
			$dn->setProjectId($this->projectId);
			$dn->setStatus('draft');
			$dn->setCreatedAt(time());
			$dn->setUpdatedAt(time());
			return $dn;
		})());
		$dnPosition = new \OCA\ERP\Db\DeliveryNotePosition();
		$dnPosition->setDeliveryNoteId($deliveryNote->getId());
		$dnPosition->setPositionType('custom');
		$dnPosition->setDescription('Ohne Auftragsbezug');
		$dnPosition->setQuantity(1.0);
		$dnPosition->setUnit('Stk');
		$dnPosition = $this->deliveryNotePositionMapper->insert($dnPosition);

		$this->expectException(\InvalidArgumentException::class);
		$this->service->createFromDeliveryNote($deliveryNote->getId(), 'phpunit-invoice-no-price', 'invoice', null, null, [
			['deliveryNotePositionId' => $dnPosition->getId()],
		]);
	}

	public function testGetFullInvoiceListsRelatedInvoicesOfSameOrder(): void {
		[$order, $position] = $this->createOrderWithPosition('article', 10.0, 20.0);

		$partial = $this->service->createFromOrder($order->getId(), 'phpunit-invoice-teil-1', 'partial', null, null, [
			['orderPositionId' => $position->getId(), 'quantity' => 4.0],
		]);
		$final = $this->service->createFromOrder($order->getId(), 'phpunit-invoice-schluss', 'final', null, null, [
			['orderPositionId' => $position->getId(), 'quantity' => 10.0],
		]);

		$fullFinal = $this->service->getFullInvoice($final->getId());
		$this->assertCount(1, $fullFinal['relatedInvoices']);
		$this->assertSame($partial->getId(), $fullFinal['relatedInvoices'][0]['id']);
		$this->assertSame(95.2, $fullFinal['relatedInvoices'][0]['grossTotal']);

		$fullPartial = $this->service->getFullInvoice($partial->getId());
		$this->assertCount(1, $fullPartial['relatedInvoices']);
		$this->assertSame($final->getId(), $fullPartial['relatedInvoices'][0]['id']);
	}

	public function testGetFullInvoiceWithoutOrderHasEmptyRelatedInvoices(): void {
		$invoice = $this->draftWithOnePosition();
		$full = $this->service->getFullInvoice($invoice->getId());
		$this->assertSame([], $full['relatedInvoices']);
	}

	public function testAddGroupAndAssignPositionToIt(): void {
		$invoice = $this->service->createDraft('phpunit-invoice-group', 'invoice', $this->projectId, null, null, null, null);
		$group = $this->service->addGroup($invoice->getId(), 'Elektrik');
		$this->service->addPosition($invoice->getId(), $group->getId(), 'custom', null, 'Kabel', 1.0, 'Stk', 10.0, 19.0);

		$full = $this->service->getFullInvoice($invoice->getId());
		$this->assertCount(1, $full['groups']);
		$this->assertSame('Elektrik', $full['groups'][0]->getTitle());
		$this->assertSame($group->getId(), $full['positions'][0]->getGroupId());
	}

	public function testCreateFromOrderPreservesGroup(): void {
		$orderGroup = new \OCA\ERP\Db\OrderGroup();
		$orderGroup->setTitle('Elektrik');
		[$order, $position] = $this->createOrderWithPosition('article', 5.0, 10.0);
		$orderGroup->setOrderId($order->getId());
		$orderGroup = $this->orderGroupMapper->insert($orderGroup);
		$position->setGroupId($orderGroup->getId());
		$this->orderPositionMapper->update($position);

		$invoice = $this->service->createFromOrder($order->getId(), 'phpunit-invoice-with-group', 'invoice', null, null, [
			['orderPositionId' => $position->getId(), 'quantity' => 2.0],
		]);

		$full = $this->service->getFullInvoice($invoice->getId());
		$this->assertCount(1, $full['groups']);
		$this->assertSame('Elektrik', $full['groups'][0]->getTitle());
		$this->assertSame($full['groups'][0]->getId(), $full['positions'][0]->getGroupId());
	}

	public function testCreateFromDeliveryNotePreservesGroup(): void {
		[$order, $orderPosition] = $this->createOrderWithPosition('product', 5.0, 30.0);
		$deliveryNote = $this->deliveryNoteMapper->insert((function () use ($order) {
			$dn = new \OCA\ERP\Db\DeliveryNote();
			$dn->setProjectId($this->projectId);
			$dn->setOrderId($order->getId());
			$dn->setStatus('draft');
			$dn->setCreatedAt(time());
			$dn->setUpdatedAt(time());
			return $dn;
		})());
		$dnGroup = new \OCA\ERP\Db\DeliveryNoteGroup();
		$dnGroup->setDeliveryNoteId($deliveryNote->getId());
		$dnGroup->setTitle('Elektrik');
		$dnGroup = $this->deliveryNoteGroupMapper->insert($dnGroup);

		$dnPosition = new \OCA\ERP\Db\DeliveryNotePosition();
		$dnPosition->setDeliveryNoteId($deliveryNote->getId());
		$dnPosition->setGroupId($dnGroup->getId());
		$dnPosition->setPositionType('product');
		$dnPosition->setDescription('Testposition');
		$dnPosition->setQuantity(2.0);
		$dnPosition->setUnit('Stk');
		$dnPosition->setOrderPositionId($orderPosition->getId());
		$dnPosition = $this->deliveryNotePositionMapper->insert($dnPosition);

		$invoice = $this->service->createFromDeliveryNote($deliveryNote->getId(), 'phpunit-invoice-dn-group', 'invoice', null, null, [
			['deliveryNotePositionId' => $dnPosition->getId()],
		]);

		$full = $this->service->getFullInvoice($invoice->getId());
		$this->assertCount(1, $full['groups']);
		$this->assertSame('Elektrik', $full['groups'][0]->getTitle());
		$this->assertSame($full['groups'][0]->getId(), $full['positions'][0]->getGroupId());
	}
}
