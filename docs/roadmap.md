# Roadmap — Nextcloud ERP Add-on

[README](../README.md) · [ADRs](adr/)

Diese Roadmap beschreibt den geplanten Aufbau des ERP-Systems als **Nextcloud-App/Add-on**. Das ERP soll grundsätzlich **gewerkeübergreifend/branchenneutral** funktionieren und nicht speziell auf Elektriker abgestimmt sein.

Wichtige Grundentscheidung vom 2026-08-18:

- **Zuerst wird das Nextcloud-Add-on mit vollständigem Web-UI gebaut.**
- Die Web-Oberfläche soll **vollumfänglich alles können**.
- Wenn ein Baustellenleiter mit Laptop auf der Baustelle sitzt, soll er damit arbeiten können, ohne ständig zwischen Laptop/Web und Handy wechseln zu müssen.
- Die **Flutter-App wird erst gebaut, wenn das Nextcloud-Add-on reif/fertig genug ist**.
- Die **API wird trotzdem von Anfang an vorbereitet und bereitgestellt**, damit Flutter später ohne großen Umbau andocken kann.
- Nextcloud soll in **Docker** gehostet werden.
- Tests und Entwicklungsumgebung sollen auf unterschiedlichen Maschinen reproduzierbar sein.
- Das Repository steht unter **MIT-Lizenz**.

## Status (Stand 2026-08-25)

- [x] Phase 0 — Projekt- und Architekturfundament
- [x] Phase 1 — Nextcloud-App-Skeleton und Web-Grundgerüst
- [x] Phase 2 — Identität, Rechte und API-Grundlage
- [x] Phase 3 — Nextcloud-Integrationen: Contacts, Calendar, Files
- [x] Phase 4 — Projektkern: Kunden, Projekte, Aufträge
- [x] Phase 5 — Artikel, Produkte, Angebote
- [x] Phase 6 — Zeitwirtschaft und Verrechnungssätze
- [x] Phase 7 — Rechnungen, Gutschriften und Zahlungsstatus
- [x] Phase 8 — Lager, Inventur und Bestellvorschläge
- [x] Phase 9 — Fuhrpark
- [x] Phase 10 — Betriebliche Kosten und Kalkulation
- [x] Phase 11 — Auswertungen, Dashboard, Exporte
- [x] Phase 12 — Beleg-PDF-Export und Dokumentenarchiv
- [ ] Phase 13–16 — noch nicht begonnen — **Phase 13 nächster Schritt**

Details zum aktuellen Baufortschritt: [`docs/status.md`](status.md).

## Zielbild

### Web / Nextcloud-App

Das Web-UI in Nextcloud ist die Hauptoberfläche für:

- Projektleiter
- Baustellenleiter mit Laptop
- Buchhaltung
- Verwaltung
- Geschäftsführung
- Admins

Es enthält langfristig alle ERP-Funktionen:

- Dashboard
- gewerkeübergreifende konfigurierbare Stammdaten, Arbeitsarten und Vorlagen
- Kunden/Lieferanten über Contacts-Referenzen
- Projekte/Aufträge/Angebote/Rechnungen/Gutschriften
- Angebote mit Artikeln, Produkten, reinen Arbeitsstunden und Positionsgruppen/Gruppensummen
- Artikel, Lieferantenpreise, Produkte/Bundles
- Projektordner und Dokumente über Files
- Termine, Personalplanung, Abwesenheiten über Calendar-Verknüpfung
- Zeitwirtschaft: Stundenbuchung, Zeitkonto, Urlaub, Überstunden
- Lager, Inventur, Bestellvorschläge
- Fuhrpark, TÜV, Werkstatt, Tankbelege
- betriebliche Kosten und Kalkulation
- Rechteverwaltung inklusive Modulrechten für Angebote, Artikel/Produkte, MwSt.-Settings, Stunden, Rechnungen und Adminbereiche
- Verrechnungssätze und Kundenverträge
- MwSt.-Sätze in Settings sowie MwSt.-Vorgaben an Artikeln und Arbeitsarten
- Auswertungen
- API für spätere Clients

### Flutter-App

Flutter ist später ein reduzierter Monteur-/Unterwegs-/Offline-Client.

Nicht Ziel der frühen Phasen:

