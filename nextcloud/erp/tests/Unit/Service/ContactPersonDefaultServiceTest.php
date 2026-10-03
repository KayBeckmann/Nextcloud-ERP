<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Contacts\ContactRole;
use OCA\ERP\Db\ContactLink;
use OCA\ERP\Db\ContactLinkMapper;
use OCA\ERP\Db\ContactPersonDefaultMapper;
use OCA\ERP\Db\ContactPersonMapper;
use OCA\ERP\Documents\DocumentType;
use OCA\ERP\Service\ContactPersonDefaultService;
use OCP\IDBConnection;
use Test\TestCase;

/**
 * @group DB
 */
final class ContactPersonDefaultServiceTest extends TestCase {
	private const TEST_UID = 'phpunit-cpdefaults-1';

	private ContactPersonDefaultService $service;
	private ContactPersonDefaultMapper $mapper;
	private ContactLinkMapper $linkMapper;
	private ContactPersonMapper $personMapper;
	private int $linkId;
	private int $personId;
	private int $otherLinkId;
	private int $otherPersonId;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->mapper = new ContactPersonDefaultMapper($db);
		$this->linkMapper = new ContactLinkMapper($db);
		$this->personMapper = new ContactPersonMapper($db);
		$this->service = new ContactPersonDefaultService($this->mapper, $this->linkMapper, $this->personMapper);

		$this->linkId = $this->createLink(self::TEST_UID);
		$this->personId = $this->createPerson($this->linkId, 'Lars Zimmermann');
		$this->otherLinkId = $this->createLink('phpunit-cpdefaults-other');
		$this->otherPersonId = $this->createPerson($this->otherLinkId, 'Fremde Person');
	}

	protected function tearDown(): void {
		foreach ($this->mapper->findByContactLink($this->linkId) as $d) {
			$this->mapper->delete($d);
		}
		foreach ([$this->linkId, $this->otherLinkId] as $linkId) {
			foreach ($this->personMapper->findByContactLink($linkId) as $p) {
				$this->personMapper->delete($p);
			}
			$link = $this->linkMapper->findById($linkId);
			if ($link !== null) {
				$this->linkMapper->delete($link);
			}
		}
		parent::tearDown();
	}

	private function createLink(string $uid): int {
		$now = time();
		$link = new ContactLink();
		$link->setContactUid($uid);
		$link->setRole(ContactRole::Customer->value);
		$link->setCreatedAt($now);
		$link->setUpdatedAt($now);
		return $this->linkMapper->insert($link)->getId();
	}

	private function createPerson(int $linkId, string $name): int {
		$now = time();
		$person = new \OCA\ERP\Db\ContactPerson();
		$person->setContactLinkId($linkId);
		$person->setName($name);
		$person->setCreatedAt($now);
		$person->setUpdatedAt($now);
		return $this->personMapper->insert($person)->getId();
	}

	public function testGetForLinkStartsEmpty(): void {
		$map = $this->service->getForLink($this->linkId);

		self::assertSame(array_map(static fn ($t) => $t->value, DocumentType::cases()), array_keys($map));
		self::assertSame([], array_filter($map, static fn ($v) => $v !== null));
	}

	public function testSetAndGetRoundTrips(): void {
		$this->service->set($this->linkId, DocumentType::Invoice, $this->personId);

		self::assertSame($this->personId, $this->service->getForLink($this->linkId)[DocumentType::Invoice->value]);
	}

	public function testSetNullClearsExistingDefault(): void {
		$this->service->set($this->linkId, DocumentType::Invoice, $this->personId);
		$this->service->set($this->linkId, DocumentType::Invoice, null);

		self::assertNull($this->service->getForLink($this->linkId)[DocumentType::Invoice->value]);
	}

	public function testSetOverwritesExistingDefault(): void {
		$second = $this->createPerson($this->linkId, 'Katharina Schmidt');
		$this->service->set($this->linkId, DocumentType::Quote, $this->personId);
		$this->service->set($this->linkId, DocumentType::Quote, $second);

		self::assertSame($second, $this->service->getForLink($this->linkId)[DocumentType::Quote->value]);
	}

	public function testSetRejectsPersonFromAnotherLink(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->set($this->linkId, DocumentType::Invoice, $this->otherPersonId);
	}

	public function testSetRejectsUnknownContactLink(): void {
		$this->expectException(\OutOfBoundsException::class);
		$this->service->set(999999999, DocumentType::Invoice, $this->personId);
	}
}
