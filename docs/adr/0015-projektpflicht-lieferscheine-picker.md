# ADR-0015: Projektpflicht für Angebote/Rechnungen/Gutschriften, Lieferscheine, Kontakt-/User-Picker

**Status:** accepted
**Datum:** 2026-08-21

## Kontext

Nutzerfeedback nach Phase 8: Angebote, Aufträge, Rechnungen, Lieferscheine
und Gutschriften sollen zwingend an ein Projekt gebunden sein, aus dem
seitlichen Hauptmenü verschwinden und stattdessen im Projekt selbst
auftauchen — mit echter Datenbank-Verlinkung, nicht nur einer UI-Filterung.
Außerdem sollen Kunde und Verantwortlicher über Dropdown-Menüs mit
Suchfeld statt Freitext ausgewählt werden.

Das ändert eine frühere Entscheidung: ADR-0011 erlaubte Angebote
ausdrücklich ohne Projekt ("Angebot kann ohne Projekt starten"), ADR-0013
übernahm das unverändert für Rechnungen. Dieser ADR ersetzt diesen Teil
beider Entscheidungen.

## Entscheidung

### Angebote, Rechnungen, Gutschriften erfordern ab sofort ein Projekt

`erp_quotes.project_id` und `erp_invoices.project_id` werden von nullable
auf `NOT NULL` umgestellt. `erp_credit_notes` bekommt eine neue
`project_id`-Spalte (`NOT NULL`), die beim Anlegen einer Gutschrift direkt
vom `project_id` der referenzierten Rechnung übernommen wird — Gutschriften
hängen fachlich immer an einer Rechnung, aber die zusätzliche direkte
Spalte macht projektbezogene Abfragen (`findByProject()`) ohne Join
möglich und erfüllt wörtlich "die Verlinkung muss in der Datenbank
erfolgen".

**Aufträge** (`erp_orders.project_id`) waren bereits `NOT NULL` seit
ADR-0010 — hier ist keine Schemaänderung nötig, nur die Entfernung des
(ohnehin funktionslosen) Platzhalter-Menüpunkts "Aufträge" aus der
Seitenleiste, da Aufträge schon immer ausschließlich über das Projekt
angelegt wurden.

**Bereinigung bestehender Daten:** Vor der Schema-Änderung werden in
`preSchemaChange()` Angebote/Rechnungen ohne Projekt (samt Positionen und
davon abhängigen Gutschriften) gelöscht — ausschließlich Testdaten aus der
lokalen Docker-Entwicklungsumgebung, keine Produktivdaten. `project_id`
auf `erp_credit_notes` wird mit `default => 0` als `NOT NULL` angelegt
(Postgres befüllt bestehende Zeilen automatisch mit diesem Default) und in
`postSchemaChange()` per `UPDATE ... SET project_id = (SELECT project_id
FROM erp_invoices WHERE id = invoice_id)` auf den korrekten Wert
korrigiert.

### Neues Modul: Lieferscheine

`erp_delivery_notes` (delivery_note_number — sofort bei Anlage vergeben,
analog zu Angebotsnummern `A-%05d`, da Lieferscheine nicht derselben
GoBD-Nummernlücken-Anforderung wie Rechnungen unterliegen, siehe ADR-0013;
Format `L-%05d`; project_id NOT NULL; order_id nullable; status
`draft`/`issued`; delivered_at nullable; notes) + `erp_delivery_note_
positions` (delivery_note_id, position_type, reference_id nullable,
description, quantity, unit, position_order — bewusst **ohne** Preise/
MwSt., ein Lieferschein dokumentiert nur die gelieferte Menge, keinen
Wert). Positionen nur im Entwurf änderbar, `issue()` setzt `delivered_at`
und macht die Positionen unveränderlich — identisches Immutability-Muster
wie Rechnungen (ADR-0013), ohne die dortige Nummernvergabe-Komplexität, da
kein Zahlungs-/Steuerbezug besteht. Rechtebereich:
`ResourceType::Lieferscheine` (neuer Fall im Enum).

### Kontakt- und User-Picker als wiederverwendbare Komponenten

