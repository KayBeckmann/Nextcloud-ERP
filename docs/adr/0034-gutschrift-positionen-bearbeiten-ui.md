# ADR-0034: Gutschrift-Entwurf-Review mit editierbaren Positionen

**Status:** accepted
**Datum:** 2026-10-02

## Kontext

`status.md` führte seit ADR-0022 als bekannte Einschränkung: "Gutschrift-
Positionen sind zwar per API editierbar (`updatePosition`, ADR-0022),
aber ohne UI dafür — die Rechnungsansicht bietet für Gutschriften
bislang nur das Anlegen, kein Bearbeiten einzelner Positionen."

**Befund beim Umsetzen:** Die bestehende "Teilkorrektur"-Maske
(`RechnungDetailView`) legte den Entwurf an, fügte **eine** Position
hinzu und stellte die Gutschrift **sofort automatisch aus** — es gab
keinen Zwischenschritt, in dem ein Entwurf mit mehreren Positionen
sichtbar, bearbeitbar oder löschbar gewesen wäre. Eine Editier-UI
nachzurüsten war daher kein reines "Button hinzufügen", sondern
erforderte einen echten Entwurf-Review-Schritt. Mit Kay abgestimmt:
diesen Schritt einführen, auch wenn das den bisherigen
Sofort-Ausstellen-Automatismus der Teilkorrektur-Maske ändert.

Außerdem fehlte serverseitig ein `removePosition()` auf
`CreditNoteService`/`CreditNoteController` komplett (anders als bei
Angebot/Auftrag/Rechnung/Lieferschein, die alle ein `DELETE .../positions/
{id}` haben) — bereits in `docs/api/v1.md` als Lücke vermerkt.

## Entscheidung

### Backend: `removePosition()` nachgerüstet

`CreditNoteService::removePosition(int $creditNoteId, int $id): void` —
identisches Muster zu `addPosition()`/`updatePosition()`: nur solange
`status = draft` (`\DomainException` sonst), `\OutOfBoundsException`
wenn Gutschrift/Position nicht existiert. Neue Route
`DELETE /api/v1/credit-notes/{creditNoteId}/positions/{id}`.

### Frontend: Teilkorrektur wird zum Entwurf-Review

**Verhaltensänderung:** `submitPartialCreditNote()` legt jetzt **nur
noch den leeren Entwurf** an (`createPartialCreditNote()`), fügt keine
Position mehr hinzu und stellt nicht mehr automatisch aus. Die
Gutschriften-Tabelle zeigt Entwurfs-Zeilen (`status === 'draft'`) als
aufklappbar (analog zum Expand-Muster aus `ArtikelView`/`ProdukteView`):

- Aufklappen lädt die vollen Positionsdaten (`GET /credit-notes/{id}`,
  bereits vorhanden, bisher ungenutzt für diesen Zweck).
- Positionsliste mit Bearbeiten (`PUT`, bereits API-seitig vorhanden)
  und Löschen (`DELETE`, neu) pro Zeile — identisches Inline-Edit-Muster
  wie bei Rechnungspositionen selbst (`editingPositionId`/
  `editPosition`).
- Ein Formular zum Hinzufügen weiterer Positionen
  (`POST .../positions`).
- Ein expliziter "Gutschrift ausstellen"-Button (`POST .../issue`),
  deaktiviert ohne mindestens eine Position — der Server lehnt das
  ohnehin mit `412` ab (ADR-0013), die UI verhindert den unnötigen
  Request von vornherein.

**Vollstorno bleibt unverändert** (weiterhin Sofort-Ausstellen ohne
Review) — er kopiert ohnehin alle Rechnungspositionen 1:1, ein
Review/Editieren einzelner Positionen wäre dort kein sinnvoller
Zwischenschritt (und war nicht Teil der gemeldeten Lücke, die sich
explizit auf editierbare Positionen bezog).

## Nicht Teil dieser Phase

- **Kein Review-Schritt für Vollstorno-Gutschriften** — bleibt bewusst
  beim bisherigen Sofort-Ausstellen-Verhalten (siehe oben).
- **Keine Mengen-/Preis-Validierung** der Gutschriftposition gegen die
  Original-Rechnungsposition — eine Teilkorrektur kann weiterhin einen
  beliebigen Betrag/Menge enthalten, unabhängig von dem, was auf der
  Rechnung stand (unverändert zu ADR-0013/0022).
- **Kein Entwürfe-Aufräumen** — ein angelegter, aber nie mit Positionen
  gefüllter oder nie ausgestellter Gutschrift-Entwurf bleibt dauerhaft
  als Zeile mit Status "Entwurf" stehen, ohne Lösch-Möglichkeit für den
  Entwurf selbst (nur seine Positionen sind löschbar). Entspricht dem
  bestehenden Umgang mit Entwürfen im restlichen Projekt (z. B.
  Rechnungs-/Angebotsentwürfe haben ebenfalls keine "Entwurf löschen"-
  Funktion).

## Konsequenzen

- **Verhaltensänderung für bestehende Nutzung der Teilkorrektur-Maske:**
  wer bisher "Teilkorrektur ausstellen" in einem Schritt erwartet,
  braucht jetzt zwei Interaktionen (Entwurf anlegen → Positionen
  pflegen → explizit ausstellen). Bewusst in Kauf genommen, um
  mehrpositionige, korrigierbare Gutschriften überhaupt abbilden zu
  können.
- `CreditNoteController`/`CreditNoteService` bekommen eine neue
  Methode, rein additiv — keine bestehenden Signaturen geändert.

## Alternativen erwogen

- **One-Shot-Flow beibehalten, nur eine Bestätigungs-Vorschau vor dem
  Absenden ergänzen:** am Anfang erwogene kleinere Variante — verworfen,
  weil sie kein echtes Mehrpositionen-Bearbeiten ermöglicht hätte und
  damit die gemeldete Lücke ("Positionen bearbeiten") nur kosmetisch
  verkleinert, nicht geschlossen hätte.
