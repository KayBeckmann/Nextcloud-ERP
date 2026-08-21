# Baufortschritt

Kurzer, laufend aktualisierter Stand — Details/Begründungen stehen in
[`roadmap.md`](roadmap.md) und den [ADRs](adr/).

## 2026-08-19 — Phase 0 + Phase 1 (Skeleton)

**Erledigt:**

- Monorepo-Struktur (`nextcloud/erp/`, `docs/`, `docker/`, `tests/`, `client/flutter/`)
- MIT-Lizenz, README mit Ziel/Scope/Nicht-Zielen
- 7 ADRs für die aus der Roadmap offenen Kernentscheidungen (App-ID, Nextcloud-Version,
  Datenbank, Frontend-Stack, Docker/Tests, Monorepo, Lizenz)
- Docker-Compose-Testumgebung: Nextcloud + PostgreSQL, `.env.example`, dokumentierte
  Start-/Test-Befehle in `docker/README.md`
- Nextcloud-App-Skeleton `erp`:
  - `appinfo/info.xml`, `appinfo/routes.php` (Seiten-Route + erster API-v1-Endpunkt)
  - `lib/AppInfo/Application.php`, `PageController`, `ApiController` (`GET /api/v1/status`)
  - erste Migration (`erp_app_meta`-Tabelle) als Beweis des Migrationsmechanismus
  - `composer.json` (PSR-4 `OCA\ERP\`)
- Web-UI-Grundgerüst: Vue 3 + `@nextcloud/vue`, App-Navigation für alle 16
  Hauptbereiche aus der Roadmap, Dashboard-Platzhalter mit den geplanten Kacheln,
  generische Platzhalteransicht für alle noch nicht gebauten Module
  (jeweils mit Verweis auf die zuständige Roadmap-Phase)
  - `npm run build` erfolgreich (nur Bundle-Size-Warnung, keine Fehler)
- PHPUnit-Grundgerüst: `ApplicationTest`, `PageControllerTest`, Migrations-Test

**Noch offen aus Phase 1:**

- Verifikation im laufenden Docker-Container (App aktivieren, Web-UI aufrufen,
  Tests im Container ausführen) — Docker-Image-Download lief noch, als dieser
  Stand geschrieben wurde
- CI-Workflow (GitHub Actions)

**Nicht begonnen:** Phase 2–14 (Rechte/API-Ausbau, Contacts/Calendar/Files-Integration,
Projektkern, Angebote/Artikel, Zeitwirtschaft, Rechnungen, Lager, Fuhrpark, Kosten,
Auswertungen, Web-Stabilisierung, Flutter).

## 2026-08-19 — Phase 2 (Identität, Rechte, API-Grundlage)

**Erledigt:**

- ADR-0008: Rechte-Modell (eigene `erp_permissions`-Tabelle, User+Gruppen,
  höchste Stufe gewinnt, NC-Admin-Fallback)
- Migration `erp_permissions` (principal_type/id, resource_type, permission,
  unique index)
- `PermissionResolver` — reine Auflösungslogik, DB-frei, 9 Unit-Tests
- `PermissionService` + `PermissionMapper`/`PermissionEntry` (QBMapper/Entity
  nach Nextcloud-Konvention), liest Nextcloud-User/Gruppen live aus
- API v1: `GET /permissions/principals`, `GET`+`PUT /permissions/matrix`
  (NC-Admin), `GET /permissions/me` (jeder eingeloggte User) — dokumentiert in
  `docs/api/v1.md`
- Web-UI: Rechte-Matrix ersetzt den Platzhalter unter "Berechtigungen & Sätze"
  (User-/Gruppenliste links, editierbare Matrix rechts)
- 19 neue Tests (Resolver, Service gegen echte DB, Controller-Attribut-
  Regressionstest, Migrationsstruktur) — alle grün, App-Gesamtstand: 23 Tests

**Verifiziert (nicht nur behauptet):**

- `curl` als NC-Admin (`kay`): principals/matrix lesen und schreiben
  funktioniert, Eintrag persistiert korrekt (upsert, "none" löscht den Eintrag)
- `curl` als extra angelegter Nicht-Admin-Testuser: `principals`/`matrix` → HTTP
  403, `me` → HTTP 200 mit korrekt aufgelösten Rechten (direkter User-Eintrag
  wirkt, Gruppenmitgliedschaft wirkt, beides zusammen: höchste Stufe gewinnt)
- Playwright-Klicktest: Rechte-Matrix-Seite rendert, Principal-Auswahl zeigt
  korrekte Matrix, keine Konsolenfehler

**Gotcha für lokale Entwicklung:** Nach `occ app:disable`/`app:enable` kann
Nextclouds Routen-Cache neue OCS-Routen ignorieren (Symptom: `statuscode 998
"Invalid query"` trotz korrektem `routes.php`) — hilft: Container neu starten
(`docker compose restart nextcloud`).

**Phase 2 laut Roadmap-Prüfkriterien vollständig**, nächster Schritt: Phase 3
(Contacts/Calendar/Files-Integration).

## 2026-08-19 — Phase 3 (Contacts, Calendar, Files)

**Erledigt:**

- ADR-0009: Contacts über `OCP\Contacts\IManager::search()`, Calendar über die
  seit Nextcloud 31 offizielle Schreib-API (`ICalendarEventBuilder`/
  `ICreateFromString`), Files über `IRootFolder`/`Folder::newFolder()` — alle
  drei gegen die tatsächlich installierte API verifiziert, nicht angenommen
- Migration `erp_contact_links` (Kunde/Lieferant ↔ Contact-UID + ERP-Metadaten)
  und `erp_calendar_links` (generisch: resourceType/resourceId ↔ Kalendertermin)
- Contacts: Suche, Link-CRUD, **Rechte-Matrix aus Phase 2 greift bereits**
  (Lesen ab `read` auf `Kunden`/`Lieferanten`, Schreiben ab `write`)
- Calendar: Kalenderliste, echten Termin anlegen (kein ICS-Handbau), generische
  Verknüpfung, ebenfalls rechte-gegated über den übergebenen `resourceType`
- Files: idempotente ERP-Ordnerstruktur (`ERP/Projekte`, `.../Artikel`, …) im
  User-Home
- Web-UI: "Kunden"/"Lieferanten" sind echte Ansichten (Contact-Suche + Matrix
  aus verknüpften Kontakten), "Einstellungen" hat einen echten
  "Dateien & Ordner"-Abschnitt
- 17 neue Tests (Services gegen echte DB/gemockte Nextcloud-APIs,
  Controller-Rechte-Gate-Logik) — App-Gesamtstand: 40 Tests

**Verifiziert (nicht nur behauptet):**

- Contacts: Link anlegen/lesen/ändern/löschen per `curl`; Rechte-Gate
  end-to-end mit eigens angelegtem Nicht-Admin-Testuser durchgespielt (403 ohne
  Recht → 200 nach `read`-Vergabe → weiterhin 403 bei Schreibversuch ohne
  `write`)
- Calendar: echten Termin über die API angelegt und visuell in Nextclouds
  eigener Calendar-App bestätigt (Termin erscheint am korrekten Datum)
- Contacts: per Playwright bestätigt, dass die verlinkten Kontakte identisch
  mit denen in Nextclouds eigener Contacts-App sind (kein Schatten-Datensatz)
- Files: echte Ordnerstruktur mit Datei-IDs angelegt, per Playwright über die
  Einstellungen-Seite ausgelöst und Ergebnis geprüft
- Playwright-Klicktest: Kunden-Suche, Verknüpfen, Einstellungen-Ordnercheck —
  keine Konsolenfehler

**Ein Umgebungs-Gotcha gefunden (nicht unser Code):** `files_external` war in
der frischen Docker-Instanz aktiviert, aber seine eigene Migration war nie
gelaufen (`oc_external_mounts` fehlte) — hat einen Testlauf mit frisch
angelegtem Testuser zum Absturz gebracht, weil das Anlegen eines neuen Users
alle Mount-Provider durchprobiert. Fix: `occ app:disable files_external &&
occ app:enable files_external` (löst die Migration erneut aus).

**Ehrliche Einschränkung zum Prüfkriterium "Projekt kann verknüpft werden":**
Es gibt in Phase 3 noch keine Projekt-Entität (kommt erst in Phase 4). Die
Verknüpfungsmechanik (Contacts-Link-Tabelle, generische
resourceType/resourceId-Kalenderverknüpfung) ist bewusst so gebaut, dass
Phase 4 sie ohne Schemaänderung direkt für echte Projekte mitnutzen kann —
end-to-end bewiesen wurde sie hier mit `kunden` als Platzhalter-Ressourcentyp.

## 2026-08-20 — Phase 4 (Projektkern)

**Erledigt:**

- ADR-0010: Projekt-Datenmodell, Projektnummer-Generierung (`P-%05d` aus der
  eigenen ID, race-condition-frei), Status-Workflow (6 Stufen aus dem Mockup)
- Migration `erp_projects`, `erp_project_tasks`, `erp_orders` — Termine
  brauchten **keine neue Tabelle**, nutzen `erp_calendar_links` aus Phase 3
  direkt (`resourceType='projekte'`) — der Phase-3-Entwurf hat sich ausgezahlt
- Projekt anlegen erstellt automatisch den Projektordner
  `ERP/Projekte/<Nummer>` (wiederverwendet `ErpFolderService` aus Phase 3)
- Projekte, Aufgaben (Checkliste), Aufträge: volles CRUD, rechte-gegated
  (Projekte/Aufgaben über `ResourceType::Projekte`, Aufträge über eigenen
  `ResourceType::Auftraege`)
- Web-UI: Projektliste mit Status-Filterchips, Projektdetail mit 5 Tabs
  (Übersicht, Aufgaben, Aufträge, Termine, Dokumente) — Termine-Tab legt
  echte Kalendertermine an (Wiederverwendung der Phase-3-Calendar-API)
- 19 neue Tests — App-Gesamtstand: 59 Tests

**Ein echter Bug gefunden und gefixt:** Die `done`-Spalte in
`erp_project_tasks` war als `SMALLINT` migriert, die Entity aber als
`boolean` typisiert — PostgreSQL lehnte PDOs Boolean-Literal (`'t'`) für
`smallint` ab (`SQLSTATE[22P02]`). Fix: Spaltentyp auf `Types::BOOLEAN`
korrigiert (Migration war noch nicht veröffentlicht, direkt im Quelltext
behoben statt neuer Migration).

**Verifiziert:** Projekt anlegen → Ordner existiert real auf Platte
(`occ`-Check), Aufgabe anlegen/abhaken/löschen, Auftrag anlegen/Status ändern,
Termin über die Projekt-UI anlegen und in der Liste bestätigt, Rechte-Gate
für Projekte per Playwright/curl durchgespielt, kompletter Klickpfad
(Projektliste → Detail → alle 5 Tabs) ohne Konsolenfehler.

## 2026-08-20 — Phase 5 (Artikel, Produkte, Angebote)

**Erledigt (bislang umfangreichste Phase — 10 neue Tabellen, 5 neue Controller):**

- ADR-0011: Snapshot-Prinzip für Angebotspositionen (Preis/Satz wird beim
  Hinzufügen einer Position direkt auf der Position gespeichert, keine
  Live-Referenz), Netto-/MwSt.-Berechnung als reiner Lesevorgang
  (`QuoteCalculationService`, DB-frei testbar analog `PermissionResolver`)
- MwSt.-Sätze und Arbeitsarten als Stammdaten unter Einstellungen (nur ein
  Satz kann gleichzeitig Standard sein — wird beim Setzen automatisch
  durchgesetzt)
- Artikelstamm mit mehreren Lieferantenpreisen pro Artikel
- Produkte/Bundles aus Artikel- und Arbeitskomponenten
- Angebote mit Positionsgruppen, vier Positionstypen (Artikel/Produkt/
  Arbeitsstunden/Freitext), automatisch generierter Angebotsnummer
  (`A-%05d`), Netto-Gruppensummen und MwSt.-Abschlussblock (MwSt. wird
  ausschließlich am Ende, getrennt je vorkommendem Satz, berechnet — nie pro
  Position/Gruppe)
- Neue `AbstractResourceController`-Basisklasse für die Phase-5-Controller,
  um das Rechte-Gate-Muster (5 Controller) nicht fünfmal zu duplizieren —
  ältere Controller bleiben unverändert, um stabilen Code nicht anzufassen
- Web-UI: Artikel (mit aufklappbaren Lieferantenpreisen), Produkte (mit
  Komponenten/Arbeitsleistungen), Angebotsliste, vollständiger
  Angebots-Editor (Gruppen, gemischte Positionen, Live-Abschlussblock),
  Einstellungen um MwSt.-/Arbeitsarten-Verwaltung erweitert
- 30 neue Tests — App-Gesamtstand: **89 Tests**

**Ein echter Bug gefunden und gefixt (Nextcloud-Entity-Fallstrick):**
`QuotePosition::$positionType` hatte den PHP-Klassendefault `'custom'`.
Nextclouds Entity-Dirty-Tracking vergleicht beim Setter gegen den aktuellen
(Default-)Wert — beim Anlegen einer echten `'custom'`-Position war der neue
Wert identisch zum Default, die Spalte wurde als "unverändert" eingestuft
und beim INSERT übersprungen. Da `position_type` absichtlich keinen
DB-Default hat, führte das zu einer NOT-NULL-Verletzung. Gefunden durch
systematisches Isolieren (funktionierte mit `article`/`labor`, brach nur bei
`custom`) statt durch Vermuten. Fix: Entity-Default auf `''` geändert;
Regressionstest ergänzt, der explizit über einen frischen Mapper-Read
verifiziert, dass der Wert wirklich in der DB steht.

**Verifiziert:** Vollständiges Angebot mit 4 gemischten Positionen (Artikel,
Arbeitsstunden, zwei Freitext) und zwei MwSt.-Sätzen (19 %/7 %) end-to-end
angelegt, Netto-Zwischensumme/Gruppensummen/MwSt.-Aufschlüsselung/Brutto-
Gesamt manuell nachgerechnet und bestätigt (404,00 € netto → 477,76 €
brutto). Playwright-Klicktest durch Artikel/Produkte/Angebots-Editor, keine
Konsolenfehler.

## 2026-08-20 — Phase 6 (Zeitwirtschaft und Verrechnungssätze)

**Erledigt (8 neue Tabellen, 6 neue Controller):**

- ADR-0012: technisch getrenntes Satzmodell (Standard-/Kundenvertragssatz),
  6-stufige Priorisierung, Satz-Snapshot auf der Zeitbuchung, Zeitkonto als
  reiner Lesevorgang ohne gespeicherten Saldo, Abwesenheiten über die
  bestehende `erp_calendar_links`-Tabelle statt einer eigenen Verknüpfung
- `RateResolutionService` (pure Logik, DB-frei): löst je User+Arbeitsart den
  effektiven Satz auf — Kundenvertrag (User) → Kundenvertrag (Gruppe) →
  Standard (User) → Standard (Gruppe) → globaler Standard → Arbeitsart-
  Default → harter Fallback 0,0. 9 Unit-Tests ohne DB.
- `RateService`/`CustomerContractService`: Standard-Sätze- und
  Kundenvertrags-CRUD, `resolveRate()` als DB-Orchestrierung um die reine
  Logik herum (Gate `BerechtigungenSaetze`)
- `TimeEntryService`: Zeiterfassung mit Satz-Snapshot bei Anlage —
  spätere Satzänderungen wirken sich nicht rückwirkend auf bereits erfasste
  Zeiten aus (Gate `StundenZeitkonto`)
- `TimeAccountCalculator` (pure Logik): Soll aus Wochensoll/5 × Werktage im
  Zeitraum (Mo–Fr, kein Feiertagskalender), Ist aus summierten Minuten,
  Saldo = Ist − Soll. `TimeAccountService` als Live-Berechnung ohne
  gespeicherten Zwischenstand. `WorkScheduleService` liefert bei fehlendem
  Arbeitszeitmodell einen 40h-Default statt eines Fehlers.
- `AbsenceRequestService`: Urlaubs-/Abwesenheitsanträge mit
  Freigabe-Workflow (requested → approved/rejected). Genehmigung legt
  optional einen Kalendertermin im ersten beschreibbaren Kalender des
  Antragstellers an und verknüpft ihn über `erp_calendar_links`
  (`resourceType='absence'`) — ein fehlender Kalender lässt die Genehmigung
  nicht scheitern.
- `OvertimeActionService`: Überstunden abbummeln/auszahlen mit
  Freigabe-Workflow (requested → approved → done, bzw. reject)
- Web-UI: Stunden & Zeitkonto mit 4 Tabs (Zeiterfassung, Zeitkonto, Urlaub &
  Abwesenheit, Überstunden) — Freigabe-Abschnitte blenden sich bei
  fehlendem Approve-Recht (403) selbst aus, statt die Seite abzubrechen
- 33 neue Tests — App-Gesamtstand: **122 Tests**

**Zwei Varianten des bekannten Entity-Dirty-Tracking-Fallstricks (ADR-0011/
Phase 5) gezielt vermieden, nicht neu gefunden:**
`TimeEntry::$billable` und `AbsenceRequest::$status`/`OvertimeAction::$status`
haben DB-seitige Defaults (`true` bzw. `'requested'`). Die Entity-Defaults
spiegeln diese bewusst — dadurch landet ein beim Insert "unverändert"
wirkender Wert trotzdem korrekt über den DB-Default, während ein davon
abweichender Wert (z. B. `billable=false`) explizit als geändert erkannt und
mitgeschrieben wird. `StandardRate::$principalType`/`CustomerContractRate::
$principalType` haben dagegen bewusst `null` statt eines String-Defaults,
weil `'user'`/`'group'` echte Werte sind (identisches Muster wie
`QuotePosition::$positionType` in Phase 5). Regressionstest
`testBillableFalseIsPersistedCorrectly` prüft explizit über einen frischen
Mapper-Read, dass `billable=false` wirklich in der DB steht.

**Verifiziert:** Vollständiger End-to-End-Durchlauf via curl — alle 6
Prioritätsstufen der Satz-Auflösung (inkl. Kundenvertrag über Projekt→Kunde),
Zeitbuchung mit korrektem Satz-Snapshot, Zeitkonto-Berechnung (Wochensoll
30h → Soll/Ist/Saldo über 5 Werktage), Abwesenheitsantrag → Genehmigung →
echter Kalendertermin im Kalender des Antragstellers, Überstunden-Workflow
(requested → approved → done, inkl. 412 bei Status-Verletzung), 403 für
User ohne Rechte auf allen sechs neuen Controllern. Playwright-Klicktest
durch alle vier Tabs von Stunden & Zeitkonto inkl. echtem Formular-Submit,
keine Konsolenfehler. Voller Cold-Restart-Zyklus des Docker-Containers zur
Bestätigung, dass Migrationen/Routen/Autoload robust neu aufgesetzt werden.

## 2026-08-20 — Phase 7 (Rechnungen, Gutschriften, Zahlungsstatus)

**Erledigt (5 neue Tabellen, 2 neue Controller):**

- ADR-0013: Rechnungsnummer wird erst bei `issue()` vergeben (nicht beim
  Anlegen des Entwurfs) — verhindert Nummernlücken durch verworfene
  Entwürfe ("manipulationsarm", Roadmap-Prüfkriterium). Atomare
  Sequenzvergabe je Jahr+Art über eine dedizierte Zählertabelle
  (`erp_invoice_counters`) in einer DB-Transaktion.
- `InvoiceNumberFormatter` (pure Logik): `{Präfix}-{Jahr}-{Sequenz:05d}`,
  `R-` für Rechnungen, `G-` für Gutschriften. 4 Unit-Tests.
- `InvoiceService`: Entwürfe direkt oder aus einem Angebot erzeugen
  (`createFromQuote()` kopiert Positionen 1:1), Positionen nur im Entwurf
  änderbar, `issue()` macht die Rechnung unveränderlich, `recordPayment()`
  leitet den Status live aus dem Bruttobetrag ab (issued → partially_paid
  → paid). Netto-/MwSt.-Berechnung nutzt `QuoteCalculationService` aus
  Phase 5 wieder, statt eine Kopie zu pflegen.
- `CreditNoteService`: einziger Korrekturweg für ausgestellte Rechnungen —
  Vollstorno (kopiert alle Positionen, storniert die Rechnung automatisch
  beim Ausstellen) oder Teilkorrektur (freie Positionsliste, ändert den
  Rechnungsstatus nicht).
- Rechnungsdokument: beim Ausstellen einer projektgebundenen Rechnung wird
  eine druckfähige HTML-Repräsentation in `ERP/Projekte/<Nr>/Rechnungen`
  abgelegt (`document_file_id`) — bewusst kein PDF-Binärexport, siehe
  ADR-0013.
- Web-UI: Rechnungsliste, Rechnungsdetail (Positionen, Ausstellen, Zahlung
  erfassen, Gutschriften inkl. Vollstorno-/Teilkorrektur-Button), neuer
  Button "Rechnung aus diesem Angebot erstellen" im Angebots-Editor.
- 22 neue Tests — App-Gesamtstand: **145 Tests**

**Verifiziert:** Entwurf → Position → Ausstellen (Rechnungsnummer
`R-2026-…`) → Positionen danach unveränderlich (412), Teilzahlung →
Vollzahlung, Rechnung aus Angebot mit identischen Werten wie in Phase 5
verifiziert (404,00 € netto → 477,76 € brutto), inkl. echtem
Rechnungsdokument im Projektordner. Vollstorno per Gutschrift
(`G-2026-…`), Rechnung danach `cancelled`. 403 für User ohne Rechte auf
`ResourceType::Rechnungen`. Playwright-Klicktest über den kompletten
Workflow (Angebot → Rechnung erstellen → Positionen sichtbar), keine
Konsolenfehler. Voller Cold-Restart-Zyklus bestätigt, dass Migration,
Routen und Autoload robust neu aufgesetzt werden.

## 2026-08-20 — Phase 8 (Lager, Inventur, Bestellvorschläge)

**Erledigt (5 neue Tabellen, 3 neue Controller):**

- ADR-0014: Lagerorte als eine Tabelle mit Typ-Diskriminator (`central`/
  `vehicle`/`site`), Soll-Menge (`quantityOnHand − quantityReserved`) als
  Live-Berechnung ohne gespeicherte Spalte (identisches Prinzip wie
  Zeitkonto/Angebotssummen), jede Bestandsänderung läuft über ein
  Bewegungsprotokoll (`erp_stock_movements`) statt einer direkten
  Spaltenänderung.
- `StockCalculator`/`PurchaseSuggestionCalculator` (pure Logik):
  Nachbestellbedarf besteht, wenn Ist- **oder** Soll-Menge unter den
  Mindestbestand fällt; Bestellmenge = Mindestbestand − Ist-Menge. 10
  Unit-Tests ohne DB.
- `WarehouseService`/`StockService`: Lagerorte-CRUD (`site` erfordert
  `projectId`), `recordMovement()` lehnt negative resultierende Bestände
  ab, `transfer()` als atomares Bewegungspaar zwischen zwei Lagerorten,
  `reserve()`/`release()` für reservierte Mengen.
- `InventoryService`: vollständiger Inventurablauf — Zählung snapshotet
  den erwarteten Bestand zum Zählzeitpunkt (nicht zum Inventurstart),
  Abschluss bucht automatisch Korrekturbewegungen für jede Differenz ≠ 0.
- `PurchaseSuggestionService`: bewusst **keine** eigene Tabelle — jede
  Abfrage berechnet live, welche Artikel/Lagerort-Kombinationen unter
  Mindestbestand liegen, inkl. sortierter Lieferantenoptionen aus
  `erp_article_supplier_prices` (Phase 5).
- Web-UI: Lager mit 4 Tabs (Lagerorte, Bestand, Inventur,
  Bestellvorschläge).
- 28 neue Tests — App-Gesamtstand: **174 Tests**

**Verifiziert:** Wareneingang/Verbrauch mit vollständigem Audit-Trail,
Verbrauch über Bestand hinaus korrekt abgelehnt (412), Umlagerung
zwischen zwei Lagerorten, Bestellvorschlag korrekt berechnet sobald
Bestand unter Mindestbestand fällt (inkl. günstigstem Lieferant zuerst),
vollständiger Inventurzyklus (Start → Zählung mit Differenzanzeige →
Abschluss → Korrekturbuchung wirkt sich sichtbar auf den Bestand aus).
403 für User ohne Rechte auf `ResourceType::Lager`. Playwright-Klicktest
durch alle vier Tabs mit echten Daten aus den curl-Tests, inkl. echtem
Formular-Submit (Lagerort anlegen), keine Konsolenfehler. Voller
Cold-Restart-Zyklus bestätigt, dass Migration, Routen und Autoload robust
neu aufgesetzt werden.

## 2026-08-21 — Nutzeranpassung: Projektpflicht, Lieferscheine, Kontakt-/User-Picker

**Auslöser:** Direktes Nutzerfeedback nach Phase 8 (kein Roadmap-Phasen-
Schritt, sondern eine Architektur-/UX-Korrektur): Angebote, Aufträge,
Rechnungen, Lieferscheine und Gutschriften sollen zwingend an Projekten
hängen und aus dem Hauptmenü verschwinden; Kunde/Verantwortlicher sollen
über Dropdown mit Suchfeld statt Freitext ausgewählt werden.

**Erledigt (ADR-0015, 2 neue Tabellen, 1 neuer Controller):**

- `erp_quotes`/`erp_invoices.project_id` von nullable auf `NOT NULL`
  umgestellt (Waisen-Datensätze vorher bereinigt, `erp_credit_notes`
  bekommt eine eigene `project_id`-Spalte, aus der jeweiligen Rechnung
  befüllt) — ersetzt den entsprechenden Teil von ADR-0011/ADR-0013 (siehe
  Hinweis-Blöcke dort, ADRs selbst bleiben `accepted` und unverändert).
- Neues Modul **Lieferscheine** (`erp_delivery_notes` +
  `erp_delivery_note_positions`): Nummer sofort bei Anlage vergeben
  (`L-%05d`), Positionen ohne Preise/MwSt. (nur Menge/Einheit), nur im
  Entwurf änderbar. Neuer Rechtebereich `ResourceType::Lieferscheine`.
- `ContactPicker.vue`/`UserPicker.vue`: wiederverwendbare Suchfeld-
  Dropdown-Komponenten, nutzen bestehende (`ContactPicker`) bzw. neue
  (`UserPicker`, `GET /permissions/users`) Endpunkte.
- Angebote/Aufträge/Rechnungen aus der Seitenleiste entfernt; die
  bestehenden Listen-Views laufen jetzt als Tabs in `ProjektDetailView`
  (Übersicht, Aufgaben, **Angebote, Aufträge, Rechnungen, Lieferscheine,
  Gutschriften**, Termine, Dokumente) — 4 neue Tabs.
- 12 neue Tests — App-Gesamtstand: **189 Tests**

**Verifiziert:** Seitenleiste zeigt Angebote/Aufträge/Rechnungen/
Lieferscheine nicht mehr an; alle neuen Projekt-Tabs zeigen echte Daten;
vollständiger Lieferschein-Workflow (Anlegen → Position → Ausstellen) per
curl; Gutschrift übernimmt `project_id` automatisch von der Rechnung;
ContactPicker/UserPicker per Playwright interaktiv getestet (Suche,
Dropdown-Auswahl, Speichern, Neuladen zeigt korrekt aufgelösten
Anzeigenamen); 403 für User ohne Rechte auf `lieferscheine`; offener
Zugriff auf die User-Suche bestätigt (keine sensible Information). Voller
Docker-Cold-Restart-Zyklus bestanden.

## 2026-08-21 — Nutzeranpassung: Belegkette Angebot→Auftrag→Lieferschein/Rechnung, Teilrechnungen

**Auslöser:** Weiteres direktes Nutzerfeedback: Kundenvorbelegung soll für
alle projektgebundenen Anlage-Formulare gelten (nicht nur Angebote);
Angebote sollen in Aufträge wandelbar sein; Aufträge sollen in
Lieferscheine (nur Artikel/Produkte, keine Zeiten) und in Rechnungen
wandelbar sein; Lieferscheine sollen ebenfalls in Rechnungen wandelbar
sein; Teilrechnungen (Positionsauswahl oder Materialabschlag) sollen
möglich sein; die Schlussrechnung muss frühere Teilrechnungen/
Teilzahlungen am Ende auflisten.

**Erledigt (ADR-0016, 1 neue Tabelle, 5 neue Spalten):**

- Aufträge bekommen Positionen (`erp_order_positions`, gleiches Schema
  wie Angebotspositionen, flach ohne Gruppen) sowie `customer_contact_uid`
  und `quote_id`.
- `OrderService::createFromQuote()` — Angebot → Auftrag, kopiert Titel,
  Kunde und alle Positionen 1:1 (Snapshot-Prinzip).
- `DeliveryNoteService::createFromOrder()` — Auftrag → Lieferschein, nur
  `article`/`product` (keine Arbeitsstunden), mit Mengenauswahl und
  Prüfung gegen die noch nicht gelieferte Restmenge.
- `InvoiceService::createFromOrder()`/`createFromDeliveryNote()` — Auftrag
  bzw. Lieferschein → Rechnung, mit Positionsauswahl (inkl. Teilmengen)
  für Teilrechnungen durch Auswahl; Materialabschlag läuft über den
  bestehenden `addPosition()`-Weg (Freitext-Posten ohne Auftragsbezug),
  kein neues Datenmodell nötig.
- `erp_invoice_positions`/`erp_delivery_note_positions` bekommen
  `order_position_id` — ermöglicht die zur Laufzeit berechnete "bereits
  berechnete/gelieferte Menge" je Auftragsposition (informativ, kein
  Locking).
- `getFullInvoice()` liefert `relatedInvoices` (Geschwister-Rechnungen
  desselben Auftrags mit Nummer/Typ/Status/Bruttosumme/Bezahlt) — im
  Frontend als "Teilrechnungen & Teilzahlungen dieses Auftrags"-Abschnitt
  am Ende der Rechnungsansicht gerendert, rein informativ ohne
  automatische Verrechnung.
- Neue Web-UI: `AuftraegeView`/`AuftragDetailView` (Positionen,
  Umwandlungs-Buttons "In Lieferschein wandeln"/"In Rechnung wandeln"/
  "Materialabschlag anlegen"); `AngebotDetailView` bekommt "In Auftrag
  wandeln"; `LieferscheineView`-Detail bekommt "In Rechnung wandeln".
- Kundenvorbelegung generalisiert auf Aufträge und Rechnungen (analog zu
  Angeboten) — Lieferscheine bewusst ausgenommen, da sie kein eigenes
  Kundenfeld haben (ADR-0015).
- 17 neue Tests — App-Gesamtstand: **206 Tests**

**Verifiziert:** Vollständige Kette Angebot → Auftrag → Lieferschein +
Rechnung, Teilrechnung per Positionsauswahl, Materialabschlag,
Ablehnung von Arbeitsstunden-Positionen beim Lieferschein, Preis-
übernahme von der verknüpften Auftragsposition beim Umwandeln eines
Lieferscheins in eine Rechnung — jeweils per curl **und** per Playwright
durch die echte UI bestätigt (keine Konsolenfehler). Schlussrechnung
zeigt die verknüpften Teilrechnungen/Materialabschläge korrekt an.
Voller Docker-Cold-Restart-Zyklus bestanden.

## Bekannte Einschränkungen dieses Stands

- Artikel, Produkte, Angebote existieren jetzt (Phase 5) — Rechnungen/Lager/
  Fuhrpark/Zeitwirtschaft/Kosten noch nicht (Phase 6+).
- Kein Angebots-PDF-Export (bewusst nicht Teil von Phase 5, siehe ADR-0011)
  — "Angebot versenden" ist aktuell nur eine Status-/Zeitstempeländerung.
- Kein Live-Preisabgleich beim Auswählen eines Artikels/Produkts/Arbeitstyps
  in der Angebotsposition — der Web-UI-Nutzer trägt EP/MwSt. aktuell noch
  manuell ein, statt dass sie automatisch aus dem gewählten Artikel/Produkt
  vorbefüllt werden. Fachlich durch das Snapshot-Prinzip (ADR-0011) gedeckt,
  aber noch kein Komfort-Feature im UI.
- Projektordner leben weiterhin im Home-Verzeichnis des anlegenden Users
  (ADR-0009-Einschränkung gilt unverändert für Projektordner).
- Verantwortlicher User (`responsibleUserId`) ist ein reines Freitextfeld
  ohne Validierung gegen echte Nextcloud-User — keine Auswahlliste im UI.
- Frontend-Bundle ist noch nicht auf Komponentenebene tree-geshaked (Warnung beim
  Build) — für den Skeleton-Stand nicht kritisch, sollte vor Phase 12
  (Web-Reifegrad) angegangen werden.
- Alle offenen Punkte aus der Roadmap ("Offene Klärungen vor Implementierung")
  sind über ADRs entschieden, mit Ausnahme von Themen, die erst in späteren
  Phasen konkret werden (Standard-MwSt.-Sätze, initiale Rollen, Angebotsschema,
  Rechnungsumfang) — die bleiben bewusst bis zur jeweiligen Phase offen.
- Kein Web-UI für Verrechnungssätze/Kundenverträge (Phase 6) — aktuell nur
  über die API v1 bedienbar. Die Web-UI-Erweiterung von "Berechtigungen &
  Sätze" um diesen Bereich ist zurückgestellt und nicht Teil dieser Phase.
- Kein Feiertagskalender im Zeitkonto — Werktage sind einfach Mo–Fr
  (ADR-0012, bewusster Non-Goal für diese Phase).
- Keine automatische Herleitung von Überstunden aus dem Zeitkonto-Saldo —
  Anzahl Stunden wird beim Beantragen manuell eingegeben (ADR-0012).
- Kein Resturlaub-Zähler — `AbsenceType::affectsVacationBalance` ist
  aktuell nur ein Flag ohne eigene Berechnung/Anzeige des verbleibenden
  Kontingents.
- Pausenregeln nach ArbZG (§4) sind nicht abgebildet — `breakMinutes` ist
  reine Erfassung ohne automatische Prüfung (ADR-0012).
- Kein echter PDF-Export für Rechnungen (nur HTML im Projektordner), kein
  XRechnung/ZUGFeRD, keine vollständige § 14 UStG-Pflichtangaben-Prüfung
  (fehlende Firmenstammdaten-Entität) — siehe ADR-0013, "Nicht Teil dieser
  Phase". **Vor produktivem Rechnungsversand an Kunden zwingend
  nachzuziehen.**
- Kein Zahlungsjournal mit Einzelbuchungen (Datum/Referenz je
  Teilzahlung, Mahnwesen) — nur ein laufender `paid_amount`-Betrag.
- Kein Steuerberater-Exportformat (z. B. DATEV) implementiert.
- Fahrzeuglager (`type = 'vehicle'`) sind nur ein Namensfeld ohne
  Verknüpfung zu einem echten Fahrzeug-Datensatz — folgt erst mit dem
  Fuhrpark in Phase 9 (ADR-0014).
- Keine Offline-Synchronisierung von Materialverbrauch — bewusst eine
  Aufgabe der späteren Flutter-Phasen, nicht des Web-MVP.
- Keine automatische Reservierungslogik gegen Angebots-/Auftragspositionen
  — `reserve()`/`release()` sind manuelle Aufrufe ohne Automatismus.
- Keine eigene Bestellungs-/Einkaufs-Entität — Bestellvorschläge bleiben
  ein reiner, nie gespeicherter Bericht (ADR-0014).
- Kein Lieferschein-PDF/-Dokument im Projektordner (anders als bei
  Rechnungen) — kann bei Bedarf später nachgezogen werden (ADR-0015).
- ContactPicker/UserPicker sind nur an den explizit angeforderten Stellen
  verbaut (Projekt, Angebot, Auftrag, Rechnung) — Lieferanten-Auswahl bei
  Artikelpreisen und Kundenverträge (Phase 6) nutzen weiterhin Freitext.
- Keine automatische Verrechnung/Subtraktion von Teilrechnungsbeträgen in
  der Schlussrechnung (`relatedInvoices` ist reine Auflistung) — echte
  Abschlagsrechnungs-Arithmetik nach § 14 UStG bleibt offen (ADR-0016).
- Kein Locking gegen doppeltes Verplanen von Auftragspositions-Mengen bei
  gleichzeitiger Bearbeitung — "bereits berechnet/geliefert" ist
  informativ (ADR-0016).
- Rechnung aus Angebot ohne Projekt ist seit ADR-0015 unmöglich, da beide
  jetzt zwingend ein Projekt erfordern — kein produktiv genutzter
  Anwendungsfall betroffen (lokale Docker-Testdaten).