- keine 1:1-Kopie des Web-ERP
- keine Buchhaltung
- keine Rechte-/Satzadministration
- keine Artikelstammverwaltung
- keine vollständige Kunden-/Lieferantenverwaltung

Flutter wird vorbereitet durch:

- stabile API-Kontrakte
- saubere Datenmodelle
- Auth-Konzept
- Offline-/Sync-Architektur
- klare Rechte- und Datengrenzen

Gebaut wird Flutter erst nach Reife des Web-Add-ons.

## Rahmenbedingungen

### Hosting

- Nextcloud wird in Docker gehostet.
- Entwicklungs- und Testumgebung sollen ebenfalls per Docker/Compose reproduzierbar sein.
- Ziel: Jeder Entwickler bzw. jede Testmaschine kann mit dokumentierten Befehlen dieselbe Umgebung starten.

### Reproduzierbare Tests

Alle Tests sollen auf unterschiedlichen Maschinen reproduzierbar sein.

Daraus folgt:

- keine lokalen Sonderkonfigurationen als Voraussetzung
- keine hardcodierten Hostpfade
- keine Secrets im Repo
- `.env.example` statt echter `.env`
- Docker Compose für Nextcloud, Datenbank und Testdienste
- Seed-/Fixture-Daten für Tests
- klare Mindestversionen für Nextcloud, PHP, Node, Datenbank
- CI-taugliche Tests

### Lizenz

- Repository unter MIT-Lizenz.
- `LICENSE`-Datei früh angelegt.
- Drittanbieter-Abhängigkeiten prüfen, damit keine Lizenzkonflikte entstehen.
- Keine proprietären Assets oder Kundendaten ins öffentliche Repo.

## Architektur-Leitplanken

### Offizieller Nextcloud Style-/Implementierungs-Guide

