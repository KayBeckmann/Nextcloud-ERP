<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Contacts\ContactRole;
use OCA\ERP\Db\ContactLink;
use OCA\ERP\Db\ContactLinkMapper;
use OCP\Contacts\IManager as IContactsManager;
use OCP\IAddressBook;

/**
 * Wrapper um OCP\Contacts\IManager + erp_contact_links (ADR-0009). Speichert
 * bewusst nur die Contact-UID + ERP-Metadaten, keine Kopie von Name/E-Mail —
 * Anzeigenamen werden bei Bedarf live nachgeschlagen.
 */
class ContactsService {
	public function __construct(
		private ContactLinkMapper $mapper,
		private IContactsManager $contactsManager,
	) {
	}

	/** @return list<array{uid: string, displayName: string, emails: list<string>}> */
	public function search(string $pattern, int $limit = 20): array {
		$results = $this->contactsManager->search($pattern, ['FN', 'EMAIL'], ['limit' => $limit]);

		$contacts = [];
		foreach ($results as $r) {
			$uid = $r['UID'] ?? null;
			if ($uid === null) {
				continue; // ohne UID nicht referenzierbar
			}
			$emails = $r['EMAIL'] ?? [];
			if (!is_array($emails)) {
				$emails = [$emails];
			}
			$contacts[] = [
				'uid' => $uid,
				'displayName' => $r['FN'] ?? $uid,
				'emails' => array_values($emails),
			];
		}
		return $contacts;
	}

	private static function addressBookUriForRole(ContactRole $role): string {
		return $role === ContactRole::Customer ? 'erp-kunden' : 'erp-lieferanten';
	}

	/**
	 * Liefert ausschließlich das für die ERP-Rolle vorgesehene, für den
	 * aktuellen Nextcloud-User sichtbare Adressbuch. Die technische Key-ID wird
	 * bewusst erst aus dessen öffentlicher IAddressBook-Repräsentation bezogen;
	 * sie ist installationsspezifisch und wird nie im ERP gespeichert.
	 *
	 * @throws \OutOfBoundsException wenn das Adressbuch nicht provisioniert,
	 *   nicht geteilt oder für den Benutzer nicht sichtbar ist.
	 */
	private function addressBookForRole(ContactRole $role): IAddressBook {
		$expectedUri = self::addressBookUriForRole($role);
		foreach ($this->contactsManager->getUserAddressBooks() as $addressBook) {
			if ($addressBook->getUri() === $expectedUri) {
				return $addressBook;
			}
		}

		throw new \OutOfBoundsException("Dedicated address book '$expectedUri' is not visible");
	}

	/**
	 * Erstellt eine neue vCard im ausschließlich zur Rolle gehörenden
	 * Nextcloud-Adressbuch. Es werden keine Stammdaten ins ERP geschrieben;
	 * IAddressBook::createOrUpdate() ist die öffentliche Contacts-API und
	 * prüft die DAV-Schreibrechte des aktuellen Benutzers selbst.
	 *
	 * @param array{fullName: string, email?: string, phone?: string, address?: string} $fields
	 * @return array{uid: string, displayName: string, emails: list<string>, uri: string}
	 * @throws \InvalidArgumentException|\OutOfBoundsException
	 */
	public function createCard(ContactRole $role, array $fields): array {
		$fullName = trim((string) ($fields['fullName'] ?? ''));
		if ($fullName === '') {
			throw new \InvalidArgumentException('fullName must not be empty');
		}

		$properties = ['FN' => $fullName];
		foreach (['email' => 'EMAIL', 'phone' => 'TEL', 'address' => 'ADR'] as $field => $property) {
			$value = trim((string) ($fields[$field] ?? ''));
			if ($value !== '') {
				$properties[$property] = [$value];
			}
		}

		$card = $this->addressBookForRole($role)->createOrUpdate($properties);
		if (!is_array($card) || !isset($card['UID'], $card['URI'])) {
			throw new \RuntimeException('Nextcloud Contacts did not create the contact card');
		}

		return $this->cardResponse($card);
	}

