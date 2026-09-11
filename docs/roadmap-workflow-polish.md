# Roadmap — Workflow- und Bedienungsfeinschliff

**Status:** aktiv
**Stand:** 2026-09-11
**Auslöser:** Praxisdurchlauf im Nextcloud-ERP durch Kay

Diese Roadmap ergänzt die bisherigen Phasen 0–14. Sie priorisiert ausschließlich
Lücken, die den täglichen ERP-Ablauf blockieren oder eine sichere Nutzung von
Belegen und Stammdaten verhindern. Das ERP bleibt branchenneutral; Ausführung,
Vorlagen und Belegtexte werden konfigurierbar, nicht auf ein einzelnes Gewerk
fest verdrahtet.

## P0 — Bedienbarkeit und sichtbarer Arbeitsbereich

### Ziel
Jede ERP-Seite bleibt innerhalb des Nextcloud-Fensters bedienbar: kein seitliches
Verschwinden von Inhalten, ein klarer vertikaler Scrollbereich und bei breiten
Tabellen ein kontrollierter horizontaler Scrollcontainer.

### Arbeitspakete

1. Den App-Content als eigenständigen, vertikal scrollbaren Flexbereich führen.
2. Dashboard-Kacheln auf schmale Nextcloud-Fenster und Tablet-/Laptopbreiten
   prüfen; Karten dürfen ihre Grid-Spalte nicht überdehnen.
3. Tabellen in Kunden, Lieferanten, Lager und Belegen in responsiven
   Scrollcontainern führen, statt die gesamte Seite über den sichtbaren Bereich
   hinauszudrücken.
4. Browser-Regressionstest mit gefülltem Dashboard: unterer Inhalt muss per
   Scroll erreichbar sein, horizontaler Viewport bleibt innerhalb der App.

**Abnahme:** Ein Dashboard mit mehr Kacheln und einem Exportbereich ist bei
Laptop- und schmaler Fensterbreite vollständig erreichbar.

## P1 — Einkaufsworkflow und Lieferantenbestellungen

### Ziel
Aus Bestellvorschlägen und manuellen Positionen entstehen bearbeitbare
Lieferanten-Bestellentwürfe. Es gibt keine automatische E-Mail, Übermittlung
oder Warenbuchung ohne explizite Aktion.

### Arbeitspakete

1. Datenmodell für Bestellentwurf, Lieferantenreferenz, Status und Positionen.
2. Bestellentwurf aus markierten Bestellvorschlägen erzeugen; nach Lieferant
   bündeln, Mengen vor dem Speichern editierbar machen.
3. Manuelle Artikel-/Freitextpositionen ergänzen sowie Lieferanten auswählen.
4. Statusfolge `draft → approved → sent → partially_received → received /
   cancelled`; jede Statusänderung nachvollziehbar speichern.
5. Wareneingang erst über einen bewussten Empfangsschritt in vorhandene
   Lagerbewegungen überführen; kein blinder Bestandszugang beim Erstellen.
6. Projekt- und Lagerbezug pro Position dokumentieren.

**Abnahme:** Ein Mitarbeiter kann einen Bestellvorschlag auswählen, daraus einen
Entwurf je Lieferant erzeugen, prüfen, freigeben und einen echten Wareneingang
separat buchen.

## P2 — Kunden, Lieferanten und geteilte Kontakte

### Ziel
Kunden und Lieferanten lassen sich im ERP strukturiert verwalten, bleiben aber
Nextcloud-Contacts als Quelle für Namen, Anschriften und Ansprechpartner
verpflichtet.

### Arbeitspakete

1. Bestehende ERP-Verknüpfungen als vollständige Listen-/Detailansicht
   ausbauen: Suche, Link anlegen, ERP-Referenznummer, Zahlungsziel, Notizen,
   Bearbeiten und Entfernen.
2. Das dedizierte, gruppenweit freigegebene Adressbuch **ERP Kontakte** aus
   ADR-0024 als Betriebs-Voraussetzung prüfen und dokumentiert provisionieren.
3. Kontaktrollen `Kunde` und `Lieferant` als getrennte, kombinierbare
   ERP-Gruppierung führen. Ein Kontakt darf bewusst beide Rollen tragen.
4. Erst nach einer API-Kompatibilitätsprüfung entscheiden, ob die Gruppen auch
   als physische Nextcloud-Contacts-Kategorien / Kontaktgruppen geschrieben
   werden können. Die App darf dabei nicht an einer ungeschützten internen
   CardDAV-API hängen.
