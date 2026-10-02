<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\ArticleMapper;
use OCA\ERP\Db\ArticleSupplierPriceMapper;
use OCA\ERP\Db\CompanyProfileMapper;
use OCA\ERP\Db\ContactLinkMapper;
use OCA\ERP\Db\DeliveryNotePositionMapper;
use OCA\ERP\Db\InvoicePositionMapper;
use OCA\ERP\Db\OrderGroupMapper;
use OCA\ERP\Db\OrderMapper;
use OCA\ERP\Db\OrderPositionMapper;
use OCA\ERP\Db\ProjectMapper;
use OCA\ERP\Db\QuoteGroupMapper;
use OCA\ERP\Db\QuoteMapper;
use OCA\ERP\Db\QuotePositionMapper;
use OCA\ERP\Db\StockLevelMapper;
use OCA\ERP\Db\StockMovementMapper;
use OCA\ERP\Db\WarehouseMapper;
use OCA\ERP\Projects\OrderStatus;
use OCA\ERP\Service\ArticleService;
use OCA\ERP\Service\CompanyProfileService;
use OCA\ERP\Service\ContactsService;
use OCA\ERP\Service\DocumentHtmlBuilder;
use OCA\ERP\Service\DocumentPdfService;
use OCA\ERP\Service\ErpFolderService;
use OCA\ERP\Service\OrderService;
use OCA\ERP\Service\ProjectService;
use OCA\ERP\Service\QuoteService;
use OCA\ERP\Service\StockService;
use OCA\ERP\Service\WarehouseService;
use OCP\Contacts\IManager as IContactsManager;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use OCA\ERP\Tests\Unit\Support\ErpTestGroupTrait;
use OCA\ERP\Tests\Unit\Support\ErpIntegrationTestCase;

/**
 * @group DB
 */
final class OrderServiceTest extends ErpIntegrationTestCase {
	use ErpTestGroupTrait;

	private const TEST_UID = 'phpunit-order-user';
	private const PROJECT_ID = 999999002;

	private OrderService $service;
	private OrderMapper $mapper;
	private OrderPositionMapper $positionMapper;
	private OrderGroupMapper $groupMapper;
	private QuoteService $quoteService;
	private QuoteMapper $quoteMapper;
	private QuotePositionMapper $quotePositionMapper;
	private QuoteGroupMapper $quoteGroupMapper;
	private StockService $stockService;
	private StockLevelMapper $stockLevelMapper;
	private WarehouseMapper $warehouseMapper;
	private ArticleService $articleService;
	private ArticleMapper $articleMapper;
	private IUser $user;
	private int $realProjectId;
	private int $warehouseId;
	private int $articleId;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->mapper = new OrderMapper($db);
		$this->positionMapper = new OrderPositionMapper($db);
		$this->quoteMapper = new QuoteMapper($db);
		$this->quotePositionMapper = new QuotePositionMapper($db);
		$this->quoteGroupMapper = new QuoteGroupMapper($db);
		$folderService = new ErpFolderService(\OC::$server->get(IRootFolder::class));
		$projectService = new ProjectService(new ProjectMapper($db), $folderService);
		$pdfService = new DocumentPdfService();
		$htmlBuilder = new DocumentHtmlBuilder(
			new CompanyProfileService(new CompanyProfileMapper($db)),
			new ContactsService(new ContactLinkMapper($db), \OC::$server->get(IContactsManager::class)),
		);
		$this->quoteService = new QuoteService($this->quoteMapper, $this->quoteGroupMapper, $this->quotePositionMapper, $folderService, $projectService, $pdfService, $htmlBuilder);
		$this->groupMapper = new OrderGroupMapper($db);
		$this->stockLevelMapper = new StockLevelMapper($db);
		$this->stockService = new StockService($this->stockLevelMapper, new StockMovementMapper($db));
		$this->warehouseMapper = new WarehouseMapper($db);
		$this->articleMapper = new ArticleMapper($db);
		$this->articleService = new ArticleService($this->articleMapper, new ArticleSupplierPriceMapper($db), new ContactLinkMapper($db));
		$this->service = new OrderService(
			$this->mapper,
			$this->positionMapper,
			$this->groupMapper,
			$this->quoteMapper,
			$this->quotePositionMapper,
			$this->quoteGroupMapper,
			new InvoicePositionMapper($db),
			new DeliveryNotePositionMapper($db),
			$folderService,
			$projectService,
			$pdfService,
			$htmlBuilder,
			$this->stockService,
		);

		$userManager = \OC::$server->get(IUserManager::class);
		if ($userManager->userExists(self::TEST_UID)) {
			$userManager->get(self::TEST_UID)->delete();
		}
		$this->user = $userManager->createUser(self::TEST_UID, 'Phpunit-Test-Pass-1!');
		$this->addToErpGroup($this->user);
		self::loginAsUser(self::TEST_UID);

