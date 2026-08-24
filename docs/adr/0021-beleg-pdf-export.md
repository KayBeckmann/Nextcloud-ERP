# ADR-0021: PDF-Export für Belege + Empfehlung zur unveränderlichen Ablage

**Status:** accepted
**Datum:** 2026-08-24

## Kontext

Nutzeranforderung (2026-08-24): Aus Angeboten, Aufträgen, Lieferscheinen,
Rechnungen und Gutschriften sollen echte PDF-Dateien erzeugt werden, die
automatisch mit Zeitstempel in der Nextcloud-Ordnerstruktur landen —
idealerweise so, dass sie nachträglich nicht mehr gelöscht werden können.

Zwei ältere Entscheidungen hatten das bewusst zurückgestellt:

- ADR-0011: "Angebots-PDF-Export … wird als eigener Punkt vor Phase 12
  (Web-Reifegrad) nachgezogen."
- ADR-0013: "Rechnungsdokument im Projektordner — bewusst kein
  PDF-Binärexport", stattdessen eine druckfähige HTML-Datei, um zu diesem
  Zeitpunkt keine neue Abhängigkeit einzuführen.

Beide Male war der Grund derselbe: eine PDF-Bibliothek ist eine neue
Composer-Abhängigkeit mit eigener Lizenz-/Wartungsfrage, die nicht
"nebenbei" in einer anderen Phase mitentschieden werden sollte. Diese ADR
holt genau das jetzt nach.

## Entscheidung

### dompdf als PDF-Bibliothek

`dompdf/dompdf` (LGPL-2.1, alternativ PHP License 3.0) wird als neue
Composer-Abhängigkeit aufgenommen. Reines PHP, keine Systemabhängigkeit
(kein `wkhtmltopdf`-Binary o. Ä.), damit im bestehenden PHP-FPM-Container
ohne zusätzliche Docker-Änderungen nutzbar. LGPL ist als Composer-Library
mit einem MIT-lizenzierten Projekt unproblematisch kombinierbar (dynamische
Nutzung, keine Lizenz-"Ansteckung" der eigenen Codebasis) — eine vollständige
formale Lizenz-/Dependency-Review aller Abhängigkeiten bleibt trotzdem
Prüfkriterium der bisherigen "Web-Reifegrad"-Phase (jetzt Phase 13, siehe
Roadmap-Änderung unten).

### Gemeinsamer `DocumentPdfService` statt Code je Belegtyp

