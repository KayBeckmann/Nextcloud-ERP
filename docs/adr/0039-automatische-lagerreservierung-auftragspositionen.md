# ADR-0039: Automatische Lagerreservierung für Auftragspositionen

**Status:** accepted
**Datum:** 2026-10-02

## Kontext

`status.md` führte seit Phase 8 (ADR-0014) als bekannte Einschränkung:
"Keine automatische Reservierungslogik gegen Angebots-/
Auftragspositionen — `reserve()`/`release()` sind manuelle Aufrufe ohne
Automatismus."

**Befund beim Umsetzen:** `OrderPosition` hatte **kein Lager-Feld** —
`StockService::reserve()`/`release()` brauchen aber
`articleId + warehouseId + quantity`, und es gab keine Verknüpfung
"diese Reservierung gehört zu dieser Auftragsposition" (anders als
Bestandsbewegungen, die `referenceType`/`referenceId` haben). Außerdem
hatte die Belegkette Auftrag → Lieferschein bisher **überhaupt keine**
Berührung mit der Bestandsführung — auch der tatsächliche Warenabgang
bei Lieferschein-Erstellung war bis zu diesem ADR nie automatisch
gebucht, sondern ausschließlich manuell über die Lager-Ansicht.

Mit Kay abgestimmt: `OrderPosition` bekommt ein optionales Lager-Feld;
Anlegen/Ändern einer Artikel-Position mit Lager reserviert/passt die
Reservierung automatisch an; Löschen der Position gibt frei; eine
Lieferschein-Erstellung aus der Position wandelt die gelieferte Menge
aus der Reservierung in einen echten, gebuchten Warenabgang um.

## Entscheidung

### Scope: nur `positionType = 'article'`

Reservierung gilt **ausschließlich für echte Artikel-Positionen**.
`product`-Positionen bündeln mehrere Artikel/Arbeitszeit
(`ProductComponent`/`ProductLabor`, ADR-0011) — Bestand wird in diesem
Projekt ausschließlich je Artikel geführt, nie je Produkt. Eine
automatische Auflösung "Produktmenge × Komponenten-Stückliste
reservieren" wäre ein eigenes, größeres Feature (siehe "Nicht Teil
dieser Phase"). `labor`/`custom` haben nie Bestand.

`addPosition()`/`updatePosition()` werfen `\InvalidArgumentException`,
wenn `warehouseId` bei einem Nicht-Artikel-Typ gesetzt wird.

### Neues Feld, kein separates Reservierungs-Tracking

`OrderPosition` bekommt `warehouseId` (nullable, Migration
`Version0026`). **Keine separate Tabelle** für "welche Reservierung
gehört zu welcher Position" — die Reservierung wird implizit durch
`(referenceId, warehouseId, quantity)` der Position selbst
repräsentiert: solange eine Position mit Lager existiert, entspricht
ihre aktuelle Menge exakt der reservierten Menge. Das hält das
Datenmodell schlank, erfordert aber, dass **jede Änderung der Position
die Reservierung konsistent mitführt**:

- `addPosition()`: reserviert die volle Menge, wenn `warehouseId`
  gesetzt ist.
- `updatePosition()`: gibt die **alte** `(warehouseId, quantity)`-
  Kombination frei und reserviert die **neue** — funktioniert
  unverändert korrekt, egal ob sich nur die Menge, nur das Lager, oder
  beides ändert (auch beim Wechsel auf "kein Lager").
- `removePosition()`: gibt die bestehende Reservierung frei, bevor die
  Position gelöscht wird.

**`warehouseId` folgt der üblichen PUT-Ersetzungs-Semantik dieser API**
(wie z. B. `CompanyProfileService::update()`): weglassen bei `PUT`
bedeutet "kein Lager mehr", nicht "unverändert lassen". Das Web-UI
sendet bei jedem Speichern immer den vollständigen, aktuell angezeigten
Zustand mit, daher keine praktische Überraschung.

