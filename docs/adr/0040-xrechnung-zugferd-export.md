# ADR-0040: XRechnung/ZUGFeRD-Export für ausgestellte Rechnungen

**Status:** accepted
**Datum:** 2026-10-02

## Kontext

ADR-0038 grenzte XRechnung/ZUGFeRD bewusst aus: "das wäre die
Implementierung eines eigenen, komplexen E-Rechnungs-Datenformats
(EN 16931), eine Größenordnung über einer Feld-Vollständigkeitsprüfung
und eine eigene Entscheidung wert." Für ein **marktfähiges** ERP ist
dieser Export kein Nice-to-have mehr, sondern eine regulatorische
Notwendigkeit: XRechnung ist seit 2020 für B2G-Rechnungen in
Deutschland Pflicht, und B2B-E-Rechnungen werden ab 2025 (Empfang) bzw.
2027/2028 (Versand, je nach Unternehmensgröße) gesetzlich
verpflichtend.

## Entscheidung

### Bibliothek: `horstoeko/zugferd`

MIT-lizenziert, deckt CII-Erzeugung über die volle Profil-Leiter
(EN16931, XRechnung 1.x–3.x, ZUGFeRD-Hybrid) mit einem fluent Builder
ab, bringt eine reine PHP-XSD-Validierung ohne externe Laufzeit mit und
hat eine eigene Klasse für den PDF/A-3-Merge. Andere Kandidaten
(`php-erechnung-toolkit`, `easybill/e-invoicing`, `dealerweb/einvoice`,
`horstoeko/invoicesuite`, `xrechnung-kit`) wurden gesichtet, boten aber
keine vergleichbar vollständige, aktiv gepflegte Kombination dieser
Fähigkeiten.

### Nur CII, kein UBL

`horstoeko/zugferd` unterstützt ausschließlich die CII-Syntax (Cross
Industry Invoice). Das deckt sowohl XRechnung (als CII-Variante) als
auch ZUGFeRD aus einem einzigen Mapping-Code-Pfad ab — UBL wäre ein
komplett zweiter Pfad ohne Mehrwert für dieses Projekt.

### `XRechnungService`: ein Dienst, zwei Ausgabeformen

- `generateXml(int $invoiceId): string` — reine EN16931/XRechnung-XML
  (Profil `PROFILE_XRECHNUNG_3`).
- `generateZugferdPdf(int $invoiceId, IUser $user): string` — nimmt die
  beim Ausstellen bereits erzeugte und gespeicherte Rechnungs-PDF
  (ADR-0013/0022, `Invoice::documentFileId`) und bettet die EN16931-XML
  via `ZugferdDocumentPdfMerger` als PDF/A-3-Anhang ein. Es wird
  **keine zweite PDF-Pipeline** neben Dompdf aufgebaut — die
  vorhandene PDF bleibt die einzige Quelle für das visuelle Layout.

Beide Methoden lehnen eine Rechnung ab (`\DomainException`), die noch
im Entwurf ist (keine `invoiceNumber`), kein verknüpfter Kundenkontakt
hat, deren Firmenprofil unvollständig ist
(`CompanyProfileService::missingMandatoryFields()`, ADR-0038 — hier
zum ersten Mal **aktiv als Vorbedingung** genutzt, nicht mehr nur rein
informativ) oder deren Kundenkontakt keine vollständige
Straße/PLZ/Ort-Adresse hinterlegt hat.

### Feld-Mapping-Entscheidungen (EN16931 Business Terms)

- **Rechnungstypcode**: einheitlich `380` (Commercial invoice) für
  jede `Invoice`, unabhängig vom internen `type`
  (`invoice`/`partial`/`final`) — EN16931 kennt keine eigenen Codes für
  deutsche Teil-/Schlussrechnungen.
- **USt.-Kategorie**: `S` (Standardsatz) bei `vatRatePercent > 0`,
  sonst `Z` (Nullsatz) — bewusst nicht `E` (steuerbefreit), da `Z` ohne
  Befreiungsgrundtext auskommt. Echte §19-UStG-Kleinunternehmer-
  Befreiungsgründe werden weiterhin nicht modelliert (wie in ADR-0038
  für das PDF bereits dokumentiert).
- **USt.-Typcode**: immer `VAT`.
- **Zahlungsmittel**: SEPA-Überweisung (`addDocumentPaymentMeanToCreditTransfer`),
  nur wenn `CompanyProfile::iban` gepflegt ist; sonst entfällt der
  Block ganz.
- **Einheiten-Codes**: neuer `ZugferdUnitCodeResolver` bildet die im
  Projekt frei als Text erfassten Einheiten ("Stk", "Std", "psch." …)
  auf UN/ECE-Rec.-20-Codes ab, mit `C62` (Stück/Einheit) als
  universellem Fallback für alles Unbekannte.
- **Länder-Codes**: neuer `CountryCodeResolver` bildet
  "Deutschland"/"Germany"/"DE" (und ein paar Nachbarländer) auf
  zweistellige ISO-3166-1-Codes ab, mit `DE` als Fallback. Bewusst
  keine vollständige ISO-3166-Zuordnung — dieses ERP ist auf den
  deutschen Markt zugeschnitten.
- **Positionsrabatt** (ADR-0022): wird als `AllowanceCharge` auf
  Positionsebene abgebildet (`addDocumentPositionAllowanceCharge`),
  nicht in den Nettopreis eingerechnet.
