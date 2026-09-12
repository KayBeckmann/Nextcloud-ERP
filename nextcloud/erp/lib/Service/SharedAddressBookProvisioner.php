<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\DAV\CardDAV\CardDavBackend;
use OCA\DAV\CardDAV\Sharing\Service as AddressBookSharingService;
use OCA\DAV\DAV\Sharing\Backend as SharingBackend;

/**
 * Idempotently owns the ERP-native Contacts addressbook lifecycle. Contacts
 * remain the source of truth; this only creates the two containers and repairs
 * their role shares when they are absent or incomplete.
 */
class SharedAddressBookProvisioner {
	private const OWNER_USER_ID = 'admin';

	/** @var array<string, array{displayName: string, shares: array<string, int>}> */
	private const ADDRESS_BOOKS = [
		'erp-kunden' => [
			'displayName' => 'ERP Kunden',
			'shares' => [
				'erp-projektleiter' => SharingBackend::ACCESS_READ_WRITE,
				'erp-monteure' => SharingBackend::ACCESS_READ,
			],
		],
		'erp-lieferanten' => [
			'displayName' => 'ERP Lieferanten',
			'shares' => [
				'erp-projektleiter' => SharingBackend::ACCESS_READ_WRITE,
			],
		],
	];

	public function __construct(
		private CardDavBackend $cardDavBackend,
		private AddressBookSharingService $sharingService,
	) {
	}

	/**
	 * Creates missing dedicated role addressbooks and (re)applies least-privilege
	 * group shares. The DAV sharing service makes repeated calls safe.
	 */
	public function ensure(): void {
		$principalUri = 'principals/users/' . self::OWNER_USER_ID;
		foreach (self::ADDRESS_BOOKS as $uri => $configuration) {
			$addressBook = $this->cardDavBackend->getAddressBooksByUri($principalUri, $uri);
			$addressBookId = $addressBook === null
				? (int) $this->cardDavBackend->createAddressBook($principalUri, $uri, [
					'{DAV:}displayname' => $configuration['displayName'],
				])
				: (int) $addressBook['id'];

			foreach ($configuration['shares'] as $group => $access) {
				$this->sharingService->shareWith(
					$addressBookId,
					'principal:principals/groups/' . $group,
					$access,
				);
			}
		}
	}
}
