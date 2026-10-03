# ADR-0042: Ansprechpartner-Standards je Belegtyp (Kunde + Projekt-Override)

**Status:** accepted
**Datum:** 2026-10-03

## Kontext

ADR-0041 brachte mehrere Ansprechpartner pro Firmenkunde, grenzte aber
bewusst aus: "keine Auswahl eines bestimmten Ansprechpartners je
Angebot/Auftrag/Rechnung". Kundenwunsch danach: "Angebote, Auftrags-
bestätigungen und Lieferscheine gehen meist an den Projektleiter.
Rechnungen gehen meistens an die Buchhaltung." — mit der Fähigkeit,
das pro Kunde als Standard zu hinterlegen **und** pro Projekt
abzuweichen, wenn ein konkretes Projekt anders läuft.

## Entscheidung

### Zwei Ebenen, eine Auflösungsreihenfolge

- **Kundenstandard** (`erp_contact_person_defaults`): ein Ansprechpartner
  je Belegtyp und Firmenkontakt (`contact_link_id`), z. B. "Rechnungen
  → Katharina Schmidt".
- **Projekt-Override** (`erp_project_contact_overrides`): ein
  Ansprechpartner je Belegtyp und Projekt — überschreibt den
  Kundenstandard nur für dieses eine Projekt.
- **Auflösung** (`DocumentContactPersonResolver`, reine Lesesicht):
  zuerst Projekt-Override, sonst Kundenstandard, sonst keiner (keine
  "z. Hd."-Zeile — unverändertes Verhalten wie vor dieser ADR).

Bewusst **kein expliziter "niemand"-Zustand auf Projektebene** — fehlt
die Override-Zeile, gilt der Kundenstandard. Ein Projekt kann also
"wie beim Kunden" oder "explizit Person X", aber nicht "explizit
niemand trotz Kundenstandard" sein. Das deckt den beschriebenen
Anwendungsfall vollständig ab und vermeidet eine dritte Zustandsart
(gesetzt/nicht gesetzt/explizit leer) ohne erkennbaren Bedarf.

### Belegtypen: `OCA\ERP\Documents\DocumentType`

Neues Enum mit denselben String-Werten wie
`DocumentLayoutService::DOCUMENT_TYPES` (ohne `purchase_order` — das
ist ein interner Beschaffungsbeleg an den Lieferanten, kein
Kunden-Beleg): `quote`, `order`, `delivery_note`, `invoice`,
`credit_note`. `credit_note` ist dabei, obwohl der Kundenwunsch nur
Angebot/Auftrag/Lieferschein/Rechnung nannte — Symmetrie zu
Rechnungen kostet nichts und schließt eine sonst inkonsistente Lücke.

### Validierung: der Ansprechpartner muss zur richtigen Firma gehören

- `ContactPersonDefaultService::set()` prüft, dass der gewählte
  Ansprechpartner tatsächlich zum angegebenen `contact_link_id` gehört.
- `ProjectContactOverrideService::set()` löst den `customerContactUid`
  des Projekts zum zugehörigen `contact_link_id` auf und prüft
  dasselbe — ein Ansprechpartner einer fremden Firma lässt sich nicht
  als Override für ein Projekt eintragen, dessen Kunde eine andere
  Firma ist.

### Rendering: "z. Hd."-Zeile direkt in der Kundenanschrift

`DocumentContactPersonResolver::resolveName()` liefert nur den
Anzeigenamen (keine Position/Kontaktdaten — das wäre für eine
Briefanschrift unüblich). `DocumentHtmlBuilder::customer()` setzt ihn
als "z. Hd. {Name}"-Zeile direkt zwischen Firmenname und Straße in die
Adresszeilen — **nicht** als eigenes Token. Das hat einen konkreten
Vorteil: die Zeile durchläuft denselben Snapshot-Mechanismus
(`DocumentSnapshotService`) wie der Rest der Adresse, ohne das
Snapshot-Tokenformat ändern zu müssen. Beim Ausstellen
(`QuoteService`/`OrderService`/`DeliveryNoteService`/
`InvoiceService`/`CreditNoteService`, jeweils im `snapshot()`-Aufruf)
wird der aufgelöste Name eingefroren — ändert sich der Standard oder
Override danach, bleiben bereits ausgestellte Belege unverändert
(dasselbe Prinzip wie Firmenprofil/Layout-Snapshots, ADR-0021).

