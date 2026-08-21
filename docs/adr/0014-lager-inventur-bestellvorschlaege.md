# ADR-0014: Lager, Inventur und Bestellvorschläge

**Status:** accepted
**Datum:** 2026-08-20

> **Hinweis (2026-08-21):** Die Fuhrpark-Verknüpfung für Fahrzeuglager
> wurde in [ADR-0017](0017-fuhrpark.md) nachgezogen —
> `erp_warehouses.vehicle_id` (nullable) verweist jetzt optional auf
> einen echten `erp_vehicles`-Datensatz. Der Rest dieses ADRs gilt
> unverändert.

## Kontext

Roadmap Phase 8 verlangt Materialbestand und -verbrauch steuerbar zu
machen: mehrere Lagerorte (Zentrallager, optional Fahrzeug-/
Baustellenlager), Soll-/Ist-Mengen, Mindestbestand je Artikel/Lagerort,
Materialverbrauch, einen Inventurablauf und bearbeitbare
Bestellvorschläge, die nie blind verbindlich ausgelöst werden. Das
Brainstorming warnt ausdrücklich, dass Fahrzeuglager realistisch nicht
zuverlässig gepflegt werden — das System muss auch ohne sie funktionieren.

## Entscheidung

### Lagerorte als eine Tabelle mit Typ-Diskriminator

`erp_warehouses` (name, type: `central`/`vehicle`/`site`, project_id
nullable — nur für `site`-Lagerorte gesetzt, active, notes). Kein Zwang zu
Fahrzeug-/Baustellenlagern — ein frisch installiertes System kann mit
einem einzigen Zentrallager arbeiten; `vehicle`/`site` sind rein optionale
zusätzliche Lagerorte, keine Pflichtstruktur (Brainstorming-Vorgabe:
"muss aber auch ohne perfekt gepflegte Fahrzeuglager funktionieren").
Ein `vehicle`-Lagerort referenziert bewusst noch kein Fuhrpark-Fahrzeug
(Phase 9 existiert noch nicht) — der Name ist Freitext, die technische
Verknüpfung zu `erp_vehicles` kann in Phase 9 nachgezogen werden, ohne das
Schema hier zu brechen (kein `vehicle_id`-Fremdschlüssel, den es noch
nicht geben kann).

### Soll-Menge als Live-Berechnung, keine gespeicherte Spalte

