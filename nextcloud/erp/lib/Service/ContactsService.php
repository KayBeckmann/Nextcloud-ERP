<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Contacts\ContactRole;
use OCA\ERP\Db\ContactLink;
use OCA\ERP\Db\ContactLinkMapper;
use OCP\Contacts\IManager as IContactsManager;

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