Als maßgeblicher technischer Style Guide gilt die offizielle [Nextcloud Developer Documentation](https://docs.nextcloud.com/server/latest/developer_manual/).

Sie dient bei Umsetzung und Reviews als Referenz für:

- App-Struktur und AppFramework-Konventionen
- Controller, Services, Dependency Injection
- Datenbankmigrationen
- Security-/Permission-Patterns
- API-/OCS-/Routing-Konventionen
- Frontend-/UI-Konventionen für Nextcloud-Apps
- Tests, Packaging und App-Store-Kompatibilität

Wenn eigene Entscheidungen davon abweichen, werden sie bewusst als ADR dokumentiert (siehe [`docs/adr/`](adr/)).

### Monorepo

```text
repo/
├── nextcloud/erp/              # Nextcloud-App/Add-on
├── docs/                       # Architektur, API, ADRs, Roadmap
├── docker/                     # Docker/Compose/Testumgebung
├── tests/                      # übergreifende Tests / Fixtures
├── client/flutter/             # späterer Flutter-Client, zunächst Platzhalter/Docs
├── LICENSE                     # MIT
└── README.md
```

### API-first, aber nicht Flutter-first

- Web-UI nutzt dieselbe Service-/Businesslogik wie die API.
- Flutter kann später andocken, ohne ERP-Regeln neu zu implementieren.
- Rechteprüfung bleibt serverseitig maßgeblich.
- Offline-/Sync-Fähigkeit wird im Datenmodell vorbereitet.

### Nextcloud-native Integration

- User/Gruppen aus Nextcloud als Identitätsbasis.
- Contacts für Kunden/Lieferanten/Ansprechpartner, ERP-Metadaten separat.
- Calendar für Termine, Personalplanung, Urlaub/Abwesenheiten, Werkstatt/TÜV.
- Files für Projektordner, Belege, Fotos, Dokumente.
- Mail optional, abhängig von stabiler API oder Fallback über serverseitigen Versand.

## Phase 0 — Projekt- und Architekturfundament

Ziel: Das öffentliche Projekt sauber startfähig machen.

Ergebnisse:

- Repository initialisieren oder Zielrepo festlegen.
- MIT-Lizenz anlegen.
- README mit Projektziel, Scope und Nicht-Zielen.
- Docker-/Compose-Grundlage für reproduzierbare Entwicklung.
- Nextcloud-Testinstanz per Docker starten.
- Datenbankdienst festlegen (PostgreSQL, siehe [ADR-0003](adr/0003-datenbank-postgresql.md)).
- Mindestversionen dokumentieren: Nextcloud, PHP, Node, Datenbank.
- Basis-Teststrategie dokumentieren.
- ADRs für Kernentscheidungen.

Prüfkriterien:

- Neue Maschine kann Umgebung aus Repo-Doku starten.
- Nextcloud läuft in Docker.
- Leere ERP-App lässt sich installieren/aktivieren.
- Lizenz und Projektstruktur sind öffentlichkeitstauglich.

## Phase 1 — Nextcloud-App-Skeleton und Web-Grundgerüst

Ziel: ERP-App läuft sichtbar in Nextcloud.

Ergebnisse:

- Nextcloud-App `erp` (siehe [ADR-0001](adr/0001-app-id-erp.md)).
- App-Navigation innerhalb Nextcloud.
- Grundlayout nach Nextcloud-UI-Konventionen.
- Dashboard-Platzhalter.
- Routing für Hauptbereiche: Dashboard, Projekte, Kalender & Personal, Kunden, Lieferanten, Artikel, Produkte, Angebote, Aufträge, Rechnungen, Lager, Fuhrpark, Kosten & Kalkulation, Stunden & Zeitkonto, Berechtigungen & Sätze, API & Sync, Einstellungen.
- App-spezifische Datenbankmigrationen.
- Basistests für Installation, Migration, Routing.

Prüfkriterien:

- App ist in Nextcloud installierbar.
- Web-UI öffnet stabil im Nextcloud-Frame.
- Navigation ist vollständig angelegt, auch wenn Module noch Platzhalter sind.
- Tests laufen per dokumentiertem Befehl auf frischer Docker-Umgebung.

## Phase 2 — Identität, Rechte und API-Grundlage

Ziel: Nextcloud-User/Gruppen nutzen und ERP-Rechte sauber trennen.

Ergebnisse:

- Nextcloud-User und Gruppen auslesen.
- ERP-Rechtemodell als eigene Tabellen/Objekte.
- Rechte-Matrix im Web-UI: kein Zugriff / lesen / lesen & schreiben / freigeben-buchen-abschließen / administrieren.
- API-Grundstruktur: Versionierung (`/api/v1/...`), Auth über Nextcloud-Session/App-Mechanismen, standardisierte Fehlerantworten, zentrale Rechteprüfung.
- API-Dokumentation in `docs/`.
- Erste API-Tests.

Prüfkriterien:

- ERP-Rechte sind technisch getrennt von Verrechnungssätzen.
- Rechte können Usern und Gruppen zugeordnet werden.
- API-Endpunkte lehnen unberechtigte Zugriffe reproduzierbar ab.
- API-Vertrag ist dokumentiert, auch wenn Flutter noch nicht gebaut wird.

## Phase 3 — Nextcloud-Integrationen: Contacts, Calendar, Files

Ziel: Beweisen, dass die App Nextcloud-native Bausteine sinnvoll nutzt.

Ergebnisse:

- Contacts-Referenzen für Kunden, Lieferanten, Ansprechpartner (ERP-Metadaten per Contact UID).
- Calendar-Referenzen für Projekttermine, Kundentermine, Personalplanung, Urlaub/Abwesenheiten.
- Files-Referenzen: ERP-Hauptordner `ERP/`, automatisch erzeugte Projektordner, definierte Unterordner, Datei-/Ordnerlinks im Web-UI.
- Tests mit reproduzierbaren Fixtures.

Prüfkriterien:

- Kunde/Lieferant kann aus Contacts referenziert werden.
- Projekt kann mit Contact, Calendar Event und Files-Ordner verknüpft werden.
- Web-UI zeigt die Verknüpfungen verständlich an.
- Keine vollständige Schattenkopie von Contacts/Calendar/Files ohne fachlichen Grund.

## Phase 4 — Projektkern: Kunden, Projekte, Aufträge

Ziel: Das ERP wird praktisch nutzbar für Projekt-/Auftragsverwaltung.

Ergebnisse: Kunden-/Lieferantenansicht mit Contacts-Bezug, Projektliste/-detail, Auftragserfassung, Projektstatus, Aufgaben/Checklisten-Grundlage, Projekt-Dashboard (Termine, Dokumente, Stunden, Material, Status), Web-UI für Baustellenleiter am Laptop nutzbar.

Prüfkriterien: Ein Projekt kann komplett im Web angelegt und verwaltet werden. Projektordner, Kunde und Termine sind verknüpft. Baustellenleiter kann am Laptop die relevanten Projektinformationen sehen und bearbeiten.

## Phase 5 — Artikel, Produkte, Angebote

Ziel: Angebotsprozess und Material-/Leistungsgrundlage.

Ergebnisse: Artikelstamm, MwSt.-Satz am Artikel, Hersteller-Art.Nr., Lieferantenpreise pro Artikel, Lieferanten-Art.Nr. je Lieferant, Produkte/Bundles aus Artikeln + Arbeitsleistungen. Angebote enthalten Positionen aus Artikeln, Produkten/Bundles, reinen Arbeitsstunden/Leistungen und Freitext-/Pauschalpositionen. Angebotsgruppen mit Gruppensumme netto. MwSt. wird erst am Ende auf die Nettosummen berechnet, bei mehreren Sätzen getrennt ausgewiesen. Arbeitsarten (Monteur, Meister, Programmierer, Prüfung/Dokumentation, optional Helfer/Azubi). Preis-/Satz-Snapshots beim Angebotsversand. Angebots-PDF im Projektordner.

Prüfkriterien: Ein Angebot kann im Web erstellt werden. Angebotsgruppen zeigen Nettogruppensummen. MwSt. wird erst im Abschlussblock berechnet. Artikel, Produkte und reine Arbeitsstunden können angeboten werden. Artikel und Leistungen werden getrennt kalkuliert. Preise/Sätze werden als Snapshot gespeichert.

## Phase 6 — Zeitwirtschaft und Verrechnungssätze

Ziel: Stunden und Satzlogik fachlich korrekt abbilden.

Ergebnisse: Stundenerfassung im Web, Stundenbuchung auf Projekt/Auftrag/Arbeitsart, Zeitkonto pro Mitarbeiter (Soll/Ist/Plus-/Minusstunden/Überstundenstatus), Urlaub/Abwesenheiten (Antrag/Genehmigung/Resturlaub/Calendar-Verknüpfung), Überstunden (abbummeln/auszahlen/Freigabestatus), MwSt.-Satz an Arbeitsarten, technisch getrenntes Satzmodell (Standard, Kundenvertrag, Arbeitsart/Rolle, User/Gruppe, Snapshot).

Prüfkriterien: Derselbe User kann je Arbeitsart unterschiedlich abgerechnet werden. Kundenvertrag kann Standardsatz überschreiben. Alte Stunden/Rechnungen ändern sich nicht durch spätere Satzänderungen. Urlaub/Abwesenheit ist mit Calendar sichtbar verknüpft.

## Phase 7 — Rechnungen, Gutschriften und Zahlungsstatus

Ziel: Aus Angebot/Auftrag abrechenbare Dokumente erzeugen.

Ergebnisse: Rechnungsentwürfe, Rechnungspositionen aus Material/Stunden/Angebot, Abschlags-/Teil-/Schlussrechnungen, Gutschriften/Storno-Konzept, Rechnungsnummernlogik, Pflichtangaben nach deutschem/EU-Kontext, Rechnungs-PDF im Projektordner, Zahlungsstatus, Export-Vorbereitung für Steuerberater.

Prüfkriterien: Rechnung kann nachvollziehbar aus Projekt/Auftrag/Angebot entstehen. Netto-Zwischensummen, Gruppensummen und MwSt.-Aufschlüsselung sind korrekt nachvollziehbar. Satz- und Preissnapshots sind unveränderlich nachvollziehbar. Nummernlogik ist manipulationsarm. Rechtliche offene Punkte sind dokumentiert, bevor produktiv abgerechnet wird.

## Phase 8 — Lager, Inventur, Bestellvorschläge

Ziel: Materialbestand und Verbrauch steuerbar machen.

Ergebnisse: Lagerorte (Zentrallager, optional Fahrzeuglager, optional projektgebundenes Baustellenlager), Soll-/Ist-Mengen, Mindestbestand je Artikel/Lagerort, Materialverbrauch, Inventurablauf, Bestellvorschläge, Lieferantenauswahl auf Basis von Preisen/Lieferzeiten/Mindestmengen.

Prüfkriterien: Verbrauch senkt Bestand korrekt. Soll/Ist ist fachlich verständlich. Fahrzeuglager bleibt optional. Bestellvorschläge sind bearbeitbar, nicht blind verbindlich.

## Phase 9 — Fuhrpark

Ziel: Fahrzeuge, Termine und Tankkosten im ERP führen.

Ergebnisse: Fahrzeugstamm, Fahrer/Zuweisungen, TÜV-Erinnerungen, Werkstatttermine mit Calendar-Verknüpfung, Tankbelege mit Foto, Kilometerstand, optional Fahrtenbuch, optional Fahrzeuglager-Anzeige.

Prüfkriterien: TÜV/Werkstatttermine erscheinen in Calendar. Tankbelege sind Fahrzeugen zugeordnet. Offline-Tankbeleg-Erfassung ist für später vorbereitet.

## Phase 10 — Betriebliche Kosten und Kalkulation

Ziel: Gemeinkosten und Zuschläge nachvollziehbar machen.

Ergebnisse: Kostenarten (Miete, Telefon/Internet, Software, Gehälter, Lohnnebenkosten, Versicherungen, Berufsgenossenschaft, Steuerberater, Fahrzeuge, Werkzeuge, Energie, Finanzierung/Leasing, Marketing, sonstige Gemeinkosten), Gemeinkosten pro Monat/Jahr, produktive Stunden als Bezugsgröße, interner Stundensatz, Materialaufschläge, Produktaufschläge, Auswertungen.

Prüfkriterien: Kalkulation ist nachvollziehbar, nicht nur ein fixer Aufschlag. Interne Kosten und externe Verrechnungssätze bleiben getrennt. Ergebnisse können in Angebote/Produkte einfließen.

## Phase 11 — Auswertungen, Dashboard, Exporte

Ziel: ERP wird steuerbar und auswertbar.

Ergebnisse: ERP-Dashboard, Projekt-Gewinn/Verlust, Soll/Ist-Vergleich, offene Angebote/Rechnungen, Mindestbestand/Bestellvorschläge, Fuhrpark- und Kostenübersichten, Zeitkonto-/Überstundenübersichten, Export für Steuerberater/Buchhaltung, optional XRechnung/ZUGFeRD.

Prüfkriterien: Dashboard zeigt handlungsrelevante Daten. Auswertungen berücksichtigen Rechte. Export ist reproduzierbar und dokumentiert.

## Phase 12 — Beleg-PDF-Export und Dokumentenarchiv

Ziel: Aus Angeboten, Aufträgen, Lieferscheinen, Rechnungen und Gutschriften lassen sich echte PDF-Dokumente erzeugen, automatisch mit Zeitstempel im richtigen Projektordner abgelegt.

Ergebnisse: `dompdf` als PDF-Bibliothek, gemeinsamer `DocumentPdfService`, PDF-Erzeugung beim Ausstellen/Senden/Bestätigen jedes der fünf Belegtypen (löst den bisherigen HTML-Export für Rechnungen aus ADR-0013 ab), Dateiname aus Belegnummer + Zeitstempel, ein Unterordner je Belegtyp im Projektordner, dokumentierte Admin-Anleitung für eine löschgeschützte Ablage über einen Nextcloud Group Folder (`Delete = verweigert`) — siehe ADR-0021.

Prüfkriterien: Jeder der fünf Belegtypen erzeugt beim jeweiligen “Fixieren”-Schritt ein echtes PDF im Projektordner. Dateiname enthält Belegnummer und Zeitstempel. Bestehende Tests und der bisherige Rechnungs-HTML-Export laufen sauber auf PDF um, ohne alte Belege rückwirkend zu verändern. Die Löschschutz-Empfehlung ist in `docs/status.md` dokumentiert, nicht automatisiert (bewusst, siehe ADR-0021).

## Phase 13 — Belegqualität: Firmenprofil, Gruppen im PDF, Positionspflege, Rabatte

Ziel: Die in Phase 12 erzeugten PDF-Dokumente sind tatsächlich an Kunden verwendbar statt nur technischer Nachweis, dass die Erzeugung funktioniert.

Ergebnisse: Firmenprofil (Name/Anschrift/USt-IdNr/Kontakt/Freitext-Fußzeile) unter Einstellungen, Kundenanschrift wird aus dem verknüpften Kontakt gezogen, Belegdatum und (nur Angebot) Bindefrist erscheinen im PDF, Positionsgruppen werden im PDF als Zwischenüberschriften dargestellt (nicht nur in der Web-Ansicht), Positionen (Menge/Preis/MwSt./Rabatt/Beschreibung) sind nach dem Anlegen noch änderbar statt nur löschbar, Rabatt pro Position sowie ein Gesamtrabatt je Beleg sind möglich (Angebot/Auftrag/Rechnung).

Prüfkriterien: Ein erzeugtes Angebots-PDF enthält Firmenname, Kundenanschrift, Datum und Bindefrist. Gruppentitel erscheinen im PDF in derselben Struktur wie in der Web-Ansicht. Eine bereits angelegte Position lässt sich in Menge, Preis und Rabatt korrigieren, ohne sie zu löschen und neu anzulegen. Ein Rabatt auf eine einzelne Position und ein Rabatt auf den gesamten Beleg wirken sich korrekt auf Netto-/MwSt.-/Bruttosumme aus und erscheinen im PDF.

## Phase 14 — Web-Reifegrad und Stabilisierung

Ziel: Das Nextcloud-Add-on wird reif genug, bevor Flutter gebaut wird.

Ergebnisse: Web-UI vollständig nutzbar für Kernprozesse, API stabil genug dokumentiert, Datenmodell migrationssicher, Docker-Testumgebung stabil, Tests auf mehreren Maschinen reproduzierbar, Rollen/Rechte getestet, Backup-/Restore-Verhalten geprüft, Sicherheitsreview, Lizenz-/Dependency-Review.

Prüfkriterien für „fertig/reif genug für Flutter”: Web-ERP kann Kernprozesse ohne Flutter abbilden. API-Endpunkte für Flutter-MVP sind versioniert und getestet. Offline-/Sync-Regeln sind dokumentiert. Rechteprüfung funktioniert serverseitig. Es gibt Testdaten für Monteur-/Projektleiter-Szenarien. Docker-Setup läuft reproduzierbar auf mindestens zwei unterschiedlichen Maschinen.

## Phase 15 — Flutter-Vorbereitung

Ziel: Flutter konkret bauen können, ohne Web-ERP umzubauen.

Ergebnisse: Flutter-MVP-Scope finalisieren, API-Endpunkte für Flutter-MVP einfrieren, Auth-Konzept (bevorzugt Nextcloud Login Flow v2), Offline-Datenpakete definieren, Outbox-Modell finalisieren, Konfliktregeln testen, Sicherheitskonzept für lokale Daten.

Prüfkriterien: Flutter kann gegen eine stabile Test-API entwickelt werden. Flutter muss keine Geschäftslogik duplizieren. Offline-Rechte sind serverseitig prüfbar.

## Phase 16 — Flutter-MVP

Ziel: Reduzierter Monteur-/Unterwegs-Client.

Ergebnisse: Login, Heute-Screen, eigene Projekte/Aufträge, Auftragsdetail, Dokumente/Pläne anzeigen, Stunden erfassen, Materialverbrauch erfassen, Fotos/Notizen erfassen, Tankbeleg erfassen, Offline-Outbox, Sync-Status.

Nicht enthalten: Buchhaltung, Rechnungen/Gutschriften, Artikelstamm-Verwaltung, Lieferantenpreise, Produktkalkulation, Kosten/Gemeinkosten, Rechte-/Satzadministration.

Prüfkriterien: Monteur kann auf der Baustelle offline arbeiten. Sync überträgt Daten nachvollziehbar. Konflikte werden sichtbar, nicht still verschluckt. Web bleibt führende Oberfläche.

## Nicht-Ziele für die erste Umsetzungsrunde

- Flutter-App direkt bauen.
- vollständige Lohnabrechnung ersetzen.
- vollständiges Finanzbuchhaltungsprogramm bauen.
- Zentrallager offline buchbar machen.
- alle Nextcloud-Community-ERP-Apps hart als Abhängigkeit einbauen.
- produktive Rechnungsstellung ohne rechtliche Prüfung.
