# ADR-0011: Datenmodell für Artikel, Produkte und Angebote

**Status:** accepted
**Datum:** 2026-08-20

## Kontext

Roadmap Phase 5 verlangt einen vollständigen Angebotsprozess: Artikel mit
Lieferantenpreisen, Produkte/Bundles aus Artikeln + Arbeitsleistungen, und
Angebote, die Artikel-, Produkt-, reine Arbeitsstunden- und
Freitextpositionen in Gruppen mit Netto-Gruppensummen mischen können —
MwSt. wird erst im Abschlussblock berechnet, nicht pro Position/Gruppe.

## Entscheidung

### MwSt.-Sätze und Arbeitsarten als gemeinsames Fundament

`erp_vat_rates` (name, percentage, is_default, active) und `erp_work_types`
(name, hourly_rate, vat_rate_id, active) sind eigene, einfache Tabellen.
Verwaltung ist Teil von **Einstellungen** (`ResourceType::Einstellungen`) —
kein eigener Rechtebereich, weil sie systemweite Stammdaten sind, keine
Projekt-/Kundendaten. Deckt sich mit dem Mockup (Screen 21: MwSt. unter
Einstellungen).

### Artikel

`erp_articles` (name, manufacturer, manufacturer_article_no, unit, category,
vat_rate_id, notes) + `erp_article_supplier_prices` (article_id,
supplier_contact_uid, supplier_article_no, purchase_price, currency,
min_order_quantity, delivery_time). Ein Artikel kann mehrere
Lieferantenpreise haben (Brainstorming-Vorgabe). `supplier_contact_uid`
referenziert wie bei Kunden/Lieferanten (ADR-0009) direkt eine Nextcloud-
Contact-UID, keine Pflicht-Verknüpfung über `erp_contact_links`.
Rechtebereich: `ResourceType::Artikel`.

### Produkte/Bundles

`erp_products` (name, description, vat_rate_id, notes) +
`erp_product_components` (product_id, article_id, quantity, unit) +
`erp_product_labor` (product_id, work_type_id, hours) — Material- und
Arbeitskomponenten bewusst getrennt (Brainstorming: "Materialbestandteile
haben immer eine Menge und Einheit", "Arbeitsleistungen bestehen aus Stunden
... und einem Verrechnungssatz"). Rechtebereich: `ResourceType::Produkte`.

### Angebote

`erp_quotes` (quote_number analog zu Projektnummer: `A-%05d` nach Insert,
project_id nullable — "Angebot kann ohne Projekt starten", customer_contact_uid
nullable, status, valid_until, notes, sent_at).

`erp_quote_groups` (quote_id, title, position) — Positionsgruppen mit fester
Reihenfolge.

`erp_quote_positions` (quote_id, group_id nullable — ungruppierte Positionen
erlaubt, position_type: article/product/labor/custom, reference_id nullable,
description, quantity, unit, unit_price_net, vat_rate_percent, position_order).

**Snapshot-Prinzip (statt Live-Referenz):** `unit_price_net` und
`vat_rate_percent` sind eigene Werte der Position, keine berechneten Felder,
die live vom referenzierten Artikel/Produkt/Arbeitstyp abgeleitet werden.
Beim Hinzufügen einer Position wird der aktuelle Preis/Satz einmalig
übernommen; spätere Änderungen am Artikel/Produkt/Arbeitstyp wirken sich
**nicht** rückwirkend auf bereits hinzugefügte Positionen aus. Das erfüllt
"Preise/Sätze werden als Snapshot gespeichert" einfacher als eine getrennte
Live-Referenz-plus-Freeze-bei-Versand-Logik, mit demselben Ergebnis für den
Nutzer: ein einmal erstelltes Angebot ändert sich nicht durch spätere
Preispflege. `sent_at` markiert zusätzlich den Versandzeitpunkt für die
Nachvollziehbarkeit, verändert aber keine Positionsdaten.

**Netto-/MwSt.-Berechnung:** Gruppensummen und Gesamtsumme werden **nicht**
gespeichert, sondern bei jedem Abruf aus den Positionen berechnet
(`QuoteCalculationService`) — vermeidet Inkonsistenzen zwischen
gespeicherter Summe und tatsächlichen Positionen. Berechnung:

1. Je Position: `netTotal = quantity × unit_price_net`.
2. Je Gruppe: Summe der `netTotal` ihrer Positionen (+ ungruppierte
   Positionen als "virtuelle" Restgruppe im Abschlussblock).
3. Gesamt: Netto-Zwischensumme = Summe aller Gruppen. MwSt. wird **erst
   hier**, getrennt je vorkommendem `vat_rate_percent`, auf die jeweilige
   Netto-Teilsumme berechnet. Brutto-Gesamt = Netto-Zwischensumme + Summe
   aller MwSt.-Teilbeträge.

Rechtebereich: `ResourceType::Angebote`.

### Nicht Teil dieser Phase

- **Angebots-PDF-Export.** Erfordert eine neue Abhängigkeit (PDF-Bibliothek
  oder externer Renderer) und damit eine eigene Architekturentscheidung
  (Lizenz, Ressourcenverbrauch, Wartung) — bewusst nicht "nebenbei"
  mitentschieden. Alle expliziten Roadmap-Prüfkriterien für Phase 5 sind auch
  ohne PDF-Export erfüllbar (Web-Erstellung, Gruppensummen, MwSt.-Logik,
  gemischte Positionstypen, Preis-Snapshots). PDF-Export wird als eigener
  Punkt vor Phase 12 (Web-Reifegrad) nachgezogen.
- Alternativpositionen/optionale Gruppen (Brainstorming nennt das
  ausdrücklich "nicht sofort MVP-Pflicht").
- Angebot → Auftrag-Übernahme (bestehender `erp_orders` aus Phase 4 bleibt
  vorerst unabhängig von Angebotspositionen, siehe ADR-0010).

## Konsequenzen

- Preisänderungen an Artikeln/Produkten/Arbeitsarten wirken nur auf **neue**
  Positionen, nie auf bestehende — entspricht der Preisbindungs-Vorgabe ohne
  zusätzlichen "Freeze"-Mechanismus.
- Netto-/MwSt.-Berechnung ist ein reiner Lesevorgang (kein Caching, keine
  Neuberechnungs-Jobs nötig) — für die bei Angeboten üblichen Positionsmengen
  unproblematisch.
- Ohne PDF-Export bleibt "Angebot versenden" vorerst eine Status-Änderung
  (`sent_at` setzen), kein tatsächlicher Mail-/Dokumentenversand.

## Alternativen erwogen

- Gruppensummen/Gesamtsumme in der DB persistieren und bei jeder
  Positionsänderung neu berechnen (Trigger/Event): mehr Komplexität, keine
  greifbaren Vorteile bei den hier realistischen Positionsmengen pro Angebot.
- Live-Referenz auf Artikelpreis mit explizitem "Preise einfrieren"-Button
  beim Versand: näher am Brainstorming-Wortlaut, aber deutlich mehr
  Zustandslogik (welche Positionen sind noch "live", welche eingefroren) für
  denselben Nutzeffekt wie das gewählte Sofort-Snapshot-Prinzip.