`erp_stock_levels` (article_id, warehouse_id, quantity_on_hand — Ist-Menge,
quantity_reserved — für geplante Entnahmen reservierte Menge, min_quantity
— Mindestbestand, UNIQUE(article_id, warehouse_id)). **Soll-Menge wird
nicht gespeichert**, sondern als `quantity_on_hand − quantity_reserved`
live berechnet (`StockCalculator`, pure Logik) — identisches Prinzip wie
das Zeitkonto in ADR-0012 und die Netto-/MwSt.-Berechnung in ADR-0011:
kein Caching, keine Inkonsistenzgefahr zwischen gespeichertem und
tatsächlichem Wert. Ein Nachbestellbedarf besteht, wenn **entweder** die
Ist-Menge **oder** die Soll-Menge unter den Mindestbestand fällt
(Brainstorming-Wortlaut: "Wenn Ist-Menge oder prognostizierter
Soll-Bestand unter Mindestbestand fällt").

### Materialverbrauch als Bewegungsprotokoll, nicht direkte Bestandsänderung

`erp_stock_movements` (article_id, warehouse_id, quantity_delta —
vorzeichenbehaftet, movement_type: `receipt`/`consumption`/`adjustment`/
`transfer_in`/`transfer_out`/`inventory_correction`, reference_type
nullable, reference_id nullable, user_id, notes). Jede Bestandsänderung
läuft über `StockService::recordMovement()`, das **immer** einen
Bewegungssatz schreibt und `quantity_on_hand` in derselben Operation
fortschreibt — nie eine direkte Spaltenänderung ohne Protokolleintrag.
Das erfüllt implizit einen Teil der im Brainstorming offen gelassenen
Frage nach einem Audit-Log für Lagerbuchungen: jede Mengenänderung ist
nachvollziehbar, wer/wann/warum. Negative resultierende Bestände werden
abgelehnt (`\DomainException`) — ein Verbrauch kann nicht mehr abbuchen,
als vorhanden ist (Prüfkriterium "Verbrauch senkt Bestand korrekt").
`StockService::transfer()` ist eine Komfortmethode, die einen
`transfer_out`- und einen `transfer_in`-Bewegungssatz atomar erzeugt, um
Bestand zwischen zwei Lagerorten zu verschieben (z. B. Zentrallager →
Baustellenlager).

### Inventur: Zählung snapshotet den erwarteten Bestand zum Zählzeitpunkt

`erp_inventories` (warehouse_id, status: `open`/`closed`, started_at,
closed_at nullable, notes, created_by) + `erp_inventory_counts`
(inventory_id, article_id, counted_quantity, expected_quantity, UNIQUE
(inventory_id, article_id)). `expected_quantity` wird **beim Erfassen der
Zählung** (nicht beim Start der Inventur) aus dem aktuellen
`quantity_on_hand` übernommen — vermeidet verfälschte Differenzen, falls
während einer länger laufenden Inventur parallel andere Bewegungen
gebucht werden. Beim Abschließen (`close()`) wird für jede Zählung mit
Differenz ≠ 0 automatisch ein `inventory_correction`-Bewegungssatz
erzeugt, der den Bestand auf die gezählte Menge korrigiert — Inventur
läuft damit über denselben Bewegungsmechanismus wie jeder andere
Bestandsvorgang, keine Sonderlogik nötig.

### Bestellvorschläge: reiner Lesevorgang, nie gespeichert

Bewusst **keine** `erp_purchase_suggestions`-Tabelle. `PurchaseSuggestion
Service` berechnet bei jeder Abfrage live, welche Artikel/Lagerort-
Kombinationen unter ihrem Mindestbestand liegen, wie viel nachbestellt
werden müsste (`PurchaseSuggestionCalculator`, pure Logik:
`suggestedQuantity = max(minQuantity − quantityOnHand, 0)`
— Mindestbestand-basierte Regel, die im Brainstorming explizit als
Alternative zu einer komplexeren Ziel-/Sollbestand-Regel genannt wird) und
listet die hinterlegten Lieferantenpreise (`erp_article_supplier_prices`
aus ADR-0011, bereits nach `purchase_price` sortiert) je Artikel auf.
**Das erfüllt das Prüfkriterium "Bestellvorschläge sind bearbeitbar und
werden nicht blind verbindlich ausgelöst" strukturell**: Ohne gespeicherte
Bestellvorschlags-Entität kann nichts automatisch "ausgelöst" werden — der
Vorschlag ist immer nur eine Momentaufnahme, die der Nutzer liest und
manuell in eine echte Bestellung (außerhalb des Systems oder in einer
späteren Einkaufs-Phase) überführt.

### Rechte

`ResourceType::Lager` (bereits im Rechte-Enum aus ADR-0008 vorgesehen) für
Lagerorte, Bestände, Bewegungen und Inventuren.

## Nicht Teil dieser Phase

- **Fuhrpark-Verknüpfung für Fahrzeuglager** — `type = 'vehicle'` ist
  bewusst nur ein Namensfeld, keine Fremdschlüsselbeziehung zu einem noch
  nicht existierenden Fahrzeug-Datensatz (Phase 9).
- **Offline-Synchronisierung von Materialverbrauch** (Outbox-Muster,
  Rechteprüfung/Konfliktauflösung beim Sync) — explizit eine Aufgabe der
  späteren Flutter-Phasen (13–14), nicht des Web-MVP.
- **Reservierungslogik gegen geplante Projekte** — `quantity_reserved`
  existiert als Feld und wird über einfache `reserve()`/`release()`-
  Aufrufe manuell verwaltet, aber es gibt (noch) keine automatische
  Verknüpfung zu Angebots-/Auftragspositionen, die Reservierungen
  automatisch anlegt.
- **Eigene Bestellungs-/Einkaufs-Entität** (aus einem Vorschlag eine
  echte, versendete Bestellung mit eigenem Status machen) — der
  Bestellvorschlag bleibt bewusst ein reiner Bericht, keine
  Workflow-Engine für den Einkaufsprozess selbst.
- **Ziel-/Sollbestand-basierte Bestellmengen-Regel** (auffüllen auf einen
  Zielbestand oberhalb des Mindestbestands) — nur die einfachere
  Mindestbestand-Regel ist implementiert, siehe oben.

## Konsequenzen

- Jede Bestandsänderung ist über `erp_stock_movements` nachvollziehbar,
  auch ohne ein separates Audit-Log-Feature.
- Soll-Menge und Bestellvorschläge können nie veralten, weil sie nie
  gespeichert werden — kosten dafür bei jeder Abfrage eine Neuberechnung,
  was bei den hier realistischen Artikel-/Lagerortmengen unproblematisch
  ist (gleiches Argument wie bei ADR-0011/ADR-0012).
- Negative Bestände sind strukturell ausgeschlossen — ein Buchungsfehler
  führt zu einem klaren Fehler statt zu einem stillen, falschen Bestand.

## Alternativen erwogen

- Soll-Menge als gespeicherte, bei jeder Bewegung/Reservierung
  aktualisierte Spalte: mehr Komplexität (Neuberechnung bei nachträglicher
  Korrektur einer Bewegung), kein Vorteil gegenüber Live-Berechnung.
- Bestellvorschläge als persistente Entität mit eigenem Status (`open`/
  `ordered`/`dismissed`): näher an einem vollständigen Einkaufsmodul, aber
  deutlich mehr Scope als das Phase-8-Prüfkriterium verlangt — kann bei
  Bedarf als eigene spätere Ausbaustufe ergänzt werden, ohne das
  Grundschema hier zu ändern (der `PurchaseSuggestionService` bliebe die
  Berechnungsgrundlage).
- Direkte `vehicle_id`-Fremdschlüsselspalte auf `erp_warehouses`, die erst
  in Phase 9 befüllt wird: hätte eine Spalte auf ein in dieser Phase noch
  nicht existierendes Ziel referenziert — bewusst zurückgestellt, bis
  Phase 9 die Zieltabelle liefert.
