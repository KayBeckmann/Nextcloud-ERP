# ADR-0032: Verkaufspreis für Artikel/Produkte + Live-Preisabgleich in der Positions-Maske

**Status:** accepted
**Datum:** 2026-10-01

## Kontext

`status.md` führte seit Phase 5 als bekannte Einschränkung: "Kein
Live-Preisabgleich beim Auswählen eines Artikels/Produkts/Arbeitstyps
in der Angebotsposition — der Web-UI-Nutzer trägt EP/MwSt. aktuell noch
manuell ein […]. Fachlich durch das Snapshot-Prinzip (ADR-0011)
gedeckt, aber noch kein Komfort-Feature im UI."

**Befund beim Umsetzen:** Die Positions-Maske (Angebot/Auftrag/
Rechnung) hatte bisher **gar keine Auswahl-Möglichkeit** für einen
konkreten Artikel/Produkt-Datensatz — nur eine Kategorie-Auswahl
(`positionType`: Artikel/Produkt/Arbeitsstunden/Freitext) plus manuelle
Freitextfelder. `referenceId` existiert zwar serverseitig seit ADR-0011
(Snapshot-Verweis auf den Quelldatensatz), wurde aber vom Web-UI nie
gesetzt. Außerdem hatten `Article`/`Product` **keinen Verkaufspreis** —
nur `Article` hat Einkaufs-/Lieferantenpreise (ADR-0019,
Kostenkalkulation), `Product` gar keinen Preis. Nur `WorkType` hat
einen echten Verkaufs-Stundensatz (`hourlyRate`).

Mit Kay abgestimmt: statt eines reinen UI-Komfort-Fixes (nur Name/MwSt.
vorbefüllen, Preis bleibt immer manuell) wird ein echter Verkaufspreis
für Artikel/Produkt ergänzt, damit der Preisabgleich für alle drei
Positionstypen funktioniert.

## Entscheidung

### Neues Feld `sellingPriceNet`

Migration `Version0024Date20261001170000` fügt `selling_price_net`
(nullable `DECIMAL(10,2)`) zu `erp_articles` und `erp_products` hinzu —
getrennt von `ArticleSupplierPrice` (Einkauf) und ohne Bezug zu
`ProductComponent`/`ProductLabor` (keine automatische Kalkulation aus
Komponenten, ein manuell gepflegter Festpreis). `null` bedeutet "kein
Standardpreis hinterlegt" — die Positions-Maske befüllt den Preis dann
nicht automatisch, verhält sich wie vor diesem ADR.

`ArticleService`/`ProductService` `create()`/`update()` bekommen den
neuen, optionalen Parameter `?float $sellingPriceNet = null`.

### Auswahl-Dropdown + Autofill in der Positions-Maske

`AngebotDetailView`/`AuftragDetailView`/`RechnungDetailView` laden beim
Mounten zusätzlich `articles`, `products`, `workTypes`. Je nach
gewähltem `positionType` erscheint ein weiteres Dropdown (Artikel/
Produkt/Arbeitstyp); eine Auswahl setzt `referenceId` und ruft
`applyReferencePrefill()` auf:

- **Artikel:** `description` = Name, `unit` = Artikel-Einheit,
  `unitPriceNet` = `sellingPriceNet` (nur wenn gesetzt), `vatRatePercent`
  aus `vatRateId` aufgelöst.
- **Produkt:** `description` = Name, `unitPriceNet` =
  `sellingPriceNet` (nur wenn gesetzt), `vatRatePercent` aufgelöst. Kein
  `unit`-Feld auf `Product` — Einheit bleibt wie bisher frei editierbar.
- **Arbeitstyp:** `description` = Name, `unitPriceNet` = `hourlyRate`
  (immer gesetzt, kein `null`-Fall), `unit` = "Std.", `vatRatePercent`
  aufgelöst.

