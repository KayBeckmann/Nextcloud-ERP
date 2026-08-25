<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\CompanyProfileMapper;
use OCA\ERP\Db\ContactLinkMapper;
use OCA\ERP\Db\Project;
use OCA\ERP\Db\ProjectMapper;
use OCA\ERP\Db\QuoteGroupMapper;
use OCA\ERP\Db\QuoteMapper;
use OCA\ERP\Db\QuotePositionMapper;
use OCA\ERP\Service\CompanyProfileService;
use OCA\ERP\Service\ContactsService;
use OCA\ERP\Service\DocumentHtmlBuilder;
use OCA\ERP\Service\DocumentPdfService;
use OCA\ERP\Service\ErpFolderService;
use OCA\ERP\Service\ProjectService;
use OCA\ERP\Service\QuoteService;
use OCP\Contacts\IManager as IContactsManager;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use Test\TestCase;

/**
 * @group DB
 */
final class QuoteServiceTest extends TestCase {
	private const TEST_UID = 'phpunit-quote-user';

	private QuoteService $service;
	private QuoteMapper $mapper;
	private ProjectMapper $projectMapper;
	private IUser $user;
	private int $projectId;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->mapper = new QuoteMapper($db);
		$this->projectMapper = new ProjectMapper($db);
		$folderService = new ErpFolderService(\OC::$server->get(IRootFolder::class));
		$projectService = new ProjectService($this->projectMapper, $folderService);
		$htmlBuilder = new DocumentHtmlBuilder(
			new CompanyProfileService(new CompanyProfileMapper($db)),
			new ContactsService(new ContactLinkMapper($db), \OC::$server->get(IContactsManager::class)),
		);
		$this->service = new QuoteService(
			$this->mapper,
			new QuoteGroupMapper($db),
			new QuotePositionMapper($db),
			$folderService,
			$projectService,
			new DocumentPdfService(),
			$htmlBuilder,
		);

		$userManager = \OC::$server->get(IUserManager::class);
		if ($userManager->userExists(self::TEST_UID)) {
			$userManager->get(self::TEST_UID)->delete();
		}
		$this->user = $userManager->createUser(self::TEST_UID, 'Phpunit-Test-Pass-1!');
		self::loginAsUser(self::TEST_UID);

