# ADR-0010: Projektkern-Datenmodell (Projekte, Aufgaben, Aufträge)

**Status:** accepted
**Datum:** 2026-08-19

## Kontext

Roadmap Phase 4 verlangt ein im Web vollständig anlegbares/verwaltbares
Projekt, verknüpft mit Kunde, Terminen und Projektordner — nutzbar für einen
Baustellenleiter am Laptop. Angebote/Rechnungen/Artikel existieren erst ab
Phase 5/7, Zeitwirtschaft erst ab Phase 6 — der "Auftrag" in dieser Phase ist
deshalb bewusst eine einfache Erfassung, keine aus einem Angebot abgeleitete
Struktur (die kommt erst mit echten Angebotspositionen in Phase 5).

## Entscheidung

### Projekt

`erp_projects`: `project_number`, `title`, `customer_contact_uid` (nullable —
Referenz auf einen Nextcloud-Contact wie in Phase 3, keine Kopie),
`responsible_user_id` (nullable Nextcloud-UID), `status`, `files_folder_id`
(nullable, Node-ID des Projektordners), `notes`, Timestamps.

**Projektnummer:** `sprintf('P-%05d', $id)`, nach dem Insert aus der
eigenen Auto-increment-ID gebildet und zurückgeschrieben. Race-condition-frei
(keine separate Sequenz/Locking nötig), auf Kosten einer "schönen"
Jahres-/Zähler-Nummer — für Phase 4 ausreichend, bei Bedarf später durch ein
echtes Nummernkreis-Konzept ersetzbar (dann eigenes ADR, insbesondere sobald
Rechnungsnummern in Phase 7 eine strengere Logik brauchen).

**Status (`ProjectStatus`-Enum):** `draft` (Entwurf) / `quote` (Angebot) /
`in_progress` (In Bearbeitung) / `waiting` (Wartet) / `done` (Abgeschlossen) /
`archived` (Archiv) — deckungsgleich mit den Filter-Chips aus dem
Google-Stitch-Mockup (Screen 2).

### Projektordner (Files)

Wiederverwendet die Logik aus `ErpFolderService` (ADR-0009):
`ERP/Projekte/<project_number>` wird bei Projekterstellung angelegt, die
Node-ID in `files_folder_id` gespeichert. Der Ordnername nutzt bewusst nur die
stabile Projektnummer, nicht den (änderbaren) Titel — vermeidet das in
Brainstorming/Phase-3-ADR offen gelassene Problem "wie findet man
umbenannte/verschobene Ordner wieder" für den Normalfall.

### Termine

Keine neue Tabelle nötig — nutzt direkt die generische
`erp_calendar_links`-Tabelle aus Phase 3 mit `resourceType = 'projekte'` und
`resourceId = <project_id>`. Genau der Anwendungsfall, für den die Tabelle in
ADR-0009 vorbereitet wurde.

### Aufgaben (Checkliste)

`erp_project_tasks`: `project_id`, `title`, `done` (bool), `position` (int,
Sortierung), Timestamps. Bewusst flach (keine Unteraufgaben, keine
Zuweisung) — "Aufgaben/Checklisten-**Grundlage**" laut Roadmap, kein
vollwertiges Taskmanagement.

### Aufträge

`erp_orders`: `project_id`, `title`, `status` (`draft`/`confirmed`/`done`),
`description`, Timestamps. Kein Bezug zu Angebotspositionen (existieren noch
nicht) — wird in Phase 5 erweitert, sobald es echte Angebote gibt, aus denen
ein Auftrag entstehen kann.

### Rechte

- Projekte + Aufgaben: `ResourceType::Projekte` (Aufgaben sind Unterressource
  eines Projekts, kein eigener Navigationspunkt — kein eigener Rechtebereich
  nötig).
- Aufträge: `ResourceType::Auftraege` (bereits seit Phase 1/2 als eigener
  Navigationspunkt/Rechtebereich vorgesehen).

## Konsequenzen

- Ein Projekt kann ohne Angebot/Auftrag/Rechnung existieren — passt zum
  Workflow-Entwurf aus dem Brainstorming ("Angebot kann ohne Projekt starten,
  aber bei Annahme ein Projekt/Auftrag erzeugen" — hier zunächst nur die
  Projekt-Seite davon).
- `customer_contact_uid` ist optional: ein Projekt kann angelegt werden, bevor
  der Kunde final geklärt ist (Baustellenleiter-Realität).
- Löschen ist für Projekte bewusst nicht vorgesehen (nur Status `archived`) —
  Projekte sind mit Dateien/Terminen/Aufträgen verknüpft, ein Hard-Delete
  würde verwaiste Referenzen erzeugen.

## Alternativen erwogen

- Auftrag direkt als Statuswert am Projekt statt eigener Tabelle: zu grob,
  ein Projekt kann mehrere Aufträge haben (Nachträge, Teilaufträge).
- Eigene Termin-Tabelle statt Wiederverwendung von `erp_calendar_links`:
  unnötig, die generische Struktur aus Phase 3 deckt den Fall exakt ab.
