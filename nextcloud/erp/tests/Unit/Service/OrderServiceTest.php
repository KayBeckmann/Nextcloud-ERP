<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\DeliveryNotePositionMapper;
use OCA\ERP\Db\InvoicePositionMapper;
use OCA\ERP\Db\OrderGroupMapper;
use OCA\ERP\Db\OrderMapper;
use OCA\ERP\Db\OrderPositionMapper;
use OCA\ERP\Db\ProjectMapper;
use OCA\ERP\Db\QuoteGroupMapper;
use OCA\ERP\Db\QuoteMapper;
use OCA\ERP\Db\QuotePositionMapper;
use OCA\ERP\Projects\OrderStatus;
use OCA\ERP\Service\ErpFolderService;
use OCA\ERP\Service\OrderService;
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
final class OrderServiceTest extends TestCase {
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
	private IUser $user;
	private int $realProjectId;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->mapper = new OrderMapper($db);
		$this->positionMapper = new OrderPositionMapper($db);
		$this->quoteMapper = new QuoteMapper($db);
		$this->quotePositionMapper = new QuotePositionMapper($db);
		$this->quoteGroupMapper = new QuoteGroupMapper($db);
		$this->quoteService = new QuoteService($this->quoteMapper, $this->quoteGroupMapper, $this->quotePositionMapper);
		$this->groupMapper = new OrderGroupMapper($db);
		$this->service = new OrderService(
			$this->mapper,
			$this->positionMapper,
			$this->groupMapper,
			$this->quoteMapper,
			$this->quotePositionMapper,
			$this->quoteGroupMapper,
			new InvoicePositionMapper($db),
			new DeliveryNotePositionMapper($db),
		);

		$userManager = \OC::$server->get(IUserManager::class);
		if ($userManager->userExists(self::TEST_UID)) {
			$userManager->get(self::TEST_UID)->delete();
		}
		$this->user = $userManager->createUser(self::TEST_UID, 'Phpunit-Test-Pass-1!');
		self::loginAsUser(self::TEST_UID);

		$folderService = new ErpFolderService(\OC::$server->get(IRootFolder::class));
		$projectService = new ProjectService(new ProjectMapper($db), $folderService);
		$project = $projectService->createProject($this->user, 'phpunit-order-project', null, null, null);
		$this->realProjectId = $project->getId();
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
			$this->user->delete();
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
}
