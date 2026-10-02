# ADR-0035: Angebotspositionen nur im Entwurf änderbar

**Status:** accepted
**Datum:** 2026-10-02

## Kontext

Seit ADR-0022 dokumentierte `status.md`: "Editierbare Positionen
(Menge/Preis/Rabatt) sind bei Angebot/Auftrag serverseitig nicht auf
den Entwurfsstatus beschränkt (anders als bei Rechnung/Lieferschein) —
dieselbe bereits vorher bestehende Inkonsistenz wie beim Löschen
einzelner Positionen, durch ADR-0022 nicht neu eingeführt, aber auch
nicht behoben."

**Geprüft, wer davon tatsächlich betroffen ist:** `Invoice` und
`DeliveryNote` schränken Positions-Mutationen bereits auf
`status = draft` ein. `Quote` (Angebot) tat das nicht. `Order`
(Auftrag) ebenfalls nicht — aber dort ist das **keine offene
Inkonsistenz, sondern eine bereits getroffene Entscheidung**:
ADR-0016 hält explizit fest: "Auftragspositionen ohne Entwurfs-Zwang —
anders als bei Rechnungen gibt es keinen 'Auftrag ausstellen'-Schritt,
der Positionen einfriert. Das ist eine bewusste Vereinfachung." Einen
Entwurfs-Zwang für Aufträge einzuführen würde diese bereits abgewogene
Entscheidung revidieren, nicht nur eine Lücke schließen — das ist
außerhalb des Scopes dieser ADR.

Diese ADR behandelt deshalb **ausschließlich Angebote**.

## Entscheidung

`QuoteService::addPosition()`/`updatePosition()`/`removePosition()`
bekommen eine neue private `requireDraft(Quote $quote)`-Guard —
strukturell identisch zu `InvoiceService::requireDraft()` (ADR-0013),
aber mit anderer Begründung: Angebote sind **keine GoBD-relevanten
Belege** (keine Pflicht-Sequenznummer, keine steuerliche
Unveränderlichkeit, ADR-0013 gilt nur für Rechnungen/Gutschriften).
Der Grund ist rein geschäftlich: ein bereits versendetes (`status !=
'draft'`) Angebot zeigt dem Kunden einen bestimmten Preis — ihn
nachträglich zu ändern, ohne dass der Kunde das mitbekommt, widerspräche
dem Zweck des Versendens. `\DomainException` bei Verstoß, vom
Controller auf `412` gemappt — dasselbe Muster wie bei allen anderen
Geschäftsregel-Ablehnungen in diesem Projekt.

**Gruppen (`QuoteGroup`) bleiben bewusst unberührt** — die gemeldete
Lücke bezog sich explizit auf Positionen (Menge/Preis/Rabatt), nicht
auf die strukturelle Gruppierung. Keine Ausweitung des Scopes.

### Web-UI

`AngebotDetailView` blendet das "+ Position hinzufügen"-Formular sowie
die Bearbeiten-/Löschen-Buttons pro Position aus, sobald
`quote.status !== 'draft'` — mit Hinweistext, warum. Verhindert den
unnötigen, ohnehin abgelehnten Request, statt den User erst auf eine
Fehlermeldung laufen zu lassen.

## Nicht Teil dieser Phase

- **Keine Änderung an `Order`/`erp_orders`** — bleibt bei der in
  ADR-0016 getroffenen, bewussten Entscheidung gegen einen
  Entwurfs-Zwang. `status.md` wird entsprechend präzisiert (betrifft
  nur noch Angebote, nicht mehr "Angebot/Auftrag").
- **Keine Sperre für `QuoteGroup`-Mutationen** (siehe oben).
- **Keine nachträgliche Benachrichtigung des Kunden**, falls doch ein
  neues Angebot anstelle einer Korrektur nötig wird — der Nutzer muss
  dafür ein neues Angebot anlegen oder (falls noch sinnvoll) den Status
  zurück auf `draft` setzen (bereits über `PUT /quotes/{id}` möglich,
  unverändert).

## Konsequenzen

- `QuoteService`/`QuoteController` bekommen eine zusätzliche
  Fehlerquelle (`\DomainException`/`412`) an drei bereits bestehenden
  Methoden — rein restriktiv, keine Signaturänderung.
- Bestehende Aufrufer (Tests, Web-UI), die Positionen nur im Entwurf
  mutieren, sind unverändert funktionsfähig; nur Aufrufe an
  nicht-draft-Angeboten verhalten sich jetzt anders (schlagen fehl statt
  stillschweigend zu mutieren).

## Alternativen erwogen

- **Auch `Order` auf Entwurfs-Zwang umstellen, um "Angebot/Auftrag"
  vollständig zu lösen:** verworfen — würde ADR-0016s explizite,
  bereits abgewogene Entscheidung revidieren (siehe Kontext), keine
  bloße Lückenschließung. Bei Bedarf eigene ADR mit eigener Abwägung.
