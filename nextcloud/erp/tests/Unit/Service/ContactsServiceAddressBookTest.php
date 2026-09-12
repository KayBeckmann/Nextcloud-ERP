<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Contacts\ContactRole;
use OCA\ERP\Db\ContactLinkMapper;
use OCA\ERP\Service\ContactsService;
use OCA\ERP\Service\SharedAddressBookProvisioner;
use OCP\Contacts\IManager as IContactsManager;
use OCP\IAddressBook;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ContactsServiceAddressBookTest extends TestCase {
	private IContactsManager&MockObject $contactsManager;
	private IAddressBook&MockObject $customerBook;
	private ContactsService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->contactsManager = $this->createMock(IContactsManager::class);
		$this->customerBook = $this->createMock(IAddressBook::class);
		$this->customerBook->method('getUri')->willReturn('erp-kunden');
		$this->customerBook->method('getKey')->willReturn('42');
		$this->customerBook->method('getDisplayName')->willReturn('ERP Kunden');
		$this->customerBook->method('getPermissions')->willReturn(15);
		$this->contactsManager->method('getUserAddressBooks')->willReturn([$this->customerBook]);
		$this->service = new ContactsService(
			$this->createMock(ContactLinkMapper::class),
			$this->contactsManager,
		);
	}

	public function testCreateCardUsesOnlyTheDedicatedCustomerAddressBook(): void {
		$this->customerBook->expects($this->once())
			->method('createOrUpdate')
			->with([
				'FN' => 'ACME GmbH',
				'EMAIL' => ['kontakt@acme.test'],
				'TEL' => ['+49 30 123'],
				'ADR' => [';;Musterstraße 1;Berlin;;10115;Deutschland'],
			])
			->willReturn([
				'UID' => 'native-contact-1',
				'URI' => 'native-contact-1.vcf',
				'FN' => 'ACME GmbH',
			]);

		$contact = $this->service->createCard(ContactRole::Customer, [
			'fullName' => 'ACME GmbH',
			'email' => 'kontakt@acme.test',
			'phone' => '+49 30 123',
			'address' => ';;Musterstraße 1;Berlin;;10115;Deutschland',
		]);

		$this->assertSame('native-contact-1', $contact['uid']);
		$this->assertSame('ACME GmbH', $contact['displayName']);
	}

	public function testCreateCardRejectsWhenTheDedicatedAddressBookIsNotVisible(): void {
		$this->contactsManager->method('getUserAddressBooks')->willReturn([]);

		$this->expectException(\OutOfBoundsException::class);
		$this->service->createCard(ContactRole::Supplier, ['fullName' => 'Hidden supplier']);
	}

	public function testUpdateCardOnlyUpdatesACardFoundInTheDedicatedAddressBook(): void {
		$this->customerBook->expects($this->once())
			->method('search')
			->with('native-contact-1', ['UID'], [])
			->willReturn([[
				'UID' => 'native-contact-1',
				'URI' => 'native-contact-1.vcf',
				'FN' => 'Old name',
				'EMAIL' => ['old@acme.test'],
			]]);
		$this->customerBook->expects($this->once())
			->method('createOrUpdate')
			->with([
				'UID' => 'native-contact-1',
				'URI' => 'native-contact-1.vcf',
				'FN' => 'New name',
				'EMAIL' => ['new@acme.test'],
			])
			->willReturn([
				'UID' => 'native-contact-1',
				'URI' => 'native-contact-1.vcf',
				'FN' => 'New name',
				'EMAIL' => ['new@acme.test'],
			]);

		$updated = $this->service->updateCard(ContactRole::Customer, 'native-contact-1', [
			'fullName' => 'New name',
			'email' => 'new@acme.test',
		]);

		$this->assertSame('New name', $updated['displayName']);
	}

	public function testUpdateCardRejectsAContactOutsideTheDedicatedAddressBook(): void {
		$this->customerBook->method('search')->willReturn([]);

		$this->expectException(\OutOfBoundsException::class);
		$this->service->updateCard(ContactRole::Customer, 'personal-contact', ['fullName' => 'Nope']);
	}

	public function testListCardsReturnsEditableFieldsFromOnlyTheDedicatedAddressBook(): void {
		$this->customerBook->expects($this->once())
			->method('search')
			->with('', ['FN', 'EMAIL'], [])
			->willReturn([[
				'UID' => 'customer-1',
				'URI' => 'customer-1.vcf',
				'FN' => 'ACME GmbH',
				'EMAIL' => ['kontakt@acme.test'],
				'TEL' => ['+49 30 123'],
				'ADR' => [';;Musterstraße 1;Berlin;;10115;Deutschland'],
			]]);

		$this->assertSame([[
			'uid' => 'customer-1',
			'displayName' => 'ACME GmbH',
			'email' => 'kontakt@acme.test',
			'phone' => '+49 30 123',
			'address' => ';;Musterstraße 1;Berlin;;10115;Deutschland',
			'uri' => 'customer-1.vcf',
		]], $this->service->listCards(ContactRole::Customer));
	}

	public function testListCardsEnsuresSharedRoleAddressBooksBeforeLookingThemUp(): void {
		$provisioner = $this->createMock(SharedAddressBookProvisioner::class);
		$provisioner->expects($this->once())->method('ensure');
		$this->customerBook->method('search')->willReturn([]);
		$service = new ContactsService(
			$this->createMock(ContactLinkMapper::class),
			$this->contactsManager,
			$provisioner,
		);

		self::assertSame([], $service->listCards(ContactRole::Customer));
	}
}
