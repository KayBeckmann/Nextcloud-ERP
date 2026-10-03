# ADR-0041: Mehrere Ansprechpartner pro Firmenkunde/-lieferant

**Status:** accepted
**Datum:** 2026-10-03

## Kontext

Ein `erp_contact_links`-Eintrag (ADR-0009) bildet "den" Kunden oder
Lieferanten als genau einen Nextcloud-Kontakt ab — ein Name/Firma-Feld,
eine Adresse, eine Telefonnummer, eine E-Mail. Für Firmenkunden mit
mehreren benannten Ansprechpartnern (z. B. "Glogau Bauunternehmen" mit
Geschäftsführung, Buchhaltung und Projektleitung als getrennte
Personen) gab es dafür keine Struktur — nur ein einziges Namensfeld.

## Entscheidung

### Neue Tabelle `erp_contact_persons`, hängt am `contact_link_id`

Ansprechpartner sind reine ERP-Metadaten ohne eigene vCard — anders als
der Firmenkontakt selbst (der weiterhin ein echter Nextcloud-Contact
ist). Die neue Tabelle hängt deshalb am `contact_link_id`
(`erp_contact_links.id`), nicht an einer eigenen Nextcloud-Contact-UID.
Felder: `name` (Pflicht), `position` (freier Text, z. B. "Geschäfts-
führer", "Buchhaltung" — bewusst kein Enum, Kundenstrukturen sind zu
unterschiedlich), `email`, `phone`, `notes` (alle optional).

Kein Fremdschlüssel-Constraint auf DB-Ebene — konsistent mit dem
Rest des Projekts (keine einzige Migration nutzt
`addForeignKeyConstraint`, App-Code sichert die Integrität, siehe z. B.
`InvoiceServiceTest`-Teardown).

### Eigener Dienst statt Erweiterung von `ContactsService`

`ContactPersonService` ist bewusst eigenständig, nicht Teil von
`ContactsService` — dessen Code ist bereits vCard-/CardDAV-Logik
(Adressbücher, native Karten-CRUD, History-Snapshots) und fachlich
unverwandt zu reinen ERP-Metadaten. Dasselbe Trennungsmuster wie
`CustomerContractService` neben `ContactsService` (ADR-0037).

### Cascade beim Löschen der Kunden-/Lieferanten-Verknüpfung

`ContactsController::deleteLink()` ruft jetzt zusätzlich
`ContactPersonService::deleteAllForLink()` auf, bevor der Link selbst
gelöscht wird — Ansprechpartner würden sonst als Datenleiche ohne
erreichbaren Elterndatensatz zurückbleiben. Die native vCard des
Firmenkontakts selbst bleibt davon unberührt (wie bisher bei
`deleteLink()` — nur die ERP-Verknüpfung verschwindet, nicht der
Nextcloud-Kontakt).

### Rechte-Gate folgt der Rolle der Firma, nicht einer eigenen Ressource

Ansprechpartner haben keine eigene `ResourceType`-Berechtigungsstufe.
`listPersons`/`createPerson` prüfen die Rolle der referenzierten
`contactLinkId` (`ContactsService::getLinkRole()`), `updatePerson`/
`deletePerson` die Rolle über den neuen
`ContactPersonService::getRoleFor()` (Ansprechpartner →
`contact_link_id` → Rolle) — exakt dasselbe Muster wie
`updateLink`/`deleteLink` für die Firmenverknüpfung selbst.

### API

Unter dem bestehenden `ContactsController` (kein neuer Controller
nötig):

- `GET /api/v1/contacts/links/{contactLinkId}/persons`
- `POST /api/v1/contacts/links/{contactLinkId}/persons`
- `PUT /api/v1/contacts/links/persons/{id}`
- `DELETE /api/v1/contacts/links/persons/{id}`

## Konsequenzen

- Ein Firmenkunde kann jetzt beliebig viele benannte Ansprechpartner
  mit Position/Kontaktdaten erfassen, verwaltet direkt in der
  bestehenden Kontaktverwaltung.
- Dokumente (Angebot/Auftrag/Rechnung) referenzieren weiterhin genau
  **einen** Kontakt als "den Kunden" — Ansprechpartner sind reine
  Stammdaten-Zusatzinfo, erscheinen nicht automatisch als "z. Hd."-Zeile
  auf erzeugten Belegen (siehe "Nicht Teil dieser Phase").

## Nicht Teil dieser Phase

- Keine Auswahl eines bestimmten Ansprechpartners je Angebot/Auftrag/
  Rechnung (kein "z. Hd. Herr Zimmermann" auf dem Beleg-PDF) — Dokumente
  bleiben an den einen Firmenkontakt gebunden (ADR-0022).
- Keine eigene vCard pro Ansprechpartner, keine Synchronisation mit
  Nextcloud Contacts (z. B. über `ORG`/`RELATED`) — wer die
  Ansprechpartner auch als eigene, suchbare Nextcloud-Kontakte braucht,
  legt sie zusätzlich dort manuell an; beide Datenhaltungen sind
  unverknüpft.
- Keine Mehrfachrollen (ein Ansprechpartner kann nicht gleichzeitig bei
  mehreren Firmenkontakten gelistet sein) — `contact_link_id` ist eine
  einfache 1:n-Beziehung.
