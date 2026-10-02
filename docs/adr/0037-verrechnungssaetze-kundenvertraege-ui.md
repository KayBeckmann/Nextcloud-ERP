# ADR-0037: Web-UI für Standard-Verrechnungssätze und Kundenverträge

**Status:** accepted
**Datum:** 2026-10-02

## Kontext

`status.md` führte seit ADR-0012 als bekannte Einschränkung: "Kein
Web-UI für Verrechnungssätze/Kundenverträge (Phase 6) — aktuell nur
über die API v1 bedienbar."

**Befund beim Umsetzen:** Anders als der Bullet-Text ("Phase 6")
vermuten lässt, existiert das Backend für **beide** Bereiche bereits
vollständig seit ADR-0012 — `RateService`/`RateController`
(`erp_standard_rates`) und `CustomerContractService`/
`CustomerContractController` (`erp_customer_contracts`,
`erp_customer_contract_rates`), inklusive der 6-stufigen
Satz-Auflösungspriorität (`RateResolutionService`, bereits mit eigenen
Tests). Es fehlte ausschließlich das Web-UI — keine neue
Backend-Entwicklung nötig, reines Frontend-Feature.

`BerechtigungenView.vue` (Rechte-Matrix) hatte sogar einen veralteten
Hinweistext ("Verrechnungssätze folgen erst in Phase 6"), der seit dem
Bau des Backends nie aktualisiert wurde.

## Entscheidung

`BerechtigungenView` bekommt zwei neue Tabs (Tab-Navigation analog zu
`StundenZeitkontoView`), neben dem bestehenden "Rechte-Matrix"-Tab:

### "Standard-Verrechnungssätze"

Tabelle aller `StandardRate`-Einträge (Arbeitsart aufgelöst über
`fetchWorkTypes()`, "Gilt für" aus `principalType`/`principalId`
aufgelöst gegen die bereits vorhandene `fetchPrincipals()`-Liste aus
der Rechte-Matrix — keine neue Datenquelle). Ein Formular legt einen
Satz an oder bearbeitet ihn — "bearbeiten" heißt hier technisch
`POST .../rates/standard` mit derselben `(workTypeId, principalType,
principalId)`-Kombination erneut senden, was der Service bereits als
Upsert behandelt (`setStandardRate()`, kein eigener `PUT`-Endpunkt
nötig).

**"Gilt für"-Auswahl wiederverwendet die Principals-Liste** statt
eines separaten User-/Gruppen-Pickers — es gibt keinen GroupPicker in
diesem Projekt, und die bereits geladene Liste aus der Rechte-Matrix
enthält exakt dieselben User+Gruppen, die hier relevant sind.

### "Kundenverträge"

`ContactPicker` wählt den Kunden, dessen Verträge geladen werden
(`GET /contracts?customerContactUid=`). Verträge sind aufklappbar
(Muster aus `ArtikelView`/`ProdukteView`/Gutschrift-Entwürfen,
ADR-0034) und zeigen ihre vertraglichen Sätze mit Hinzufügen/Entfernen.
Ein Formular legt einen neuen Vertrag an (Titel, Gültig von/bis als
Datumsfelder, Notiz).

## Nicht Teil dieser Phase

- **Kein Bearbeiten/Löschen eines ganzen Vertrags** — die API bietet
  dafür ohnehin keinen Endpunkt (nur `create`/`addRate`/`removeRate`),
  dieselbe Grenze wie bei Artikeln/Produkten (ADR-0032): UI deckt genau
  das ab, was die API kann.
- **Keine Testabdeckung für `RateService`/`CustomerContractService`
  nachgerüstet** — bei der Recherche aufgefallen, dass beide Services
  (anders als die reine `RateResolutionService`-Logik) keine eigenen
  PHPUnit-Tests haben. Das ist ein vorbestehender, von dieser ADR nicht
  verursachter Testlücken-Befund, nicht Teil dieses rein
  Frontend-Features — eigene Entscheidung wert, falls verfolgt.
- **Keine Live-Vorschau der Satz-Auflösung** (`GET /rates/resolve`) in
  dieser UI — der Endpunkt existiert und wird von der
  Zeiterfassungs-UI bereits beim Anlegen genutzt, aber eine explizite
  "was würde für User X bei Arbeitsart Y gelten"-Vorschau in der
  Verwaltungs-UI selbst wäre ein zusätzliches Komfort-Feature, nicht
  Teil der gemeldeten Lücke ("kein UI zum Pflegen", nicht "kein UI zum
  Testen").

## Konsequenzen

- Neue Datei `src/services/ratesApi.js` — reine Wrapper, kein neuer
  Backend-Code.
- `BerechtigungenView.vue` wächst von einer Rechte-Matrix-Seite zu
  einer Drei-Tab-Seite; der veraltete "Phase 6"-Hinweistext wurde
  entfernt.
- `status.md`-Bullet wird künftig präziser formuliert (betraf nie
  fehlende Backend-Funktionalität, nur das UI).

## Alternativen erwogen

- **Eigener `PUT`-Endpunkt für Standard-Sätze** statt den bestehenden
  Upsert-`POST` wiederzuverwenden: verworfen — der Service behandelt
  die Kombination `(workTypeId, principalType, principalId)` bereits
  korrekt als eindeutigen Schlüssel; ein zusätzlicher `PUT`-Endpunkt
  hätte keinen fachlichen Mehrwert geboten, nur zusätzliche API-Fläche.