`ContactPicker.vue` (Suchfeld + Dropdown über den bestehenden
`GET /contacts/search`-Endpunkt aus ADR-0009, keine neue Backend-Arbeit
nötig) ersetzt die Freitext-Eingabe für `customerContactUid` in
Projekt-Übersicht, Angebots- und Rechnungs-Erstellung. `UserPicker.vue`
(neuer, ungegateter Endpunkt `GET /permissions/users?q=`, analog zu
`ContactsController::search()` — jeder eingeloggte User darf Nextcloud-
User zum Zuweisen suchen, das ist keine sensible Information) ersetzt die
Freitext-Eingabe für `responsibleUserId` im Projekt. Beide Komponenten
sind bewusst generisch (Prop-basierte Suchfunktion) gehalten, damit sie
später an weiteren Stellen (z. B. Lieferanten-Auswahl) wiederverwendet
werden können, auch wenn diese Phase nur die explizit genannten Stellen
umstellt.

### Navigation: Angebote/Aufträge/Rechnungen aus der Seitenleiste entfernt

Die entsprechenden Einträge werden aus dem `modules`-Array in
`router/index.js` entfernt (treibt sowohl Seitenleiste als auch
Routen-Erzeugung). Die bestehenden Detail-Routen (`/angebote/:id`,
`/rechnungen/:id`) bleiben erhalten — sie werden jetzt ausschließlich aus
dem Projekt heraus verlinkt. Die bisherigen Listen-Views (`AngeboteView`,
`RechnungenView`) werden nicht verworfen, sondern bekommen ein optionales
`projectId`-Prop und werden als neue Tabs in `ProjektDetailView`
eingebettet — vermeidet doppelten Code für Listen-Rendering und
Anlegen-Formular. Gutschriften bekommen im Projekt einen eigenen
Übersichts-Tab (Liste mit Link zur zugehörigen Rechnung), da sie keine
eigene Detailseite haben.

### Rechte

`ResourceType::Lieferscheine` neu; alle anderen betroffenen Module
behalten ihre bestehenden Rechtebereiche (`Angebote`, `Rechnungen`) — die
Entfernung aus der Seitenleiste ändert nichts an der Rechteprüfung selbst,
nur am Einstiegspunkt in der UI. Die Rechte-Matrix unter "Berechtigungen &
Sätze" zeigt weiterhin alle `ResourceType`-Fälle, unabhängig davon, ob sie
einen eigenen Seitenleisten-Eintrag haben.

## Nicht Teil dieser Phase

- Lieferschein-PDF/-Dokument im Projektordner (analog zur Rechnung aus
  ADR-0013) — kann bei Bedarf später nachgezogen werden, kein hartes
  Anforderungskriterium dieser Anpassung.
- ContactPicker/UserPicker an weiteren Stellen (Lieferanten-Auswahl bei
  Artikelpreisen, Kundenverträge aus Phase 6) — nur die explizit genannten
  Stellen werden umgestellt.
- Automatische Migration bestehender Produktivdaten ohne Projekt — dieser
  ADR geht von einer noch nicht produktiv genutzten Instanz aus
  (Docker-Testdaten). Für eine produktive Migration wäre ein manueller
  Zuordnungsschritt vor der Schema-Änderung nötig.

## Konsequenzen

- `QuoteService::createQuote()` und `InvoiceService::createDraft()`
  werfen ab sofort einen Fehler, wenn kein `projectId` übergeben wird —
  Breaking Change für bestehende API-Aufrufer ohne Projektbezug.
- Angebote/Rechnungen/Lieferscheine/Gutschriften sind nur noch über ein
  Projekt erreichbar — ein Anwendungsfall "Rechnung ganz ohne Projekt"
  (z. B. für einmalige Verkäufe ohne Projektstruktur) wird damit bewusst
  ausgeschlossen, bis ein echter Bedarf dafür entsteht.

## Alternativen erwogen

- `project_id` weiterhin nullable lassen und nur die UI umbauen
  (Sidebar-Entfernung ohne Schema-Änderung): hätte "die Verlinkung muss in
  der Datenbank erfolgen" nicht wörtlich erfüllt — eine rein
  UI-seitige Filterung ist keine Datenbank-Verlinkung.
- Lieferscheine als reine Position innerhalb von Aufträgen statt eigenes
  Modul: zu unflexibel — ein Lieferschein kann mehrere Teillieferungen
  eines Auftrags abbilden, eine 1:1-Kopplung an genau einen Auftrag wäre
  zu starr (`order_id` bleibt deshalb nullable).
