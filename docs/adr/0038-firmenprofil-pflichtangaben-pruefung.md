# ADR-0038: Informative Vollständigkeitsprüfung der § 14 UStG-Pflichtangaben

**Status:** accepted
**Datum:** 2026-10-02

## Kontext

`status.md` führte seit Phase 12/13 (ADR-0013/0021/0022) als bekannte
Einschränkung: "kein XRechnung/ZUGFeRD, keine vollständige § 14
UStG-Pflichtangaben-*Prüfung* (die Felder lassen sich zwar im
Firmenprofil pflegen, es gibt aber keine automatische
Vollständigkeitskontrolle) — Vor produktivem Rechnungsversand an
Kunden zwingend gegenzuprüfen."

Diese ADR behandelt **ausschließlich die Vollständigkeitsprüfung**, da
sie klar abgegrenzt und ohne neue Datenmodell-Entscheidungen umsetzbar
ist. **XRechnung/ZUGFeRD bleibt bewusst außen vor** — das wäre die
Implementierung eines eigenen, komplexen E-Rechnungs-Datenformats
(EN 16931), eine Größenordnung über einer Feld-Vollständigkeitsprüfung
und eine eigene Entscheidung wert.

## Entscheidung

### Rein informativ, kein Blocker

`CompanyProfileService::missingMandatoryFields()` prüft die bereits
strukturiert erfassten Absenderfelder gegen die in § 14 Abs. 4 UStG
genannten Pflichtangaben, die sich auf den Rechnungsaussteller
beziehen:

- Name/Firma
- Anschrift
- PLZ/Ort
- Steuernummer **oder** USt-IdNr. (eine der beiden reicht)

**Bewusst kein Hard-Block beim Ausstellen** (`InvoiceService::issue()`
bleibt unverändert) — ein automatischer Block hätte das Risiko, Kays
eigene bereits laufende Rechnungsstellung zu unterbrechen, falls sein
aktuelles Firmenprofil nicht exakt dieser Definition entspricht, ohne
dass eine Person das vorher bewusst entschieden hat. Stattdessen
dasselbe "warnen, nicht verhindern"-Prinzip, das dieses Projekt bereits
für andere legal-nahe Lücken anwendet (DATEV-Export-Hinweis, ADR-0026;
Schlussrechnungs-Gutschriften-Verrechnung, ADR-0027/0030).

**Nicht geprüft** (fehlen als strukturierte Felder komplett, nicht nur
ungefüllt):

- Zeitpunkt der Lieferung/Leistung je Rechnung — es gibt aktuell kein
  "Leistungsdatum"-Feld auf `Invoice` getrennt vom Ausstellungsdatum.
  Ein neues Feld einzuführen wäre eine Datenmodell-Erweiterung, keine
  Vollständigkeitsprüfung bestehender Felder — außerhalb des Scopes.
- Angaben zu einer Steuerbefreiung (§ 19 UStG Kleinunternehmer o. Ä.) —
  nicht modelliert, dieses Projekt geht von regulärer Umsatzsteuer-
  pflicht aus.

### Sichtbarkeit: zwei Stellen

- `GET`/`PUT /api/v1/company-profile` liefern zusätzlich
  `missingMandatoryFields` — Warnbanner direkt im Firmenprofil-
  Formular (`EinstellungenView`).
- `ReportingService::dashboardSummary()` liefert zusätzlich
  `companyProfileMissingFields` (neue Abhängigkeit
  `CompanyProfileService`) — Warn-Kachel im Dashboard, analog zum
  bestehenden Muster für fällige TÜV-Termine (ADR-0028) und überfällige
  Rechnungen.

## Nicht Teil dieser Phase

- **XRechnung/ZUGFeRD** (siehe Kontext) — eigenes, deutlich größeres
  Feature.
- **Kein Leistungsdatum-Feld** je Rechnung (siehe oben).
- **Keine Steuerbefreiungs-Angaben** (§ 19 UStG).
- **Kein Hard-Block** beim Ausstellen (siehe "Entscheidung").
- **Keine Prüfung der Kundenanschrift** — die kommt aus dem verknüpften
  Nextcloud-Kontakt, nicht aus dem Firmenprofil; eine fehlende/
  unvollständige Kundenanschrift wird hier nicht erkannt.

## Konsequenzen

- `CompanyProfileController::index()`/`update()` geben jetzt ein Array
  statt der rohen `CompanyProfile`-Entity zurück (zusätzliches Feld
  `missingMandatoryFields`) — Response-Shape-Erweiterung, keine
  entfernten Felder, einziger Konsument (`EinstellungenView`) angepasst.
- `ReportingService` bekommt eine neue Konstruktor-Abhängigkeit
  (`CompanyProfileService`) — der einzige manuelle Testaufrufer
  (`ReportingServiceTest`) musste angepasst werden.
- Neue Testdatei `CompanyProfileServiceTest.php` — `CompanyProfileService`
  hatte bisher (wie `RateService`/`CustomerContractService`, siehe
  ADR-0037) keine eigenen Tests. Da `erp_company_profile` ein echter
  Singleton ist, sichert der Test die beim Testlauf vorhandene Zeile
  und stellt sie in `tearDown()` exakt wieder her, statt sie zu
  löschen.

## Alternativen erwogen

- **Hard-Block in `InvoiceService::issue()`:** verworfen, siehe
  "Entscheidung" — zu riskant ohne Rücksprache, welche Felder exakt als
  Pflicht gelten sollen, und widerspricht dem etablierten
  "warnen, nicht verhindern"-Muster dieses Projekts für vergleichbare
  Lücken.
- **Warnung nur im Dashboard, nicht im Firmenprofil-Formular selbst:**
  verworfen — die naheliegendste Stelle, die Lücke zu schließen, ist
  genau dort, wo die Felder gepflegt werden.