- **Beleg-Rabatt** (ADR-0022): fließt direkt in
  `setDocumentSummation()`s `allowanceTotalAmount`
  (`calculation.documentDiscountAmount`) ein. **Bewusst keine
  eigenen `AllowanceCharge`-Elemente auf Belegebene je MwSt.-Satz** —
  das wäre für vollständige KoSIT-Schematron-Konformität nötig, ist
  aber für die hier geprüfte XSD-Strukturvalidität nicht erforderlich
  und für den (seltenen) Fall eines Beleg-Rabatts eine bekannte Lücke
  (siehe "Nicht Teil dieser Phase").

### Strukturierte Kontaktadresse: `ContactsService::structuredAddressFor()`

Additive neue Methode neben dem bestehenden `detailsFor()`
(ADR-0022) — liefert Straße/PLZ/Ort/Land einzeln statt als fertige
Anzeigezeilen, wie es das EN16931-Käufer-Datenmodell verlangt. Bewusst
**ohne** den `ContactHistorySnapshotService`-Fallback von
`detailsFor()`: ein gelöschter Kontakt liefert leere Felder, die die
Vorbedingungsprüfung dann klar ablehnt, statt eine unvollständige
E-Rechnung aus einer historischen Anzeigezeile zu raten.

### Nextclouds XXE-Hardening und die XSD-Validierung

Nextcloud deaktiviert global jedes Nachladen externer XML-Entities
(`lib/base.php`, `libxml_set_external_entity_loader`) — das blockiert
auch die legitimen `xsd:import`/`xsd:include`-Verweise *innerhalb* der
von `horstoeko/zugferd` mitgelieferten EN16931-XSD-Dateien, die keinen
Bezug zu nutzergesteuertem Input haben. `XRechnungService::generateXml()`
schaltet für die Dauer der einen Validierung auf PHPs Standardverhalten
zurück und stellt Nextclouds Sperre danach exakt wieder her. Kein
Sicherheitsrisiko: validiert wird ausschließlich selbst erzeugte XML
gegen mitgelieferte, nicht nutzergesteuerte Schema-Dateien.

### Download-Endpunkte

`EInvoiceController` (Nicht-OCS, wie `ReportExportController`/
`DocumentsController`) liefert beide Formen als Rohdatei-Download,
Rechte-Gate auf `ResourceType::Rechnungen` (mind. `Read`):

- `GET /apps/erp/export/invoices/{id}/xrechnung.xml`
- `GET /apps/erp/export/invoices/{id}/zugferd.pdf`

Frontend: zwei neue Links in `RechnungDetailView.vue`, sichtbar sobald
die Rechnung nicht mehr im Entwurfsstatus ist.

## Konsequenzen

- Rechnungen können jetzt als EN16931/XRechnung-XML und als
  ZUGFeRD-Hybrid-PDF exportiert werden — Voraussetzung für B2G- und
  die kommende B2B-E-Rechnungspflicht.
- `CompanyProfileService::missingMandatoryFields()` (ADR-0038) hat
  jetzt eine zweite, blockierende Verwendung zusätzlich zur rein
  informativen Anzeige im Firmenprofil-Formular.
- Der reale Dev-DB-Testdatensatz des Firmenprofils fehlt `country`,
  `vat_id`, `tax_number`, `iban`, `bic` — ohne diese Felder lehnt der
  neue Export mit einer klaren Fehlermeldung ab. Das ist beabsichtigtes
  Verhalten, kein Bug; vor produktivem Einsatz muss das echte
  Firmenprofil vollständig gepflegt sein.

## Nicht Teil dieser Phase

- **Volle KoSIT-Schematron-Geschäftsregelprüfung**
  (`ZugferdKositValidator` benötigt eine Java-Laufzeit) — hier wird nur
  die strukturelle XSD-Validierung (`ZugferdXsdValidator`) geprüft und
  bei Fehlern hart abgelehnt. Vor produktivem E-Rechnungsversand
  zwingend zusätzlich gegen die offizielle KoSIT-Validator-Suite
  prüfen.
- **Beleg-Rabatt als eigene `AllowanceCharge`-Elemente je
  MwSt.-Satz** auf Belegebene (nur in den Summenfeldern abgebildet,
  siehe oben).
- **Unabhängige PDF/A-3-Zertifizierung** — `ZugferdDocumentPdfMerger`
  übernimmt die Konformitätsstufe strukturell, eine externe
  PDF/A-Validierung (z. B. veraPDF) wurde nicht durchgeführt.
- **CreditNote-Export** — nur `Invoice`-Entitäten sind abgedeckt.
- **Echte §19-UStG-Kleinunternehmer-Befreiungsgründe** (wie bereits in
  ADR-0038 für das PDF dokumentiert).

## Alternativen erwogen

- **UBL-Syntax zusätzlich zur CII-Syntax**: verworfen — kein
  Marktbedarf für dieses Projekt, doppelter Mapping-Aufwand ohne
  Mehrwert.
- **Eigene, minimale XML-Erzeugung ohne Bibliothek**: verworfen — das
  EN16931-Datenmodell ist zu umfangreich und fehleranfällig, um es ohne
  getestete Bibliothek und XSD-Validierung selbst zu pflegen.
