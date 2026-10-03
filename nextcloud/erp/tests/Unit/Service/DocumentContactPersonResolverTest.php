<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Contacts\ContactRole;
use OCA\ERP\Db\ContactLink;
use OCA\ERP\Db\ContactLinkMapper;
use OCA\ERP\Db\ContactPerson;
use OCA\ERP\Db\ContactPersonDefaultMapper;
use OCA\ERP\Db\ContactPersonMapper;
use OCA\ERP\Db\ProjectContactOverrideMapper;
use OCA\ERP\Documents\DocumentType;
use OCA\ERP\Service\ContactPersonDefaultService;
use OCA\ERP\Service\DocumentContactPersonResolver;
use OCA\ERP\Service\ProjectContactOverrideService;
use OCP\IDBConnection;
use Test\TestCase;

/**
 * @group DB
 */
final class DocumentContactPersonResolverTest extends TestCase {
	private const TEST_UID = 'phpunit-cpresolver-1';
	private const PROJECT_ID = 424242;

	private DocumentContactPersonResolver $resolver;
	private ContactPersonDefaultService $defaults;
	private ProjectContactOverrideService $overrides;
	private ContactLinkMapper $linkMapper;
	private ContactPersonMapper $personMapper;
	private ContactPersonDefaultMapper $defaultMapper;
	private ProjectContactOverrideMapper $overrideMapper;
	private int $linkId;
	private int $projectLeaderId;
	private int $accountingId;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->linkMapper = new ContactLinkMapper($db);
		$this->personMapper = new ContactPersonMapper($db);
		$this->defaultMapper = new ContactPersonDefaultMapper($db);
		$this->overrideMapper = new ProjectContactOverrideMapper($db);
		$this->resolver = new DocumentContactPersonResolver($this->overrideMapper, $this->defaultMapper, $this->linkMapper, $this->personMapper);
		$this->defaults = new ContactPersonDefaultService($this->defaultMapper, $this->linkMapper, $this->personMapper);
		// ProjectContactOverrideService braucht ein echtes Projekt für set();
		// hier genügt der direkte Mapper-Zugriff, da das Projekt selbst für
		// den Resolver irrelevant ist (er liest nur contact_person_id).
		$this->overrides = new ProjectContactOverrideService(
			$this->overrideMapper,
			new \OCA\ERP\Db\ProjectMapper($db),
			$this->linkMapper,
			$this->personMapper,
		);

		$now = time();
		$link = new ContactLink();
		$link->setContactUid(self::TEST_UID);
		$link->setRole(ContactRole::Customer->value);
		$link->setCreatedAt($now);
		$link->setUpdatedAt($now);
		$this->linkId = $this->linkMapper->insert($link)->getId();

		$this->projectLeaderId = $this->createPerson('Finn Könnecker');
		$this->accountingId = $this->createPerson('Katharina Schmidt');
	}

	protected function tearDown(): void {
		foreach ($this->overrideMapper->findByProject(self::PROJECT_ID) as $o) {
			$this->overrideMapper->delete($o);
		}
		foreach ($this->defaultMapper->findByContactLink($this->linkId) as $d) {
			$this->defaultMapper->delete($d);
		}
		foreach ($this->personMapper->findByContactLink($this->linkId) as $p) {
			$this->personMapper->delete($p);
		}
		$link = $this->linkMapper->findById($this->linkId);
		if ($link !== null) {
			$this->linkMapper->delete($link);
		}
		parent::tearDown();
	}

	private function createPerson(string $name): int {
		$now = time();
		$person = new ContactPerson();
		$person->setContactLinkId($this->linkId);
		$person->setName($name);
		$person->setCreatedAt($now);
		$person->setUpdatedAt($now);
		return $this->personMapper->insert($person)->getId();
	}

	/** Direktes Setzen der Override-Zeile, ohne auf ein echtes Projekt angewiesen zu sein. */
	private function setRawOverride(int $personId, DocumentType $type): void {
		$now = time();
		$override = new \OCA\ERP\Db\ProjectContactOverride();
		$override->setProjectId(self::PROJECT_ID);
		$override->setDocumentType($type->value);
		$override->setContactPersonId($personId);
		$override->setCreatedAt($now);
		$override->setUpdatedAt($now);
		$this->overrideMapper->insert($override);
	}

	public function testReturnsNullWhenNeitherOverrideNorDefaultIsSet(): void {
		self::assertNull($this->resolver->resolveName(self::PROJECT_ID, self::TEST_UID, DocumentType::Invoice));
	}

	public function testFallsBackToCustomerDefaultWhenNoOverride(): void {
		$this->defaults->set($this->linkId, DocumentType::Invoice, $this->accountingId);

		self::assertSame('Katharina Schmidt', $this->resolver->resolveName(self::PROJECT_ID, self::TEST_UID, DocumentType::Invoice));
	}

	public function testOverrideTakesPrecedenceOverCustomerDefault(): void {
		$this->defaults->set($this->linkId, DocumentType::Invoice, $this->accountingId);
		$this->setRawOverride($this->projectLeaderId, DocumentType::Invoice);

		self::assertSame('Finn Könnecker', $this->resolver->resolveName(self::PROJECT_ID, self::TEST_UID, DocumentType::Invoice));
	}

	public function testDifferentDocumentTypesResolveIndependently(): void {
		$this->defaults->set($this->linkId, DocumentType::Invoice, $this->accountingId);
		$this->defaults->set($this->linkId, DocumentType::Quote, $this->projectLeaderId);

		self::assertSame('Katharina Schmidt', $this->resolver->resolveName(self::PROJECT_ID, self::TEST_UID, DocumentType::Invoice));
		self::assertSame('Finn Könnecker', $this->resolver->resolveName(self::PROJECT_ID, self::TEST_UID, DocumentType::Quote));
		self::assertNull($this->resolver->resolveName(self::PROJECT_ID, self::TEST_UID, DocumentType::DeliveryNote));
	}

	public function testReturnsNullWithoutCustomerContactUid(): void {
		$this->defaults->set($this->linkId, DocumentType::Invoice, $this->accountingId);

		self::assertNull($this->resolver->resolveName(self::PROJECT_ID, null, DocumentType::Invoice));
	}
}