### Lieferschein-Erstellung wandelt Reservierung in Warenabgang um

`DeliveryNoteService::createFromOrder()` bekommt einen neuen,
**optionalen** Parameter `$createdByUserId` (Default `null`, um alle
23 bestehenden Testaufrufstellen unverändert zu lassen — keine von
ihnen nutzt eine lagergebundene Position). Für jede konvertierte
Artikel-Position mit `warehouseId`:

1. `StockService::release()` für die gelieferte Teilmenge — unabhängig
   davon, ob `$createdByUserId` übergeben wurde (reine Buchhaltung,
   keine Attribution nötig).
2. **Nur wenn** `$createdByUserId` übergeben wurde:
   `StockService::recordMovement()` mit `movementType = 'consumption'`,
   `referenceType = 'delivery_note'` — der erste automatisch gebuchte
   Bestandsvorgang in der gesamten Belegkette (bisher war jede
   Bestandsbuchung manuell über die Lager-Ansicht).

`DeliveryNoteController::createFromOrder()` übergibt den eingeloggten
User automatisch.

## Nicht Teil dieser Phase

- **Keine automatische Reservierung für `product`-Positionen** (siehe
  oben) — bündelübergreifende Komponenten-Auflösung wäre ein eigenes
  Feature.
- **Keine Prüfung der Verfügbarkeit** vor dem Reservieren —
  `StockService::reserve()` erlaubte schon vorher Überbuchung
  (`quantityReserved` kann `quantityOnHand` übersteigen), dieses
  Verhalten bleibt unverändert; ADR-0039 führt keine neue Prüfung ein.
- **Keine Reservierung für Angebote** (`QuotePosition`) — ein Angebot
  ist keine verbindliche Zusage (ADR-0035 behandelt Angebote bereits
  separat als "nicht verbindlich genug" für den Entwurfs-Zwang), eine
  Lagerbindung wäre hier fachlich verfrüht.
- **Kein "Auftrag stornieren"-Trigger** für eine Massen-Freigabe aller
  Reservierungen eines Auftrags — `Order` kennt keinen
  Stornierungs-Status (`OrderStatus`: nur `draft`/`confirmed`/`done`,
  ADR-0016); Freigabe läuft ausschließlich über Positions-Löschen/
  -Ändern.
- **Keine UI-Warnung bei unzureichendem Bestand** beim Reservieren.

## Konsequenzen

- `OrderService`/`DeliveryNoteService` bekommen eine neue
  Konstruktor-Abhängigkeit (`StockService`) — alle manuellen
  Testaufrufstellen (`OrderServiceTest`, `ReportingServiceTest`,
  `DeliveryNoteServiceTest`) mussten angepasst werden.
- `AuftragDetailView` bekommt eine neue Lager-Spalte in der
  Positionstabelle sowie ein Lager-Dropdown im Anlage-/Bearbeiten-
  Formular für Artikel-Positionen.
- Erste automatische Bestandsbuchung im Projekt überhaupt
  (`consumption` bei Lieferschein-Erstellung) — bisher lief jede
  `StockMovement`-Buchung ausschließlich manuell über die Lager-Ansicht
  (`StockController`).

## Alternativen erwogen

- **Eigene Verknüpfungstabelle `erp_order_position_reservations`**
  (statt der Position selbst als implizite Reservierung zu behandeln):
  verworfen — hätte eine 1:1-Beziehung zur Position dupliziert, ohne
  einen Anwendungsfall zu bedienen, der die Position selbst nicht schon
  abdeckt (eine Position hat nie mehr als eine Reservierung
  gleichzeitig in diesem Entwurf).
- **`$createdByUserId` als Pflichtparameter**: verworfen — hätte alle
  23 bestehenden `createFromOrder()`-Aufrufstellen in Tests zum Ändern
  gezwungen, ohne fachlichen Mehrwert für Aufrufer ohne
  Lager-Reservierungs-Bezug.
