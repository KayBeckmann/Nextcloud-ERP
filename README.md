# Nextcloud ERP

Branchenneutrales ERP-Add-on für Nextcloud — für Handwerks- und Dienstleistungsbetriebe
über alle Gewerke hinweg (Elektro, SHK, Tischler, Maler, Garten-/Landschaftsbau u. a.).

Status: **frühe Entwicklung (Phase 0/1)**. Noch nicht produktiv einsetzbar.

## Ziel

Ein ERP-System als natives Nextcloud-Add-on, das Projekte, Angebote, Aufträge,
Rechnungen, Artikel, Produkte, Arbeitsstunden, Lager, Zeitwirtschaft und Verwaltung
abbildet — ohne eigene Benutzerverwaltung, ohne zweite Plattform neben Nextcloud.

Nextcloud liefert die Plattform (User, Gruppen, Rechte-Grundlage, Calendar, Contacts,
Files, Mail). Diese App ergänzt die fehlende Business-Logik und referenziert
bestehende Nextcloud-Daten, statt sie zu duplizieren.

## Scope

**Zuerst:** vollständiges Web-UI innerhalb von Nextcloud. Die Web-Oberfläche soll
alles können — auch ein Baustellenleiter mit Laptop auf der Baustelle soll ohne
Wechsel zu einer mobilen App arbeiten können.

**Später:** ein reduzierter Flutter-Client für Android/Linux/Windows als
Monteur-/Unterwegs-Client mit gezieltem Offline-Cache. Wird erst gebaut, wenn das
Web-Add-on reif genug ist. Die API wird von Anfang an vorbereitet, damit Flutter
ohne größeren Umbau andocken kann.

## Nicht-Ziele (erste Umsetzungsrunde)

- keine Flutter-App
- keine vollständige Finanzbuchhaltung / Lohnabrechnung
- kein automatischer Zahlungsabgleich, kein DATEV-Export
- kein vollständiges Bestandsführungssystem im ersten Wurf
- keine produktive Rechnungsstellung ohne vorherige rechtliche Prüfung
- keine harte Abhängigkeit von bestehenden Community-ERP-Apps (NextLedger, Gestion, …)
  — diese dienen höchstens als Ideengeber

## Architektur-Leitplanken

- Maßgeblicher Style-/Implementierungsguide: die offizielle
  [Nextcloud Developer Documentation](https://docs.nextcloud.com/server/latest/developer_manual/).
- API-first: Die App stellt von Anfang an eine versionierte API (`/api/v1/...`)
  bereit, die dieselbe Service-/Businesslogik wie das Web-UI nutzt.
- Nextcloud-nativ: User/Gruppen, Contacts, Calendar und Files werden referenziert,
  nicht dupliziert.
- Abweichungen vom offiziellen Guide werden bewusst als ADR dokumentiert
  (siehe [`docs/adr/`](docs/adr/)).

## Repo-Struktur

```text
repo/
├── nextcloud/erp/     Nextcloud-App/Add-on (PHP + Vue)
├── docs/               Architektur, API, ADRs, Roadmap
├── docker/             Docker/Compose-Testumgebung
├── tests/              übergreifende Tests / Fixtures
├── client/flutter/     späterer Flutter-Client (aktuell nur Doku/Platzhalter)
├── LICENSE             MIT
└── README.md
```

## Entwicklungsumgebung (Docker)

Reproduzierbare lokale Nextcloud-Testinstanz per Docker Compose:

```bash
cd docker
cp .env.example .env
docker compose up -d
```

Details, Mindestversionen und Testbefehle: [`docker/README.md`](docker/README.md).

## Roadmap

Die vollständige Umsetzungsplanung (14 Phasen) liegt außerhalb dieses Repos im
privaten Projekt-Vault. Der öffentliche Fortschritt wird laufend in
[`docs/roadmap.md`](docs/roadmap.md) und über ADRs in [`docs/adr/`](docs/adr/)
nachvollziehbar gehalten.

Aktueller Stand: Phase 0 (Projekt-/Architekturfundament) und Phase 1
(App-Skeleton + Web-Grundgerüst) in Arbeit.

## Lizenz

MIT — siehe [`LICENSE`](LICENSE).
