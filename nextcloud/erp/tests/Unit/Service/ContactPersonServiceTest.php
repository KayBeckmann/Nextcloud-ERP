<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Contacts\ContactRole;
use OCA\ERP\Db\ContactLink;
use OCA\ERP\Db\ContactLinkMapper;
use OCA\ERP\Db\ContactPersonMapper;
use OCA\ERP\Service\ContactPersonService;
use OCP\IDBConnection;
use Test\TestCase;

/**
 * @group DB
 */
final class ContactPersonServiceTest extends TestCase {
	private const TEST_UID = 'phpunit-contact-persons-1';

	private ContactPersonService $service;
	private ContactPersonMapper $mapper;
	private ContactLinkMapper $linkMapper;
	private int $linkId;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->mapper = new ContactPersonMapper($db);
		$this->linkMapper = new ContactLinkMapper($db);
		$this->service = new ContactPersonService($this->mapper, $this->linkMapper);

		$now = time();
		$link = new ContactLink();
		$link->setContactUid(self::TEST_UID);
		$link->setRole(ContactRole::Customer->value);
		$link->setCreatedAt($now);
		$link->setUpdatedAt($now);
		$this->linkId = $this->linkMapper->insert($link)->getId();
	}

	protected function tearDown(): void {
		foreach ($this->mapper->findByContactLink($this->linkId) as $person) {
			$this->mapper->delete($person);
		}
		$link = $this->linkMapper->findById($this->linkId);
		if ($link !== null) {
			$this->linkMapper->delete($link);
		}
		parent::tearDown();
	}

	public function testCreateAndListReturnsPersonsInCreationOrder(): void {
		$this->service->create($this->linkId, 'Lars Zimmermann', 'CEO', 'lars@example.test', '0123', null);
		$this->service->create($this->linkId, 'Katharina Schmidt', 'Buchhaltung', null, null, null);

		$persons = $this->service->listForLink($this->linkId);

		self::assertCount(2, $persons);
		self::assertSame('Lars Zimmermann', $persons[0]->getName());
		self::assertSame('CEO', $persons[0]->getPosition());
		self::assertSame('Katharina Schmidt', $persons[1]->getName());
	}

	public function testCreateRejectsEmptyName(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->create($this->linkId, '   ', null, null, null, null);
	}

	public function testCreateRejectsUnknownContactLink(): void {
		$this->expectException(\OutOfBoundsException::class);
		$this->service->create(999999999, 'Finn Könnecker', 'Projektleiter', null, null, null);
	}

	public function testUpdateChangesFieldsAndBlankStringsBecomeNull(): void {
		$person = $this->service->create($this->linkId, 'Finn Könnecker', 'Projektleiter', 'finn@example.test', null, null);

		$updated = $this->service->update($person->getId(), 'Finn Könnecker', 'Senior-Projektleiter', '', '', 'neue Notiz');

		self::assertSame('Senior-Projektleiter', $updated->getPosition());
		self::assertNull($updated->getEmail());
		self::assertNull($updated->getPhone());
		self::assertSame('neue Notiz', $updated->getNotes());
	}

	public function testUpdateUnknownPersonThrows(): void {
		$this->expectException(\OutOfBoundsException::class);
		$this->service->update(999999999, 'Irrelevant', null, null, null, null);
	}

	public function testDeleteRemovesPerson(): void {
		$person = $this->service->create($this->linkId, 'Lars Zimmermann', 'CEO', null, null, null);

		$this->service->delete($person->getId());

		self::assertSame([], $this->service->listForLink($this->linkId));
	}

	public function testDeleteUnknownPersonThrows(): void {
		$this->expectException(\OutOfBoundsException::class);
		$this->service->delete(999999999);
	}

	public function testGetRoleForResolvesRoleOfOwningLink(): void {
		$person = $this->service->create($this->linkId, 'Lars Zimmermann', 'CEO', null, null, null);

		self::assertSame(ContactRole::Customer, $this->service->getRoleFor($person->getId()));
	}

	public function testGetRoleForUnknownPersonReturnsNull(): void {
		self::assertNull($this->service->getRoleFor(999999999));
	}

	public function testDeleteAllForLinkRemovesEveryPerson(): void {
		$this->service->create($this->linkId, 'Lars Zimmermann', 'CEO', null, null, null);
		$this->service->create($this->linkId, 'Katharina Schmidt', 'Buchhaltung', null, null, null);

		$this->service->deleteAllForLink($this->linkId);

		self::assertSame([], $this->service->listForLink($this->linkId));
	}
}