Neue Klasse `lib/Service/DocumentPdfService.php`: nimmt gerenderte HTML
plus einen Ziel-`Folder` und eine Dateibasis (Belegnummer) entgegen,
rendert per dompdf zu PDF-Bytes und schreibt sie unter einem Dateinamen aus
**Belegnummer + Zeitstempel** (`<Belegnummer>_<Y-m-d>T<H-i>.pdf`, z. B.
`RE-2026-0042_2026-08-24T14-30.pdf`) in den Ordner. Der Zeitstempel im
Namen ist bewusst gewählt statt eines reinen Overwrite bei erneutem
Aufruf — jede tatsächliche PDF-Erzeugung hinterlässt eine eigene Datei,
nichts wird stillschweigend ersetzt. Jeder der fünf Belegtypen liefert nur
noch sein HTML (die HTML-Renderer aus ADR-0013 bleiben bestehen, nur der
Aufrufer ändert sich von "HTML in Datei schreiben" zu "HTML an
`DocumentPdfService` übergeben").

### Auslöse-Zeitpunkt je Belegtyp

Analog zum bestehenden Rechnungs-Muster wird das PDF genau einmal beim
fachlichen "Fixieren" eines Belegs erzeugt, nicht bei jeder Änderung:

| Belegtyp | Auslöser |
|---|---|
| Rechnung | `InvoiceService::issue()` (bestehend, HTML → PDF) |
| Gutschrift | `CreditNoteService::issue()` (neu) |
| Lieferschein | `DeliveryNoteService::issue()` (neu) |
| Angebot | `QuoteService::updateQuote()`, wenn Status erstmals auf `sent` wechselt (neu) |
| Auftrag | `OrderService::updateOrder()`, wenn Status erstmals auf `Confirmed` wechselt (neu) |

Angebot und Auftrag haben kein eigenes `issue()` — ihr Status wird über die
generische `update*()`-Methode gesetzt. Der PDF-Trigger prüft deshalb
denselben Übergang, den die Entities bereits für ihre eigenen Zeitstempel
nutzen (`sentAt`/vergleichbar), statt einen neuen Zustand einzuführen.

### `document_file_id` auf allen fünf Beleg-Entities

`erp_quotes`, `erp_orders`, `erp_delivery_notes`, `erp_credit_notes`
bekommen je eine neue, nullable Spalte `document_file_id` (Migration),
analog zur bestehenden Spalte auf `erp_invoices`. Wird bei jeder
PDF-Erzeugung auf die neueste Datei-ID aktualisiert — bei mehrfacher
Erzeugung (z. B. erneutes `issue()` nach Korrektur, sofern der Belegtyp
das zulässt) zeigt sie immer auf die zuletzt geschriebene Datei; ältere
PDF-Dateien bleiben im Ordner liegen, werden aber nicht mehr referenziert.

### Ordnerstruktur: ein Unterordner je Belegtyp im Projektordner

`ErpFolderService` bekommt `ensureQuoteFolder()`, `ensureOrderFolder()`,
`ensureDeliveryNoteFolder()`, `ensureCreditNoteFolder()` analog zum
bestehenden `ensureInvoiceFolder()` — Ergebnis:
`ERP/Projekte/<Projektnummer>/{Angebote,Aufträge,Lieferscheine,Rechnungen,Gutschriften}/`.

### Unveränderliche Ablage: Empfehlung, kein automatisiertes Setup

Echte Löschsicherheit auf Nextcloud-Ebene bräuchte einen **Group Folder**
mit der Berechtigung `Delete = verweigert` für die betroffene Gruppe — das
ist die einzige praxistaugliche, community-verfügbare Nextcloud-Funktion
dafür (die "Files Retention"-App macht das Gegenteil: automatisches
Löschen NACH einer Frist, kein Löschschutz). Bewusst **nicht** Teil dieser
Phase: das automatisierte Anlegen/Konfigurieren eines Group Folders aus
der ERP-App heraus, weil das eine interne (nicht-OCP-)API einer weiteren
App (`groupfolders`) voraussetzen würde — fragil gegenüber
Versionsänderungen dieser Fremd-App und ein eigenständiges
Architekturthema, keine Nebenentscheidung dieser ADR. Stattdessen wird in
der Betriebs-Doku (`docs/status.md`) ein manueller Einrichtungsschritt für
Admins dokumentiert: Group Folder "ERP-Archiv" anlegen, an
`ERP/Projekte` mounten, `Delete` für die normale Nutzergruppe verweigern.

**Wichtig, auch dokumentiert:** Das schützt vor versehentlichem/normalem
Löschen durch Nutzer, nicht vor einem Admin mit Root-/DB-Zugriff auf den
Server — echte GoBD-/revisionssichere Archivierung im rechtlichen Sinn
ist damit nicht erreicht und auch nicht das Ziel dieser ADR.

## Nicht Teil dieser Phase

- Automatisiertes Anlegen/Konfigurieren eines Nextcloud Group Folders aus
  der App heraus (s. o.) — nur Doku eines manuellen Einrichtungsschritts.
- Echte GoBD-/revisionssichere Archivierung (WORM-Speicher, kryptografische
  Nachweisbarkeit) — bräuchte eine dedizierte Archivlösung oder
  Object-Storage mit Object Lock als Nextcloud-Backend, außerhalb des
  Scopes dieser ADR.
- Digitale Signatur der erzeugten PDFs.
- Automatischer E-Mail-Versand des PDFs an den Kunden — weiterhin ein
  manueller Schritt (Datei aus Nextcloud herunterladen/teilen).
- Vollständige Pflichtangaben-Prüfung nach § 14 UStG für die PDF-Vorlagen —
  bereits in ADR-0013 als offener Punkt vor produktivem Einsatz vermerkt,
  gilt unverändert weiter (die PDF-Umstellung ändert daran nichts).

## Roadmap-Änderung

Neue **Phase 12 — Beleg-PDF-Export und Dokumentenarchiv** eingefügt (diese
ADR). Die bisherigen Phasen 12–14 rücken auf 13–15
(Web-Reifegrad/Stabilisierung, Flutter-Vorbereitung, Flutter-MVP);
Querverweise in ADR-0006, ADR-0007 und `docs/status.md` entsprechend
angepasst.

## Konsequenzen

- Der bestehende HTML-Export für Rechnungen (ADR-0013) entfällt; bereits
  ausgestellte Rechnungen mit einer `.html`-Dokumentdatei behalten ihre
  alte Datei und `document_file_id` unverändert — kein rückwirkendes
  Neu-Rendern alter Belege.
- Fünf neue Ordner pro Projekt statt bisher nur einem
  (`Rechnungen`) — bestehende Projektordner ohne bisherige Belege bleiben
  bis zur ersten PDF-Erzeugung des jeweiligen Typs ohne diese Unterordner
  (idempotentes `ensureFolder()`-Muster wie bisher).
- Ohne den empfohlenen Group-Folder-Umzug bleibt die Ablage weiterhin
  pro-User im Home-Verzeichnis (bereits in ADR-0009 als Einschränkung
  vermerkt) — diese ADR ändert daran nichts, sie fügt nur der bestehenden
  Struktur PDF-Dateien hinzu.

## Alternativen erwogen

- **wkhtmltopdf** (externer Renderer) statt dompdf: bessere CSS-Treue,
  aber zusätzliches Systembinary im Docker-Image nötig — mehr
  Build-/Wartungsaufwand für einen Vorteil, der bei den einfachen
  Tabellenlayouts der Belege nicht ins Gewicht fällt.
- **Group Folder automatisiert per `occ`-Kommandoaufruf aus PHP** anlegen
  lassen: technisch denkbar (`shell_exec` auf `occ groupfolders:create`),
  aber ein Sicherheits- und Wartungsrisiko (Shell-Ausführung aus der App
  heraus, Abhängigkeit vom exakten `occ`-Pfad im Container) für einen
  einmaligen Admin-Setup-Schritt — eine dokumentierte manuelle Anleitung
  ist hier die robustere Wahl.
- **PDF bei jedem Aufruf neu rendern statt Datei zu persistieren** (on
  the fly bei Download-Anfrage): hätte die Zeitstempel-Anforderung nicht
  erfüllt (kein fixierter Erzeugungszeitpunkt) und würde bei jedem Abruf
  Rechenzeit kosten, ohne einen Vorteil gegenüber einer einmal erzeugten,
  abgelegten Datei zu bieten.