		$project = $projectService->createProject($this->user, 'phpunit-quote-project', null, null, null);
		$this->projectId = $project->getId();
	}

	protected function tearDown(): void {
		foreach ($this->mapper->findAll() as $quote) {
			if (str_starts_with($quote->getTitle(), 'phpunit-')) {
				$this->mapper->delete($quote);
			}
		}
		$this->projectMapper->delete($this->projectMapper->findById($this->projectId));
		self::logout();
		$this->user->delete();
		parent::tearDown();
	}

	public function testCreateQuoteGeneratesNumber(): void {
		$quote = $this->service->createQuote('phpunit-quote-1', $this->projectId, null, null);
		$this->assertSame(sprintf('A-%05d', $quote->getId()), $quote->getQuoteNumber());
		$this->assertSame('draft', $quote->getStatus());
		$this->assertSame($this->projectId, $quote->getProjectId());
	}

	/** ADR-0015: Angebote hängen zwingend an einem Projekt. */
	public function testCreateQuoteWithoutProjectThrows(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->createQuote('phpunit-quote-no-project', 0, null, null);
	}

	/**
	 * Regressionstest für den gefixten Entity-Default-Bug: 'custom' war
	 * zufällig der PHP-Default von QuotePosition::$positionType, wodurch
	 * Nextclouds Dirty-Tracking die Spalte beim Insert übersprang und die
	 * NOT-NULL-Spalte position_type verletzt wurde (siehe Commit-Message).
	 */
	public function testCustomPositionTypeIsPersistedCorrectly(): void {
		$quote = $this->service->createQuote('phpunit-quote-2', $this->projectId, null, null);
		$position = $this->service->addPosition(
			$quote->getId(),
			null,
			'custom',
			null,
			'Anfahrtspauschale',
			1.0,
			'psch.',
			25.0,
			7.0,
		);

		$this->assertSame('custom', $position->getPositionType());

		// Über einen frischen Mapper-Read erneut prüfen — stellt sicher, dass
		// der Wert wirklich in der DB steht, nicht nur im PHP-Objekt.
		$reloaded = (new QuotePositionMapper(\OC::$server->get(IDBConnection::class)))
			->findOne($quote->getId(), $position->getId());
		$this->assertNotNull($reloaded);
		$this->assertSame('custom', $reloaded->getPositionType());
	}

	public function testGroupNetTotalAndOverallCalculation(): void {
		$quote = $this->service->createQuote('phpunit-quote-3', $this->projectId, null, null);
		$group = $this->service->addGroup($quote->getId(), 'Material');
		$this->service->addPosition($quote->getId(), $group->getId(), 'article', null, 'Artikel A', 2.0, 'Stk', 10.0, 19.0);
		$this->service->addPosition($quote->getId(), null, 'custom', null, 'Pauschale', 1.0, 'psch.', 5.0, 19.0);

		$full = $this->service->getFullQuote($quote->getId());

		$this->assertSame(25.0, $full['calculation']['netSubtotal']); // 2*10 + 1*5
		$materialGroup = array_values(array_filter($full['calculation']['groups'], static fn ($g) => $g['title'] === 'Material'));
		$this->assertCount(1, $materialGroup);
		$this->assertSame(20.0, $materialGroup[0]['netTotal']);
	}

	public function testAddPositionToUnknownGroupThrows(): void {
		$quote = $this->service->createQuote('phpunit-quote-4', $this->projectId, null, null);
		$this->expectException(\OutOfBoundsException::class);
		$this->service->addPosition($quote->getId(), 999999999, 'custom', null, 'x', 1.0, 'Stk', 1.0, 19.0);
	}

	public function testUpdateQuoteToSentSetsSentAtOnce(): void {
		$quote = $this->service->createQuote('phpunit-quote-5', $this->projectId, null, null);
		$this->assertNull($quote->getSentAt());

		$sent = $this->service->updateQuote($quote->getId(), 'phpunit-quote-5', 'sent', $this->projectId, null, null, null);
		$this->assertNotNull($sent->getSentAt());
		$firstSentAt = $sent->getSentAt();

		// Erneutes Speichern im Status 'sent' darf sentAt nicht überschreiben.
		$sentAgain = $this->service->updateQuote($quote->getId(), 'phpunit-quote-5', 'sent', $this->projectId, null, null, null);
		$this->assertSame($firstSentAt, $sentAgain->getSentAt());
	}

	/** ADR-0021: PDF wird beim erstmaligen Wechsel nach 'sent' abgelegt. */
	public function testUpdateQuoteToSentWritesPdfDocument(): void {
		$quote = $this->service->createQuote('phpunit-quote-7', $this->projectId, null, null);
		$this->service->addPosition($quote->getId(), null, 'custom', null, 'Pauschale', 1.0, 'psch.', 100.0, 19.0);
		$this->assertNull($quote->getDocumentFileId());

		$sent = $this->service->updateQuote($quote->getId(), 'phpunit-quote-7', 'sent', $this->projectId, null, null, null, $this->user);
		$this->assertNotNull($sent->getDocumentFileId());
	}

	public function testRemovePosition(): void {
		$quote = $this->service->createQuote('phpunit-quote-6', $this->projectId, null, null);
		$position = $this->service->addPosition($quote->getId(), null, 'custom', null, 'x', 1.0, 'Stk', 1.0, 19.0);

		$this->service->removePosition($quote->getId(), $position->getId());

		$full = $this->service->getFullQuote($quote->getId());
		$this->assertCount(0, $full['positions']);
	}
}