	/**
	 * Aktualisiert eine vorhandene vCard ausschließlich, wenn sie im passenden
	 * dedizierten Adressbuch liegt. Eine UID aus einem privaten oder fremden
	 * Adressbuch kann damit nicht über die ERP-API verändert werden.
	 *
	 * @param array{fullName: string, email?: string, phone?: string, address?: string} $fields
	 * @return array{uid: string, displayName: string, emails: list<string>, uri: string}
	 * @throws \InvalidArgumentException|\OutOfBoundsException
	 */
	public function updateCard(ContactRole $role, string $contactUid, array $fields): array {
		$addressBook = $this->addressBookForRole($role);
		$existing = null;
		foreach ($addressBook->search($contactUid, ['UID'], []) as $card) {
			if (($card['UID'] ?? null) === $contactUid) {
				$existing = $card;
				break;
			}
		}
		if ($existing === null || !isset($existing['URI'])) {
			throw new \OutOfBoundsException("Contact $contactUid is not in the dedicated {$role->value} address book");
		}

		$fullName = trim((string) ($fields['fullName'] ?? ''));
		if ($fullName === '') {
			throw new \InvalidArgumentException('fullName must not be empty');
		}
		$properties = [
			'UID' => $contactUid,
			'URI' => (string) $existing['URI'],
			'FN' => $fullName,
		];
		foreach (['email' => 'EMAIL', 'phone' => 'TEL', 'address' => 'ADR'] as $field => $property) {
			if (array_key_exists($field, $fields)) {
				$value = trim((string) $fields[$field]);
				$properties[$property] = $value === '' ? [] : [$value];
			}
		}

		$card = $addressBook->createOrUpdate($properties);
		if (!is_array($card) || !isset($card['UID'], $card['URI'])) {
			throw new \RuntimeException('Nextcloud Contacts did not update the contact card');
		}
		return $this->cardResponse($card);
	}

	/**
	 * Listet nur die live vCards des rollenfesten, sichtbaren Adressbuchs.
	 * Die Kontaktdaten bleiben damit bei Nextcloud Contacts; die ERP-UI erhält
	 * genau die Felder, die sie im selben nativen Datensatz bearbeiten darf.
	 *
	 * @return list<array{uid: string, displayName: string, email: string, phone: string, address: string, uri: string}>
	 * @throws \OutOfBoundsException
	 */
	public function listCards(ContactRole $role): array {
		$cards = [];
		foreach ($this->addressBookForRole($role)->search('', ['FN', 'EMAIL'], []) as $card) {
			if (!is_array($card) || !isset($card['UID'], $card['URI'])) {
				continue;
			}
			$cards[] = $this->editableCardResponse($card);
		}
		return $cards;
	}

	/** @param array<string, mixed> $card @return array{uid: string, displayName: string, email: string, phone: string, address: string, uri: string} */
	private function editableCardResponse(array $card): array {
		$firstValue = static function (mixed $value): string {
			if (is_array($value)) {
				$value = $value[0] ?? '';
			}
			return (string) $value;
		};
		return [
			'uid' => (string) $card['UID'],
			'displayName' => (string) ($card['FN'] ?? $card['UID']),
			'email' => $firstValue($card['EMAIL'] ?? ''),
			'phone' => $firstValue($card['TEL'] ?? ''),
			'address' => $firstValue($card['ADR'] ?? ''),
			'uri' => (string) $card['URI'],
		];
	}

	/** @param array<string, mixed> $card @return array{uid: string, displayName: string, emails: list<string>, uri: string} */
	private function cardResponse(array $card): array {
		$emails = $card['EMAIL'] ?? [];
		if (!is_array($emails)) {
			$emails = [$emails];
		}
		return [
			'uid' => (string) $card['UID'],
			'displayName' => (string) ($card['FN'] ?? $card['UID']),
			'emails' => array_values(array_map('strval', $emails)),
			'uri' => (string) $card['URI'],
		];
	}

	/** Prüft, ob der aktuelle Benutzer diesen Contact per UID sehen darf. */
	public function exists(string $contactUid): bool {
		foreach ($this->contactsManager->search($contactUid, ['UID']) as $contact) {
			if (($contact['UID'] ?? null) === $contactUid) {
				return true;
			}
		}
		return false;
	}

	public function displayNameFor(string $contactUid): string {
		foreach ($this->contactsManager->search($contactUid, ['UID']) as $r) {
			if (($r['UID'] ?? null) === $contactUid) {
				return $r['FN'] ?? $contactUid;
			}
		}
		return $contactUid;
	}

