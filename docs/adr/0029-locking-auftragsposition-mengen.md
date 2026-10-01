# ADR-0029: Zeilensperre gegen doppeltes Verplanen von Auftragspositions-Mengen

**Status:** accepted
**Datum:** 2026-10-01

## Kontext

ADR-0016 hatte die "bereits verplante Menge" (invoiced/delivered) explizit
als **informativ** eingeführt: `DeliveryNoteService::createFromOrder()`
prüft zwar pro Aufruf, ob die angeforderte Menge die noch nicht
gelieferte Restmenge einer Auftragsposition überschreitet
(`sumQuantityForOrderPosition()` gegen `OrderPosition::quantity`), aber
diese Prüfung ist nicht gegen gleichzeitige Bearbeitung abgesichert:

1. Transaktion A liest die Summe der bisher gelieferten Mengen (z. B. 0 von
   5 Stk.).
2. Transaktion B liest dieselbe Summe, bevor A committet (ebenfalls 0 von
   5 Stk.).
3. Beide validieren unabhängig "3 Stk. anfordern ist ok (0+3 ≤ 5)" und
   fügen jeweils eine Lieferscheinposition mit 3 Stk. ein.
4. Ergebnis: 6 Stk. geliefert, obwohl die Auftragsposition nur 5 Stk.
   umfasst — ein klassisches TOCTOU-Problem (Time-of-check/
   time-of-use).

`status.md` dokumentierte das seitdem als bekannte Einschränkung: "Kein
Locking gegen doppeltes Verplanen von Auftragspositions-Mengen bei
gleichzeitiger Bearbeitung."

**Scope-Abgrenzung beim Entwurf:** `InvoiceService::createFromOrder()`
hat **bewusst keine** entsprechende Mengenprüfung — eine Schlussrechnung
(`type='final'`) darf laut ADR-0027 die **volle** Auftragsmenge erneut
auflisten, nicht nur die rechnerische Restmenge (siehe
`InvoiceServiceTest::testFinalInvoiceSettlement*`). Eine Sperre/Prüfung
für einen nicht existierenden Grenzwert wäre bedeutungslos. Diese ADR
betrifft daher ausschließlich den Lieferschein-Pfad
(`DeliveryNoteService::createFromOrder()`), der tatsächliche eine
Restmengen-Grenze durchsetzt.

## Entscheidung

**`SELECT ... FOR UPDATE` innerhalb einer expliziten Transaktion** auf die
betroffenen `erp_order_positions`-Zeilen, bevor die Restmenge berechnet
und die Lieferscheinpositionen eingefügt werden:

- `OrderPositionMapper::findOneForUpdate()` — wie `findOne()`, aber mit
  `IQueryBuilder::forUpdate()` (von Nextclouds QueryBuilder/Doctrine DBAL
  bereitgestellt, DB-Treiber-unabhängig).
- `DeliveryNoteService::createFromOrder()` sperrt alle referenzierten
  Auftragspositionen **in fester Reihenfolge (aufsteigende ID)**, bevor
  die Mengen-Validierung und das Einfügen der Lieferscheinpositionen
  läuft — alles innerhalb eines `beginTransaction()`/`commit()`-Blocks,
  mit `rollBack()` im Fehlerfall.

**Warum feste Sperr-Reihenfolge:** Zwei gleichzeitige Anfragen, die
mehrere gemeinsame Auftragspositionen in unterschiedlicher Reihenfolge
anfordern, könnten sich sonst gegenseitig blockieren (klassisches
Deadlock-Muster "A sperrt 1 dann 2, B sperrt 2 dann 1"). Aufsteigende
ID-Sortierung vor dem Sperren schließt das aus — dieselbe Lösung, die
auch außerhalb dieses Projekts für Mehrzeilen-Sperren üblich ist.