		$project = $projectService->createProject($this->user, 'phpunit-order-project', null, null, null);
		$this->realProjectId = $project->getId();

		$warehouseService = new WarehouseService($this->warehouseMapper, new ProjectMapper($db));
		$this->warehouseId = $warehouseService->create('phpunit-order-warehouse', 'central', null, null)->getId();
		$this->articleId = $this->articleService->create('phpunit-order-article', null, null, 'Stk', null, null, null)->getId();
	}

	protected function tearDown(): void {
		foreach ($this->mapper->findByProject(self::PROJECT_ID) as $order) {
			foreach ($this->positionMapper->findByOrder($order->getId()) as $p) {
				$this->positionMapper->delete($p);
			}
			foreach ($this->groupMapper->findByOrder($order->getId()) as $g) {
				$this->groupMapper->delete($g);
			}
			$this->mapper->delete($order);
		}
		foreach ($this->mapper->findByProject($this->realProjectId) as $order) {
			foreach ($this->positionMapper->findByOrder($order->getId()) as $p) {
				$this->positionMapper->delete($p);
			}
			foreach ($this->groupMapper->findByOrder($order->getId()) as $g) {
				$this->groupMapper->delete($g);
			}
			$this->mapper->delete($order);
		}
		foreach ($this->quoteMapper->findAll(null, $this->realProjectId) as $quote) {
			foreach ($this->quotePositionMapper->findByQuote($quote->getId()) as $p) {
				$this->quotePositionMapper->delete($p);
			}
			foreach ($this->quoteGroupMapper->findByQuote($quote->getId()) as $g) {
				$this->quoteGroupMapper->delete($g);
			}
			$this->quoteMapper->delete($quote);
		}
		if (isset($this->user)) {
			$this->removeFromErpGroup($this->user);
			$this->user->delete();
		}
		if (isset($this->warehouseId, $this->articleId)) {
			$level = $this->stockLevelMapper->findOne($this->articleId, $this->warehouseId);
			if ($level !== null) {
				$this->stockLevelMapper->delete($level);
			}
		}
		if (isset($this->warehouseId)) {
			$warehouse = $this->warehouseMapper->findById($this->warehouseId);
			if ($warehouse !== null) {
				$this->warehouseMapper->delete($warehouse);
			}
		}
		if (isset($this->articleId)) {
			$article = $this->articleMapper->findById($this->articleId);
			if ($article !== null) {
				$this->articleMapper->delete($article);
			}
		}
		parent::tearDown();
	}

	public function testCreateOrderDefaultsToDraft(): void {
		$order = $this->service->createOrder(self::PROJECT_ID, 'Ausführung', 'Beschreibung');
		$this->assertSame(OrderStatus::Draft->value, $order->getStatus());
		$this->assertSame('Beschreibung', $order->getDescription());
	}

	public function testUpdateOrderChangesStatus(): void {
		$order = $this->service->createOrder(self::PROJECT_ID, 'Ausführung', null);
		$updated = $this->service->updateOrder(self::PROJECT_ID, $order->getId(), 'Ausführung', OrderStatus::Confirmed, null);
		$this->assertSame('confirmed', $updated->getStatus());
	}

	/** ADR-0021: PDF wird beim erstmaligen Wechsel nach 'confirmed' abgelegt. */
	public function testUpdateOrderToConfirmedWritesPdfDocument(): void {
		$order = $this->service->createOrder($this->realProjectId, 'phpunit-order-pdf', null);
		$this->service->addPosition($order->getId(), null, 'custom', null, 'Pauschale', 1.0, 'psch.', 50.0, 19.0);
		$this->assertNull($order->getDocumentFileId());

		$updated = $this->service->updateOrder($this->realProjectId, $order->getId(), 'phpunit-order-pdf', OrderStatus::Confirmed, null, null, null, $this->user);
		$this->assertNotNull($updated->getDocumentFileId());
	}

	public function testUpdateUnknownOrderThrows(): void {
		$this->expectException(\OutOfBoundsException::class);
		$this->service->updateOrder(self::PROJECT_ID, 999999999, 'x', OrderStatus::Draft, null);
	}

	public function testListOrdersScopedToProject(): void {
		$this->service->createOrder(self::PROJECT_ID, 'Eigenes Projekt', null);
		$this->assertCount(1, $this->service->listOrders(self::PROJECT_ID));
		$this->assertCount(0, $this->service->listOrders(self::PROJECT_ID + 1));
	}

	public function testCreateOrderStoresCustomerContactUid(): void {
		$order = $this->service->createOrder(self::PROJECT_ID, 'Mit Kunde', null, 'kay');
		$this->assertSame('kay', $order->getCustomerContactUid());
	}

	public function testCreateAndUpdateOrderStoreAssignedUserId(): void {
		$order = $this->service->createOrder(self::PROJECT_ID, 'Mit Zuweisung', null, null, 'mitarbeiter-a');
		$this->assertSame('mitarbeiter-a', $order->getAssignedUserId());

		$updated = $this->service->updateOrder(self::PROJECT_ID, $order->getId(), 'Mit Zuweisung', OrderStatus::Confirmed, null, null, 'mitarbeiter-b');
		$this->assertSame('mitarbeiter-b', $updated->getAssignedUserId());
	}

	public function testAddAndRemovePosition(): void {
		$order = $this->service->createOrder(self::PROJECT_ID, 'Mit Positionen', null);
		$position = $this->service->addPosition($order->getId(), null, 'article', null, 'Kabel', 10, 'Stk', 2.5, 19);
		$full = $this->service->getFullOrder($order->getId());
		$this->assertCount(1, $full['positions']);
		$this->assertSame(0.0, $full['positions'][0]['invoicedQuantity']);
		$this->assertSame(0.0, $full['positions'][0]['deliveredQuantity']);

		$this->service->removePosition($order->getId(), $position->getId());
		$full = $this->service->getFullOrder($order->getId());
		$this->assertCount(0, $full['positions']);
	}

	public function testAddPositionRejectsUnknownType(): void {
		$order = $this->service->createOrder(self::PROJECT_ID, 'Mit Positionen', null);
		$this->expectException(\InvalidArgumentException::class);
		$this->service->addPosition($order->getId(), null, 'unknown', null, 'x', 1, 'Stk', 1, 0);
	}

	public function testAddGroupAndAssignPositionToIt(): void {
		$order = $this->service->createOrder(self::PROJECT_ID, 'Mit Gruppen', null);
		$group = $this->service->addGroup($order->getId(), 'Elektrik');
		$this->service->addPosition($order->getId(), $group->getId(), 'article', null, 'Kabel', 10, 'Stk', 2.5, 19);

		$full = $this->service->getFullOrder($order->getId());
		$this->assertCount(1, $full['groups']);
		$this->assertSame('Elektrik', $full['groups'][0]->getTitle());
		$this->assertSame($group->getId(), $full['positions'][0]['groupId']);
	}

	public function testAddPositionRejectsUnknownGroup(): void {
		$order = $this->service->createOrder(self::PROJECT_ID, 'Mit Positionen', null);
		$this->expectException(\OutOfBoundsException::class);
		$this->service->addPosition($order->getId(), 999999999, 'article', null, 'x', 1, 'Stk', 1, 0);
	}

	public function testCreateFromQuoteCopiesPositionsAndCustomer(): void {
		$quote = $this->quoteService->createQuote('phpunit-quote-for-order', $this->realProjectId, 'kay', null);
		$this->quoteService->addPosition($quote->getId(), null, 'article', null, 'Sicherung', 5, 'Stk', 3.0, 19.0);
		$this->quoteService->addPosition($quote->getId(), null, 'labor', null, 'Montage', 2, 'Std', 60.0, 19.0);

		$order = $this->service->createFromQuote($quote->getId());
		$this->assertSame($this->realProjectId, $order->getProjectId());
		$this->assertSame('kay', $order->getCustomerContactUid());
		$this->assertSame($quote->getId(), $order->getQuoteId());

		$full = $this->service->getFullOrder($order->getId());
		$this->assertCount(2, $full['positions']);
	}

	public function testCreateFromQuotePreservesGroups(): void {
		$quote = $this->quoteService->createQuote('phpunit-quote-with-group', $this->realProjectId, 'kay', null);
		$group = $this->quoteService->addGroup($quote->getId(), 'Elektrik');
		$this->quoteService->addPosition($quote->getId(), $group->getId(), 'article', null, 'Sicherung', 5, 'Stk', 3.0, 19.0);
		$this->quoteService->addPosition($quote->getId(), null, 'labor', null, 'Montage', 2, 'Std', 60.0, 19.0);

		$order = $this->service->createFromQuote($quote->getId());
		$full = $this->service->getFullOrder($order->getId());

		$this->assertCount(1, $full['groups']);
		$this->assertSame('Elektrik', $full['groups'][0]->getTitle());

		$grouped = array_values(array_filter($full['positions'], static fn ($p) => $p['description'] === 'Sicherung'));
		$ungrouped = array_values(array_filter($full['positions'], static fn ($p) => $p['description'] === 'Montage'));
		$this->assertSame($full['groups'][0]->getId(), $grouped[0]['groupId']);
		$this->assertNull($ungrouped[0]['groupId']);
	}

	public function testCreateFromUnknownQuoteThrows(): void {
		$this->expectException(\OutOfBoundsException::class);
		$this->service->createFromQuote(999999999);
	}

	/**
	 * ADR-0039: Eine Auftragsposition vom Typ 'article' mit Lagerauswahl
	 * reserviert automatisch Bestand.
	 */
	public function testAddArticlePositionWithWarehouseReservesStock(): void {
		$order = $this->service->createOrder($this->realProjectId, 'phpunit-order-reserve-1', null);
		$this->service->addPosition($order->getId(), null, 'article', $this->articleId, 'Kabel', 5.0, 'Stk', 10.0, 19.0, 0.0, $this->warehouseId);

		$level = $this->stockLevelMapper->findOne($this->articleId, $this->warehouseId);
		$this->assertSame(5.0, $level->getQuantityReserved());
	}

	public function testAddPositionWithWarehouseOnNonArticleTypeThrows(): void {
		$order = $this->service->createOrder($this->realProjectId, 'phpunit-order-reserve-2', null);

		$this->expectException(\InvalidArgumentException::class);
		$this->service->addPosition($order->getId(), null, 'custom', null, 'Pauschale', 1.0, 'Stk', 10.0, 19.0, 0.0, $this->warehouseId);
	}

	public function testUpdatePositionChangesReservationQuantity(): void {
		$order = $this->service->createOrder($this->realProjectId, 'phpunit-order-reserve-3', null);
		$position = $this->service->addPosition($order->getId(), null, 'article', $this->articleId, 'Kabel', 5.0, 'Stk', 10.0, 19.0, 0.0, $this->warehouseId);

		$this->service->updatePosition($order->getId(), $position->getId(), 'Kabel', 8.0, 'Stk', 10.0, 19.0, 0.0, $this->warehouseId);

		$level = $this->stockLevelMapper->findOne($this->articleId, $this->warehouseId);
		$this->assertSame(8.0, $level->getQuantityReserved());
	}

	public function testUpdatePositionRemovingWarehouseReleasesReservation(): void {
		$order = $this->service->createOrder($this->realProjectId, 'phpunit-order-reserve-4', null);
		$position = $this->service->addPosition($order->getId(), null, 'article', $this->articleId, 'Kabel', 5.0, 'Stk', 10.0, 19.0, 0.0, $this->warehouseId);

		$this->service->updatePosition($order->getId(), $position->getId(), 'Kabel', 5.0, 'Stk', 10.0, 19.0, 0.0, null);

		$level = $this->stockLevelMapper->findOne($this->articleId, $this->warehouseId);
		$this->assertSame(0.0, $level->getQuantityReserved());
	}

	public function testRemovePositionReleasesReservation(): void {
		$order = $this->service->createOrder($this->realProjectId, 'phpunit-order-reserve-5', null);
		$position = $this->service->addPosition($order->getId(), null, 'article', $this->articleId, 'Kabel', 5.0, 'Stk', 10.0, 19.0, 0.0, $this->warehouseId);

		$this->service->removePosition($order->getId(), $position->getId());

		$level = $this->stockLevelMapper->findOne($this->articleId, $this->warehouseId);
		$this->assertSame(0.0, $level->getQuantityReserved());
	}

	public function testUpdatePositionMovingToDifferentWarehouseTransfersReservation(): void {
		$warehouseService = new WarehouseService($this->warehouseMapper, new ProjectMapper(\OC::$server->get(IDBConnection::class)));
		$secondWarehouseId = $warehouseService->create('phpunit-order-warehouse-2', 'central', null, null)->getId();

		$order = $this->service->createOrder($this->realProjectId, 'phpunit-order-reserve-6', null);
		$position = $this->service->addPosition($order->getId(), null, 'article', $this->articleId, 'Kabel', 5.0, 'Stk', 10.0, 19.0, 0.0, $this->warehouseId);

		$this->service->updatePosition($order->getId(), $position->getId(), 'Kabel', 5.0, 'Stk', 10.0, 19.0, 0.0, $secondWarehouseId);

		$oldLevel = $this->stockLevelMapper->findOne($this->articleId, $this->warehouseId);
		$newLevel = $this->stockLevelMapper->findOne($this->articleId, $secondWarehouseId);
		$this->assertSame(0.0, $oldLevel->getQuantityReserved());
		$this->assertSame(5.0, $newLevel->getQuantityReserved());

		$newLevelRow = $this->stockLevelMapper->findOne($this->articleId, $secondWarehouseId);
		if ($newLevelRow !== null) {
			$this->stockLevelMapper->delete($newLevelRow);
		}
		$secondWarehouse = $this->warehouseMapper->findById($secondWarehouseId);
		if ($secondWarehouse !== null) {
			$this->warehouseMapper->delete($secondWarehouse);
		}
	}
}
