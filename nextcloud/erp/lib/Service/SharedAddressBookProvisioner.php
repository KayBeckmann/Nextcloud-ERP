<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\DAV\CardDAV\CardDavBackend;
use OCA\DAV\CardDAV\Sharing\Service as AddressBookSharingService;
use OCA\DAV\DAV\Sharing\Backend as SharingBackend;
use OCP\IConfig;
use OCP\IUserSession;

/**
 * Idempotently owns the ERP-native Contacts addressbook lifecycle. Contacts
 * remain the source of truth; this only creates the two containers and repairs
 * their role shares when they are absent or incomplete.
 */
class SharedAddressBookProvisioner {
	private const OWNER_CONFIG_KEY = 'shared_addressbook_owner';

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
		private IConfig $config,
		private IUserSession $userSession,
	) {
	}

	/**
	 * Uses the stored ERP addressbook owner. On the first Contacts request the
	 * authenticated user becomes the stable owner; no installation-specific
	 * hard-coded `admin` account is assumed.
	 */
	public function ensure(): void {
		$ownerUserId = $this->config->getAppValue('erp', self::OWNER_CONFIG_KEY, '');
		if ($ownerUserId === '') {
			$user = $this->userSession->getUser();
			if ($user === null) {
				throw new \RuntimeException('No shared addressbook owner is configured; open ERP Contacts once as the intended owner.');
			}
			$ownerUserId = $user->getUID();
			$this->config->setAppValue('erp', self::OWNER_CONFIG_KEY, $ownerUserId);
		}
		$this->ensureFor($ownerUserId);
	}

	/** @internal Explicit owner is used by unit tests and controlled repairs. */
	public function ensureFor(string $ownerUserId): void {
		if (trim($ownerUserId) === '') {
			throw new \InvalidArgumentException('Addressbook owner must not be empty');
		}
		$principalUri = 'principals/users/' . $ownerUserId;
		foreach (self::ADDRESS_BOOKS as $uri => $configuration) {
			$addressBook = $this->cardDavBackend->getAddressBooksByUri($principalUri, $uri);
			$addressBookId = $addressBook === null
				? (int) $this->cardDavBackend->createAddressBook($principalUri, $uri, [
					'{DAV:}displayname' => $configuration['displayName'],
				])
				: (int) $addressBook['id'];

			foreach ($configuration['shares'] as $group => $access) {
				$this->sharingService->shareWith($addressBookId, 'principal:principals/groups/' . $group, $access);
			}
		}
	}
}