**Warum eine Zeilensperre statt eines DB-Constraints:** Ein
CHECK-Constraint kann "Summe über mehrere Zeilen einer Fremdtabelle ≤
Wert einer Zeile" nicht ausdrücken (keine aggregierenden Constraints in
SQL). Eine serialisierbare Transaktionsisolation (`SERIALIZABLE`) wäre
eine Alternative gewesen, hätte aber Retry-Logik für
Serialisierungsfehler im gesamten Request-Pfad erfordert — die gezielte
Sperre auf genau die betroffene(n) Zeile(n) ist die lokale, minimale
Lösung für genau dieses eine Problem.

## Nicht Teil dieser Phase

- **`InvoiceService::createFromOrder()`/`createFromDeliveryNote()`**
  bekommen keine Sperre — siehe "Scope-Abgrenzung" oben, dort gibt es
  keine durchzusetzende Obergrenze.
- **Keine echte Mehrprozess-Testabdeckung.** PHPUnit läuft in diesem
  Projekt mit einer einzelnen DB-Verbindung pro Testlauf
  (`ErpIntegrationTestCase`) — ein Test mit zwei tatsächlich
  gleichzeitigen Transaktionen auf unterschiedlichen Verbindungen ist
  damit nicht abbildbar. Die Regressionstests
  (`testCreateFromOrderAcrossMultipleCallsStillSumsCorrectly`,
  `testCreateFromOrderAcceptsExactRemainingQuantity`) sichern nur die
  fachliche Korrektheit der Summenbildung nach dem Umbau ab, nicht die
  Sperrwirkung selbst. Letztere beruht auf der Standardsemantik von
  `SELECT ... FOR UPDATE` innerhalb einer Transaktion (dieselbe Primitive,
  die `InvoiceService::nextSequence()` bereits für Sequenznummern nutzt,
  dort allerdings ohne `FOR UPDATE` — siehe "Alternativen erwogen").
- **Lieferschein-Entwurf und Gruppen-Kopie bleiben außerhalb der
  Transaktion** — nur die Mengenprüfung plus das Einfügen der
  Lieferscheinpositionen ist gesperrt/transaktional. Ein Fehler in der
  Positionsschleife lässt den zuvor angelegten Lieferschein-Entwurf (ggf.
  mit kopierten Gruppen) unverändert in der DB stehen — identisches
  Verhalten wie vor diesem ADR, keine neue Atomaritätsgarantie für den
  gesamten Aufruf.

## Konsequenzen

- `DeliveryNoteService` bekommt eine neue Konstruktor-Abhängigkeit
  `IDBConnection $db` — der einzige manuelle Aufrufer
  (`DeliveryNoteServiceTest`) musste angepasst werden, die
  Controller-Instanziierung läuft über Nextclouds Autowiring und war
  nicht betroffen.
- Zwei gleichzeitige `POST .../delivery-notes/from-order`-Aufrufe auf
  dieselbe Auftragsposition laufen jetzt serialisiert: der zweite Aufruf
  wartet, bis der erste committet oder zurückrollt, und sieht danach die
  korrekt aktualisierte Summe.

## Alternativen erwogen

- **`InvoiceService::nextSequence()`-Muster übernehmen** (reines
  `beginTransaction()`/`commit()` ohne `FOR UPDATE`): verworfen — dort
  funktioniert das Muster nur, weil ein Duplikat durch einen
  `UNIQUE`-Constraint auf `(year, kind)` zur Laufzeit ohnehin abgefangen
  würde; für eine Mengen-Summe über mehrere Zeilen gibt es keinen
  äquivalenten Constraint, der ohne explizite Zeilensperre vor einem
  Überbuchen schützen würde.
- **Anwendungsseitiger Retry bei Konflikt** (optimistisches Locking mit
  Versionsspalte auf `erp_order_positions`): verworfen — hätte
  Retry-Logik im Controller/Frontend erfordert (Nutzer müsste den Vorgang
  erneut auslösen); eine kurze Wartezeit durch Zeilensperre ist für den
  seltenen Gleichzeitigkeitsfall (zwei Personen liefern dieselbe
  Auftragsposition im selben Moment) die einfachere Lösung.