5. Für neue Stammdaten einen klaren Übergang in die native Contacts-App
   anbieten; keine zweite, unvollständige Kontaktkopie im ERP anlegen.

**Abnahme:** Alle ERP-Benutzer mit den vorgesehenen Gruppenrechten sehen dieselbe
Kunden-/Lieferantenbasis. Kunden- und Lieferantenfilter sind getrennt nutzbar,
ohne einen Kontakt mit beiden Rollen zu duplizieren.

## P3 — Firmenprofil und Beleggestaltung

### Ziel
Angebote, Aufträge, Rechnungen, Lieferscheine und Gutschriften basieren auf
einem vollständigen Firmenprofil und konfigurierbaren, versionierten
Belegvorlagen.

### Arbeitspakete

1. Bestehendes Firmenprofil um Logo, Rechtsform, Geschäftsführung,
   Handelsregister, Bankdaten und getrennte USt-IdNr./Steuernummer ergänzen.
2. Logo sicher als ERP-Datei ablegen und nur nach Typ-/Größenprüfung im
   Belegkopf verwenden; kein frei eingebettetes Daten-URL-Feld.
3. Template-Settings je Belegtyp: Kopf-/Fußzeile, Betreff, Einleitung,
   Schlusstext, Zahlungs-/Lieferhinweise, sichtbare Spalten und
   Dokumentnummern-Präfix.
4. Platzhalter klar begrenzen und serverseitig rendern (z. B.
   `{{company.name}}`, `{{customer.name}}`, `{{document.number}}`), niemals
   ausführbares HTML oder Skript aus Einstellungen übernehmen.
5. Beim Ausstellen eines Belegs die verwendete Template-/Profildaten-Version
   snapshotten, damit alte Belege unverändert nachvollziehbar bleiben.
6. PDF- und HTML-Regressionstests für Logo, Pflichtfelder, Gruppensummen und
   sichere Escape-Behandlung ergänzen.

**Abnahme:** Ein Beleg kann ohne Codeänderung mit Firmenlogo, korrekten
Firmenangaben und dem für den Belegtyp vorgesehenen Text-/Layoutprofil erzeugt
werden; spätere Änderungen verfälschen keine bereits ausgestellten Belege.

## P4 — Geführter End-to-End-Workflow

### Ziel
Der vollständige Ablauf wird in der realen Oberfläche nachvollziehbar,
rollenbewusst und testbar.

### Arbeitspakete

1. Geführte Prüfszenarien erstellen: Kontakt → Projekt → Angebot → Auftrag →
   Lieferant/Bestellung → Wareneingang → Lieferschein → Teil-/Schlussrechnung
   → Zahlung.
2. Pro Schritt Pflichtfelder, Statusübergänge und nächste erlaubte Aktionen
   sichtbar machen.
3. Fehlermeldungen und leere Zustände auf fachliche, verständliche Hinweise
   prüfen.
4. Browser-Smoke-Test für den gesamten Ablauf und eine Rechte-Matrix für
   Projektleitung, Buchhaltung und Monteur ergänzen.

## Umsetzungsschnitt

Der erste technische Schnitt bearbeitet P0 samt lokaler Docker-Reproduzierbarkeit
und schreibt die Roadmap ein. Danach folgen P1 und P2 in getrennten Feature-
Branches. P3 wird erst umgesetzt, sobald die fachliche Entscheidung zum Umfang
und zur Gestaltung der Vorlagen bestätigt ist.

## Offene Entscheidungen vor P3

- Soll Beleganpassung aus festen, sicheren Bereichen und Platzhaltern bestehen
  (empfohlen), oder wird ein frei gestaltbarer WYSIWYG-Editor benötigt?
- Welche Angaben sollen als Pflichtfelder getrennt geführt werden: USt-IdNr.,
  Steuernummer, Handelsregister, Geschäftsführung, Bankdaten?
- Soll das Logo nur als Briefkopf in PDFs erscheinen oder zusätzlich in der
  ERP-Navigation/auf dem Dashboard?

## Nicht im ersten Schnitt

- Automatischer Versand von Bestellungen an Lieferanten.
- Produktive Steuer-/Rechtskonformitätsfreigabe ohne gesonderte Prüfung.
- Flutter-/Offline-Client.
- Duplizierte eigene Kontakt- oder Adressverwaltung neben Nextcloud Contacts.