### Optionaler, nullable Resolver in den fünf Beleg-Services

`DocumentContactPersonResolver` wird als `?DocumentContactPersonResolver
$contactPersonResolver = null` injiziert — derselbe "optionaler
Dienst am Konstruktorende"-Stil wie z. B.
`ContactsService::$addressBookProvisioner`. Ohne ihn (z. B. in den
sieben bereits bestehenden Service-Tests, die diese Konstruktoren
direkt mit `new` aufrufen) verhält sich die Beleg-Erzeugung exakt wie
vor dieser ADR — keine der bestehenden Tests musste angepasst werden.

### API

Unter den bestehenden Controllern (kein neuer Controller nötig):

- `GET`/`PUT /api/v1/contacts/links/{contactLinkId}/person-defaults`
  (bzw. `.../person-defaults/{documentType}` für `PUT`) —
  `ContactsController`, Rechte-Gate wie bei Ansprechpartnern (ADR-0041).
- `GET`/`PUT /api/v1/projects/{id}/contact-overrides` (bzw.
  `.../contact-overrides/{documentType}` für `PUT`) —
  `ProjectController`, Rechte-Gate `ResourceType::Projekte`.

### Web-UI

- `ContactLinksView`: im bereits bestehenden Ansprechpartner-Panel
  (ADR-0041) ein neuer Abschnitt "Standard je Belegtyp" mit einem
  Dropdown pro Belegtyp, sobald mindestens ein Ansprechpartner erfasst
  ist.
- `ProjektDetailView`: im Tab "Übersicht" ein neuer Abschnitt
  "Ansprechpartner je Belegtyp", sichtbar sobald der Projekt-Kunde
  Ansprechpartner hat. Die Personenliste kommt dynamisch über den
  Projekt-Kunden (`customerContactUid` → passender `erp_contact_links`-
  Eintrag → dessen Ansprechpartner), nicht über eine eigene
  Projekt-Ressource.

## Konsequenzen

- Angebote, Auftragsbestätigungen, Lieferscheine, Rechnungen und
  Gutschriften zeigen jetzt automatisch den richtigen Ansprechpartner
  als "z. Hd."-Zeile — standardmäßig nach Kundenvorgabe, projektweise
  übersteuerbar.
- `ContactPersonDefaultService`/`ProjectContactOverrideService` sind
  bewusst getrennte, kleine Dienste (Schreiben), `DocumentContact
  PersonResolver` ist die einzige Lesesicht, die die Beleg-Services
  tatsächlich brauchen.

## Nicht Teil dieser Phase

- Kein expliziter "niemand trotz Kundenstandard"-Zustand auf
  Projektebene (siehe oben).
- Keine Massenpflege ("alle Projekte dieses Kunden auf Person X
  umstellen") — jede Projekt-Override-Zeile wird einzeln gesetzt.
- Löscht man einen Ansprechpartner (`ContactPersonService::delete()`),
  bleiben bestehende Standard-/Override-Zeilen, die auf ihn verweisen,
  unangetastet in der DB stehen (kein Cascade). Der Resolver degradiert
  dabei sauber auf "kein Ansprechpartner" (`ContactPersonMapper::
  findById()` liefert `null`), die betroffene Zeile zeigt im
  Dropdown nur keine Auswahl mehr an — funktional unauffällig, aber
  eine Datenleiche. Vor einem produktiven Einsatz mit häufigem
  Ansprechpartner-Löschen ggf. nachrüsten.
- Keine automatische Migration bestehender, bereits ausgestellter
  Belege — die "z. Hd."-Zeile erscheint nur auf Belegen, die nach
  dieser ADR (neu) ausgestellt werden.
