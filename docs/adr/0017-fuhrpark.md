# ADR-0017: Fuhrpark

**Status:** accepted
**Datum:** 2026-08-21

## Kontext

Roadmap Phase 9 verlangt: Fahrzeugstamm, Fahrer/Zuweisungen,
TÜV-Erinnerungen, Werkstatttermine mit Calendar-Verknüpfung, Tankbelege
mit Foto, Kilometerstand, optional Fahrtenbuch, optional
Fahrzeuglager-Anzeige. Phase 8 (ADR-0014) hatte bereits einen
Warenlager-Typ `vehicle` vorgesehen, aber bewusst ohne Verknüpfung zu
einem echten Fahrzeug-Datensatz — die dortige Doku verweist ausdrücklich
auf diese Phase.

## Entscheidung

### Fahrzeugstamm

`erp_vehicles` (license_plate — Kennzeichen, unique; brand_model;
vehicle_type: `car`/`van`/`trailer`/`other`; status:
`active`/`inactive`/`sold`; assigned_user_id nullable — aktueller
Fahrer als Nextcloud-UID, keine Zuweisungs-Historie; current_mileage_km;
next_inspection_date nullable — TÜV-Fälligkeit; notes; created_at/
updated_at).

Fahrer-Zuweisung ist bewusst ein einzelnes Feld auf dem Fahrzeug (wie
`responsibleUserId` beim Projekt), keine eigene Zuweisungstabelle mit
Historie — für den aktuellen Umfang reicht "wer fährt es gerade",
Verlaufsdaten sind ein späteres Ausbaustück.

### TÜV-Erinnerung und Werkstatttermine: kein neues Termin-Datenmodell

`next_inspection_date` ist ein reines Datumsfeld auf dem Fahrzeug — die
Web-UI markiert es farblich, wenn es überschritten oder in den nächsten
30 Tagen fällig ist (rein clientseitige Berechnung, kein Hintergrundjob).
Für einen echten Kalendereintrag (TÜV-Termin oder Werkstatttermin) nutzt
die Web-UI die bereits bestehende generische Calendar-Verknüpfung aus
ADR-0009 (`erp_calendar_links`, `resourceType='fuhrpark'`,
`resourceId=<vehicleId>`) — identischer Mechanismus wie beim
Projekt-Tab "Termine", ein "Termin anlegen"-Button auf der
Fahrzeugdetailseite befüllt das Formular lediglich mit dem
TÜV-Datum vor. Kein neues Erinnerungssystem, keine
Push-/E-Mail-Benachrichtigung (siehe "Nicht Teil dieser Phase").

### Tankbelege

`erp_vehicle_fuel_logs` (vehicle_id, entry_date, liters, amount —
Bruttobetrag, keine Netto-/MwSt.-Aufschlüsselung nötig, das ist
Buchhaltungs-Sache von Phase 10; mileage_km — Kilometerstand beim
Tanken; receipt_file_id nullable — hochgeladenes Beleg-Foto;
notes; created_at). Jeder neue Tankbeleg mit einem `mileage_km`, der über
dem bisherigen `current_mileage_km` des Fahrzeugs liegt, aktualisiert
diesen automatisch (informativ, kein Zwang — ein niedrigerer Wert wird
nicht abgelehnt, nur nicht übernommen, falls versehentlich ein alter
Beleg nachgetragen wird).

**Foto-Upload:** Da bisher alle Datei-Schreibzugriffe im Projekt
serverseitig generierte Dokumente waren (Rechnungs-HTML), nicht
Nutzer-Uploads, kommt hier der erste echte Datei-Upload dazu. Die Web-UI
liest die gewählte Bilddatei clientseitig über `FileReader` als Base64
und schickt sie im JSON-Body mit (`VehicleService::uploadFuelReceipt()`
dekodiert und schreibt sie über `IRootFolder`/`Folder::newFile()` nach
`ERP/Fuhrpark/<Kennzeichen>/Tankbelege/`, analog zu
`ErpFolderService::ensureInvoiceFolder()`) — kein Multipart-Handling,
bleibt konsistent mit dem übrigen JSON-only-API-Stil dieses Projekts.

### Fahrzeuglager-Anzeige: bestehende Lager-Infrastruktur verknüpfen

`erp_warehouses` bekommt eine nullable `vehicle_id`-Spalte. Ein
Fahrzeuglager (`type='vehicle'`, ADR-0014) kann damit auf einen echten
Fahrzeug-Datensatz zeigen; die Fahrzeugdetailseite zeigt den
verknüpften Lagerbestand über den bereits bestehenden Endpunkt
`GET /stock?warehouseId=` an (ADR-0014) — keine neue Bestands-Logik
nötig.

### Rechte

Neuer Controller `VehicleController`, gated über das bereits in
ADR-0008 vorgesehene `ResourceType::Fuhrpark` (bisher nur im Enum
reserviert, jetzt erstmals tatsächlich verwendet).

## Nicht Teil dieser Phase

- **Fahrtenbuch** (Trip-Log mit Start/Ziel/Zweck je Fahrt) — laut
  Roadmap ausdrücklich optional, zurückgestellt.
- **Automatische TÜV-Erinnerungs-Benachrichtigung** (Push/E-Mail/Cron)
  — nur eine visuelle Fälligkeits-Markierung im Web-UI, kein
  Hintergrundjob.
- **Zuweisungs-Historie** — nur der aktuell zugewiesene Fahrer wird
  gespeichert, keine Protokollierung früherer Zuweisungen.
- **Kraftstoffverbrauchsstatistik/-diagramme** — Tankbelege werden
  erfasst, aber nicht zu einem Verbrauchswert (l/100km) verrechnet;
  das gehört eher zu Phase 11 (Auswertungen).
- **Echte Foto-Vorschau/-Bearbeitung** — das Bild wird abgelegt und ist
  über die Nextcloud-Files-App normal einsehbar, keine eigene
  Bildvorschau-Komponente im ERP-UI.

## Konsequenzen

- Fahrzeuglager aus Phase 8 sind jetzt tatsächlich mit einem
  Fahrzeug-Datensatz verknüpfbar, löst die dort dokumentierte
  Einschränkung auf.
- TÜV/Werkstatttermine laufen über dieselbe Calendar-Verknüpfung wie
  alle anderen Termine im Projekt — kein Parallelsystem.
- Der erste echte Datei-Upload im Projekt etabliert ein Muster
  (Base64-JSON) für künftige Foto-/Dokument-Uploads, ohne die
  API-Konventionen zu brechen.

## Alternativen erwogen

- **Multipart-Formular-Upload** für das Tankbeleg-Foto: näher an
  klassischen Datei-Uploads, hätte aber eine Sonderbehandlung im sonst
  durchgehend JSON-basierten API-Layer erfordert — Base64-im-JSON-Body
  ist für die erwartete Bildgröße (Handyfoto eines Kassenbons)
  ausreichend performant und hält die API einheitlich.
- **Eigene Zuweisungstabelle mit Start-/Enddatum** für Fahrer: hätte
  eine echte Historie ermöglicht, aber für den aktuellen Umfang
  ("wer fährt es gerade") unnötige Komplexität — wie bei
  `responsibleUserId` beim Projekt reicht ein einzelnes Feld.
