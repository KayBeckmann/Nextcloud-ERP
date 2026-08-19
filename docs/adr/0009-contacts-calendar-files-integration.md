# ADR-0009: Contacts-, Calendar- und Files-Integration

**Status:** accepted
**Datum:** 2026-08-19

## Kontext

Roadmap Phase 3 verlangt, dass die App Nextcloud-native Bausteine (Contacts,
Calendar, Files) sinnvoll nutzt, ohne sie zu duplizieren. Für alle drei
Bereiche verifiziert gegen die tatsächlich installierte Nextcloud-34-API
(`lib/public/Contacts/IManager.php`, `lib/public/Calendar/IManager.php`,
`lib/public/Files/IRootFolder.php`), nicht angenommen.

## Entscheidung

### Contacts

- Suche/Anzeige ausschließlich über `OCP\Contacts\IManager::search()` — kein
  direkter CardDAV-/DB-Zugriff auf die Contacts-App.
- Eigene Tabelle `erp_contact_links` speichert **nur** die Referenz
  (`contact_uid`) plus ERP-eigene Metadaten (Rolle, Referenznummer,
  Zahlungsziel, Notizen) — keine Kopie von Name/Adresse/Telefon. Anzeige holt
  Anzeigenamen live über `IManager::search()` nach.
- Ein Contact kann gleichzeitig Kunde und Lieferant sein → zwei Zeilen
  (unique je `contact_uid` + `role`), nicht ein Feld mit zwei Werten.

### Calendar

- Nextcloud 34 bietet mit `IManager::createEventBuilder()` /
  `ICalendarEventBuilder` / `ICreateFromString::createFromString()` eine
  offizielle, dokumentierte Schreib-API (seit 31.0.0) — kein ICS-Handbau, kein
  direkter CalDAV-Zugriff.
- `ICalendarEventBuilder` erzeugt die Event-UID intern und liefert stattdessen
  den **Dateinamen** des angelegten Events zurück; dieser (nicht eine separate
  UID) wird als `event_uri` gespeichert, weil `ICalendar::search()` genau
  dieses `uri`-Suchkriterium unterstützt.
- Eigene Tabelle `erp_calendar_links` verknüpft **generisch** einen
  ERP-Datensatz (`resource_type` + `resource_id`) mit einem Kalender-Event
  (`calendar_uri` + `event_uri`). Generisch deshalb, weil es in Phase 3 noch
  keine Projekt-/Auftragsentität gibt, an die konkret verknüpft werden könnte
  — Phase 4 nutzt dieselbe Tabelle ohne Schemaänderung.
- Termine anlegen prüft die ERP-Rechte-Matrix (`ResourceType` aus Phase 2) für
  den übergebenen `resourceType`: mindestens `write` nötig. Zeigt, dass
  Rechte- und Integrationsschicht zusammenspielen, statt zwei getrennte
  Sicherheitsmodelle zu pflegen.
- **Nicht** umgesetzt: Ändern/Löschen bestehender Events, Frei/Belegt-Prüfung
  über `checkAvailability()`, Personalplanungs-UI — bewusst nur die
  "vorbereitete Struktur" aus der Roadmap, volle Kalender-UI folgt erst mit
  echten Terminen in späteren Phasen.

### Files

- ERP-Ordnerstruktur wird über `IRootFolder::getUserFolder()` +
  `Folder::newFolder()`/`nodeExists()` im **Home-Verzeichnis des aufrufenden
  Users** angelegt (`ERP/Projekte`, `ERP/Artikel`, `ERP/Produkte`,
  `ERP/Lieferanten`, `ERP/Fuhrpark`, `ERP/Kosten`, `ERP/Vorlagen`,
  `ERP/Archiv` — Struktur aus dem Brainstorming).
- Kein separates `erp_files`-Tracking: Ordner werden idempotent bei jedem
  Aufruf sichergestellt (`nodeExists()`-Check), Referenzierung späterer
  Projektordner erfolgt über Node-IDs, sobald Projekte existieren (Phase 4).

## Konsequenzen

- **Bekannte Einschränkung:** Ein persönliches Home-Verzeichnis ist für ein
  Mehrbenutzer-ERP fachlich fragwürdig (jeder User bekommt seine eigene
  `ERP/`-Struktur statt eine gemeinsame). Für den Nachweis "Bausteine nutzbar"
  ausreichend; ein Wechsel auf Group Folders (App `groupfolders`) oder einen
  fest konfigurierten gemeinsamen Speicherort ist vor Phase 4 zu klären und
  wird dort als eigene Entscheidung nachgezogen, sobald echte Projektordner
  entstehen.
- Termine, die über die ERP-API angelegt werden, laufen im Kalender des
  anlegenden Users — Verknüpfung mit fremden Benutzerkalendern (Monteur
  einplanen) bleibt eine offene Frage aus dem Brainstorming, nicht Teil dieser
  Phase.
- Contact-Suche gibt zurück, was `IManager::search()` liefert (u. a. system
  address book, je nach Serverkonfiguration) — keine eigene Filterung nach
  Adressbuch-Herkunft in dieser Phase.

## Alternativen erwogen

- ICS-String von Hand bauen statt `ICalendarEventBuilder`: unnötig, seit
  Nextcloud 31 gibt es die offizielle, getestete API dafür.
- Contact-Daten in `erp_contact_links` cachen (Name/Mail) für schnellere
  Listenansichten ohne Re-Query: bewusst nicht getan (ADR-Leitplanke "keine
  Schattenkopie ohne fachlichen Grund") — Performance wird erst bei echtem
  Bedarf optimiert.