**Bewusst reine Vorbefüllung, kein Zwang:** Alle Felder bleiben nach dem
Autofill normal editierbar — entspricht dem bestehenden
Snapshot-Prinzip (ADR-0011): die Position speichert ihre eigenen Werte,
`referenceId` ist nur ein Rückverweis, keine Live-Bindung. Wechselt der
Nutzer `positionType`, wird `referenceId` zurückgesetzt (die
Dropdown-Liste wechselt die Quelle).

**Fehlender Verkaufspreis löst kein Überschreiben mit `0`/`null` aus:**
`applyReferencePrefill()` lässt `unitPriceNet` unverändert, wenn der
gewählte Artikel/das Produkt keinen `sellingPriceNet` hat — ein
manuell schon eingetragener Preis wird nicht durch einen fehlenden
Wert überschrieben.

### Web-UI: Verkaufspreis pflegen

`ArtikelView`/`ProdukteView` bekommen ein optionales
Verkaufspreis-Eingabefeld im Anlageformular sowie eine Anzeige-Spalte/
-Zeile in der Liste. **Kein Bearbeiten-Formular** — weder `Article`
noch `Product` hatten vor diesem ADR eine Bearbeiten-UI (nur Anlegen),
das bleibt unverändert; `updateArticle()`/`updateProduct()` unterstützen
`sellingPriceNet` serverseitig zwar bereits, sind aber weiterhin nur
über die API, nicht über ein Web-UI-Formular erreichbar.

## Nicht Teil dieser Phase

- **Kein Bearbeiten-Formular für Artikel/Produkte** im Web-UI (siehe
  oben) — vorbestehende Lücke, durch dieses ADR nicht neu eingeführt,
  aber auch nicht geschlossen.
- **Keine automatische Kalkulation des Produkt-Verkaufspreises** aus
  Komponenten-Einkaufspreisen + Arbeitszeit × Stundensatz — bliebe ein
  eigenes, größeres Feature ("Produktkalkulation").
- **Kein Pflichtfeld/keine Warnung**, wenn ein Artikel/Produkt ohne
  Verkaufspreis in einer Position verwendet wird — der Nutzer trägt den
  Preis dann weiterhin manuell ein, wie bisher.
- **`Lieferschein`-Positionen bleiben außen vor** — Lieferscheine haben
  bewusst keine Preise (ADR-0015), ein Preisabgleich wäre dort
  fachlich nicht sinnvoll.

## Konsequenzen

- `Article`/`Product` bekommen ein neues, nullables Feld. Alle
  manuellen Testaufrufstellen (`ArticleServiceTest`,
  `ProductServiceTest`) sind davon nicht betroffen, da der neue
  Parameter einen Default (`null`) hat.
- Drei Detail-Views (Angebot/Auftrag/Rechnung) laden beim Mounten
  zusätzlich Artikel-, Produkt- und Arbeitstyp-Listen — bei sehr vielen
  Stammdaten könnte das die initiale Ladezeit dieser Ansichten spürbar
  erhöhen; für den aktuellen Datenumfang unkritisch.

## Alternativen erwogen

- **Nur UI-Komfort ohne neues Preisfeld** (Name/MwSt. vorbefüllen, Preis
  immer manuell): ursprünglich erwogene kleinere Variante — verworfen,
  da sie den Kernnutzen "Preisabgleich" kaum eingelöst hätte (die
  Zahl, die am meisten Tipparbeit/Fehlerpotenzial spart, wäre weiterhin
  nie vorbefüllt gewesen).
- **Einkaufspreis (günstigster Lieferant) als Verkaufspreis-Vorschlag
  verwenden**, statt eines neuen Felds: verworfen — ein Einkaufspreis
  ohne Aufschlag als Verkaufspreis vorzubefüllen wäre fachlich
  irreführend (Null-Marge-Risiko, falls der Nutzer den vorbefüllten
  Wert übernimmt, ohne ihn zu prüfen).
