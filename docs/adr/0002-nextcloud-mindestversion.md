# ADR-0002: Ziel-Nextcloud-Version

**Status:** accepted
**Datum:** 2026-08-19

## Kontext

Die Roadmap listet die Ziel-/Mindestversion von Nextcloud als offene Klärung.
Aktuell verfügbare offizielle Docker-Image-Tags (Stand 2026-08-19): Nextcloud 34
(aktuell stable), 33, 32 als vorherige Majors.

## Entscheidung

- Entwickelt und getestet wird gegen **Nextcloud 34** (aktuelle stable-Reihe,
  Docker-Tag `34-apache` in der lokalen Testumgebung).
- `appinfo/info.xml` deklariert `min-version="30"` und `max-version="34"`, um die
  App nicht sofort an genau eine Minor-Version zu binden, aber auch nicht auf
  Kompatibilität mit sehr alten Majors zu versprechen, die nicht getestet werden.
- Die Spanne wird bei jedem Nextcloud-Major-Release-Wechsel überprüft und ggf. per
  neuem ADR oder Änderung dieses ADR-Eintrags nachgezogen.

## Konsequenzen

- Tests/CI laufen gegen eine konkrete, aktuelle Version — reproduzierbar, aber nicht
  automatisch gegen ältere Majors abgesichert.
- `min-version="30"` ist eine Annahme (keine Tests gegen 30–33 durchgeführt); falls
  bei einer echten alten Installation Inkompatibilitäten auftreten, muss die Spanne
  nach unten korrigiert werden.

## Alternativen erwogen

- Nur exakte Version (`min-version = max-version = 34`): zu eng, jedes
  Nextcloud-Minor-Update würde die App sofort blockieren.
- Sehr breite Spanne ohne obere Grenze: widerspricht der Praxis, dass
  AppFramework-APIs zwischen Majors brechen können.
