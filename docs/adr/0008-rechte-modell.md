# ADR-0008: ERP-Rechte-Modell

**Status:** accepted
**Datum:** 2026-08-19

## Kontext

Laut Roadmap/Brainstorming sollen ERP-Rechte technisch von Verrechnungssätzen
getrennt sein, granular pro Modul vergeben werden können (kein Zugriff / lesen /
lesen & schreiben / freigeben-buchen-abschließen / administrieren) und sowohl
einzelnen Nextcloud-Usern als auch Nextcloud-Gruppen zugeordnet werden können.

## Entscheidung

- Eigene Tabelle `erp_permissions` (`principal_type`, `principal_id`,
  `resource_type`, `permission`), unique je (`principal_type`, `principal_id`,
  `resource_type`). Kein Duplizieren von Nextcloud-Usern/Gruppen — `principal_id`
  verweist nur auf die Nextcloud-UID bzw. den Gruppennamen.
- `resource_type` ist eine feste, code-definierte Liste (`ResourceType`-Enum),
  identisch zu den 16 Hauptbereichen der Web-Navigation (`dashboard`, `projekte`,
  `kalender-personal`, `kunden`, `lieferanten`, `artikel`, `produkte`, `angebote`,
  `auftraege`, `rechnungen`, `lager`, `fuhrpark`, `kosten-kalkulation`,
  `stunden-zeitkonto`, `berechtigungen-saetze`, `api-sync`, `einstellungen`).
- `permission` ist eine geordnete Stufenskala (`PermissionLevel`-Enum):
  `none` < `read` < `write` < `approve` < `admin`.
- **Auflösung:** Für einen User werden alle direkt zugeordneten Einträge sowie
  die Einträge aller Nextcloud-Gruppen, in denen der User Mitglied ist,
  eingesammelt; es gilt die **höchste** gefundene Stufe je Ressource
  (inklusiv-ODER, keine Veto-Logik). Kein Eintrag = `none`.
- **Nextcloud-Instanz-Admins** (Mitglied der Nextcloud-Gruppe `admin`) erhalten
  implizit `admin` auf alle ERP-Ressourcen, unabhängig von `erp_permissions` —
  verhindert Aussperrung, bevor die Matrix gepflegt wurde, und deckt sich mit der
  Erwartung "Admin kann alles".
- Die reine Auflösungslogik (`PermissionResolver`) ist von der
  DB-/Nextcloud-Anbindung (`PermissionService`) getrennt, damit sie ohne
  Datenbank/Server-Bootstrap unit-testbar bleibt.
- Verwaltung der Matrix (lesen und schreiben) ist vorerst ausschließlich
  Nextcloud-Instanz-Admins vorbehalten (`#[RequireAdmin]`-Standardverhalten der
  Controller, kein `#[NoAdminRequired]`). Eine feinere Delegation über die
  ERP-Ressource `berechtigungen-saetze` selbst ist eine spätere Ausbaustufe,
  keine Phase-2-Anforderung.

## Konsequenzen

- Rechte- und Satzsystem bleiben unabhängig voneinander persistiert und
  auflösbar (Satzsystem entsteht erst in Phase 6).
- Ein User kann über mehrere Gruppen unterschiedliche Rechte "erben" — die
  inklusiv-ODER-Regel ist einfach nachvollziehbar, kann aber in seltenen Fällen
  großzügiger sein als eine restriktivere "niedrigste Stufe gewinnt"-Regel.
  Bewusst gewählt, weil Rechte-Systeme in der Praxis meist additiv statt
  restriktiv gedacht werden (vgl. Nextclouds eigene Gruppenrechte).
- Der NC-Admin-Fallback bedeutet: Ein Nextcloud-Admin sieht in der Matrix ggf.
  keinen expliziten Eintrag, hat aber trotzdem Vollzugriff — das UI muss das
  sichtbar machen, um Verwirrung zu vermeiden (geplant für die Web-UI-Ansicht).

## Alternativen erwogen

- "Niedrigste Stufe gewinnt" bei Mehrfachgruppenzugehörigkeit: konsequenter im
  Sinne von Least Privilege, aber unüblich für den hier beschriebenen
  Anwendungsfall (Gruppen als additive Rollen, nicht als Einschränkungen) und
  schwerer vermittelbar ("warum sehe ich das trotz Gruppe X nicht").
- Rechte ausschließlich über Nextcloud-Gruppen ohne eigene Tabelle: zu grob,
  deckt die geforderten 5 Berechtigungsstufen pro Modul nicht ab.
