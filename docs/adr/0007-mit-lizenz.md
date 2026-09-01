# ADR-0007: MIT-Lizenz

**Status:** superseded-by [ADR-0023](0023-agpl-lizenzwechsel.md)
**Datum:** 2026-08-19

## Kontext

Vorgabe aus der Roadmap: Repository unter MIT-Lizenz veröffentlichen.

## Entscheidung

Das Repository steht unter der MIT-Lizenz (`LICENSE`, Copyright Kay Beckmann,
2026). Alle direkten Abhängigkeiten (Composer-/npm-Pakete) werden vor jeder
Veröffentlichung auf Lizenzkompatibilität geprüft (Phase 12,
"Lizenz-/Dependency-Review").

## Konsequenzen

- Keine GPL-/AGPL-lizenzierten Pflichtabhängigkeiten, die MIT-Veröffentlichung
  einschränken würden — insbesondere bei der Wahl von npm-/Composer-Paketen
  künftig gegenprüfen (Nextclouds `@nextcloud/*`-Pakete sind i. d. R. AGPL-3.0
  für Server-Code bzw. permissive für reine Frontend-Libraries; das muss vor
  Veröffentlichung explizit verifiziert werden, siehe Phase 12).
- Keine proprietären Assets oder Kundendaten im öffentlichen Repo.

## Alternativen erwogen

- AGPL (wie Nextcloud Server selbst): stärkerer Copyleft-Schutz, aber Vorgabe war
  explizit MIT — nicht abgewichen.
