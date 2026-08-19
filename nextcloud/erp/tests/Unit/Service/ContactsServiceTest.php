<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Contacts\ContactRole;
use OCA\ERP\Db\ContactLinkMapper;
use OCA\ERP\Service\ContactsService;
use OCP\Contacts\IManager as IContactsManager;
use OCP\IDBConnection;
use Test\TestCase;

/**
 * @group DB
 */
final class ContactsServiceTest extends TestCase {
	private const TEST_UID = 'phpunit-contact-1';

	private ContactsService $service;
	private ContactLinkMapper $mapper;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->mapper = new ContactLinkMapper($db);

		$contactsManager = $this->createMock(IContactsManager::class);
		$contactsManager->method('search')->willReturnCallback(function ($pattern, $props) {
			if (in_array('UID', $props, true) && $pattern === self::TEST_UID) {
				return [['UID' => self::TEST_UID, 'FN' => 'PHPUnit Contact']];
			}
			return [];
		});

		$this->service = new ContactsService($this->mapper, $contactsManager);
	}

	protected function tearDown(): void {
		foreach ([ContactRole::Customer, ContactRole::Supplier] as $role) {
			foreach ($this->mapper->findByRole($role->value) as $link) {
				if (str_starts_with($link->getContactUid(), 'phpunit-')) {
					$this->mapper->delete($link);
				}
			}
		}
		parent::tearDown();
	}

	/** Filtert auf unsere Test-UID, weil die Dev-DB auch manuell angelegte Links enthalten kann. */
	private function ownLinks(ContactRole $role): array {
		return array_values(array_filter(
			$this->service->listLinks($role),
			static fn (array $l) => $l['contactUid'] === self::TEST_UID,
		));
	}

	public function testCreateAndListLinkResolvesDisplayName(): void {
		$this->service->createLink(self::TEST_UID, ContactRole::Customer, 'K-1', 30, 'note');

		$links = $this->ownLinks(ContactRole::Customer);
		$this->assertCount(1, $links);
		$this->assertSame('PHPUnit Contact', $links[0]['displayName']);
		$this->assertSame('K-1', $links[0]['referenceNumber']);
	}

	public function testSameContactCanBeCustomerAndSupplier(): void {
		$this->service->createLink(self::TEST_UID, ContactRole::Customer, null, null, null);
		$this->service->createLink(self::TEST_UID, ContactRole::Supplier, null, null, null);

		$this->assertCount(1, $this->ownLinks(ContactRole::Customer));
		$this->assertCount(1, $this->ownLinks(ContactRole::Supplier));
	}

	public function testDuplicateLinkForSameRoleIsRejected(): void {
		$this->service->createLink(self::TEST_UID, ContactRole::Customer, null, null, null);
		$this->expectException(\InvalidArgumentException::class);
		$this->service->createLink(self::TEST_UID, ContactRole::Customer, null, null, null);
	}

	public function testUpdateUnknownLinkThrows(): void {
		$this->expectException(\OutOfBoundsException::class);
		$this->service->updateLink(999999999, 'x', null, null);
	}

	public function testDeleteUnknownLinkThrows(): void {
		$this->expectException(\OutOfBoundsException::class);
		$this->service->deleteLink(999999999);
	}

	public function testGetLinkRole(): void {
		$link = $this->service->createLink(self::TEST_UID, ContactRole::Supplier, null, null, null);
		$this->assertSame(ContactRole::Supplier, $this->service->getLinkRole($link->getId()));
		$this->assertNull($this->service->getLinkRole(999999999));
	}
}