	/**
	 * Anzeigename + Anschrift für den Kundenblock im Beleg-PDF (ADR-0022).
	 * Nutzt die im Kontakt bereits gepflegte Adresse (vCard-ADR-Feld) — keine
	 * eigene Adress-Datenhaltung im ERP-Schema. `search()` mit `['UID']` als
	 * Suchfeld liefert trotzdem den vollständigen vCard-Datensatz zurück
	 * (die Suchfelder schränken nur ein, wonach gesucht wird, nicht was im
	 * Ergebnis mitkommt) — dasselbe Muster wie displayNameFor().
	 *
	 * @return array{displayName: string, addressLines: list<string>}
	 */
	public function detailsFor(string $contactUid): array {
		foreach ($this->contactsManager->search($contactUid, ['UID']) as $r) {
			if (($r['UID'] ?? null) !== $contactUid) {
				continue;
			}
			$adr = $r['ADR'] ?? [];
			return [
				'displayName' => $r['FN'] ?? $contactUid,
				'addressLines' => $this->addressLinesFromVCardAdr(is_array($adr) ? $adr : [$adr]),
			];
		}
		return ['displayName' => $contactUid, 'addressLines' => []];
	}

	/**
	 * @param list<string> $adrValues Rohe vCard-ADR-Werte, je Eintrag
	 *     "Postfach;Zusatz;Straße;Ort;Region;PLZ;Land" (vCard-3/4-Struktur)
	 * @return list<string>
	 */
	private function addressLinesFromVCardAdr(array $adrValues): array {
		if ($adrValues === []) {
			return [];
		}
		// Nur die erste hinterlegte Adresse — ein Kontakt kann mehrere
		// haben (privat/geschäftlich), das PDF zeigt nur eine.
		$parts = explode(';', (string) $adrValues[0]);
		$street = trim($parts[2] ?? '');
		$city = trim($parts[3] ?? '');
		$region = trim($parts[4] ?? '');
		$postalCode = trim($parts[5] ?? '');
		$country = trim($parts[6] ?? '');

		$lines = [];
		if ($street !== '') {
			$lines[] = $street;
		}
		$cityLine = trim($postalCode . ' ' . $city);
		if ($cityLine !== '') {
			$lines[] = $cityLine;
		}
		if ($region !== '' && $region !== $city) {
			$lines[] = $region;
		}
		if ($country !== '') {
			$lines[] = $country;
		}
		return $lines;
	}

	/** Rolle eines bestehenden Links nachschlagen (für Rechte-Prüfung vor Update/Delete). */
	public function getLinkRole(int $id): ?ContactRole {
		$link = $this->mapper->findById($id);
		return $link === null ? null : ContactRole::from($link->getRole());
	}

	/** @return list<array> */
	public function listLinks(ContactRole $role): array {
		return array_map(
			fn (ContactLink $link) => array_merge(
				$link->jsonSerialize(),
				['displayName' => $this->displayNameFor($link->getContactUid())],
			),
			$this->mapper->findByRole($role->value),
		);
	}

	/**
	 * @throws \InvalidArgumentException wenn der Contact in dieser Rolle bereits verknüpft ist
	 */
	public function createLink(
		string $contactUid,
		ContactRole $role,
		?string $referenceNumber,
		?int $paymentTermsDays,
		?string $notes,
	): ContactLink {
		if (trim($contactUid) === '' || !$this->exists($contactUid)) {
			throw new \InvalidArgumentException("Contact $contactUid is not visible in Nextcloud Contacts");
		}
		if ($this->mapper->findOneByContactAndRole($contactUid, $role->value) !== null) {
			throw new \InvalidArgumentException("Contact $contactUid is already linked as {$role->value}");
		}

		$now = time();
		$link = new ContactLink();
		$link->setContactUid($contactUid);
		$link->setRole($role->value);
		$link->setReferenceNumber($referenceNumber);
		$link->setPaymentTermsDays($paymentTermsDays);
		$link->setNotes($notes);
		$link->setCreatedAt($now);
		$link->setUpdatedAt($now);
		return $this->mapper->insert($link);
	}

	/**
	 * @throws \OutOfBoundsException wenn der Link nicht existiert
	 */
	public function updateLink(int $id, ?string $referenceNumber, ?int $paymentTermsDays, ?string $notes): ContactLink {
		$link = $this->mapper->findById($id);
		if ($link === null) {
			throw new \OutOfBoundsException("Contact link $id not found");
		}
		$link->setReferenceNumber($referenceNumber);
		$link->setPaymentTermsDays($paymentTermsDays);
		$link->setNotes($notes);
		$link->setUpdatedAt(time());
		return $this->mapper->update($link);
	}

	/**
	 * @throws \OutOfBoundsException wenn der Link nicht existiert
	 */
	public function deleteLink(int $id): void {
		$link = $this->mapper->findById($id);
		if ($link === null) {
			throw new \OutOfBoundsException("Contact link $id not found");
		}
		$this->mapper->delete($link);
	}
}
