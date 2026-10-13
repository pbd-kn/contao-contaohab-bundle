# Projektregeln: ContaoHab-Bundle

## Aufgabe und Aufbau

- Dieses Repository ist das Contao-Bundle, das Schwesterprojekt `../COH-Sensorcollector` enthält Collector und Raspberry-API. Änderungen auf beiden Seiten nur vornehmen, wenn die Aufgabe sie benötigt.
- Unterstützte Basis laut `composer.json`: PHP `^8.1`, Contao `^4.13 || ^5.0`. Keine neueren Sprachfunktionen oder Contao-Abhängigkeiten ohne bewusste Anpassung dieser Anforderungen einführen.
- Namespace: `PbdKn\ContaoContaohabBundle\`, PSR-4 nach `src/`.
- Controller, Services und Models liegen unter `src/`; DCA, Übersetzungen und Templates unter `contao/`; veröffentlichte Assets unter `public/`.
- Dependency Injection wird in `src/Resources/config/services.yaml` eingerichtet. Sensor-Fetcher erhalten über `SensorFetcherInterface` den Tag `sensor.fetcher`; `SensorManager` verwendet den Tagged Iterator.

## Datenfluss und Kompatibilität

- Der alte `src/Service/SyncService.php` ist ausdrücklich deaktiviert. Nicht wieder aktivieren und keine Sensorwerte über diesen Weg in die Contao-Datenbank kopieren. Die manuelle Konfigurationsübertragung verwendet `RaspberryConfigPushService`; Messwerte werden über `RaspberrySensorApiClient` gelesen.
- Bei API-Änderungen den Gegenpart unter `../COH-Sensorcollector/var/www/html/api/coh/` prüfen. Bestehende Sensor-IDs, lokale IDs, JSON-Felder, Einheiten und gespeicherte DCA-Auswahlwerte kompatibel halten oder eine ausdrücklich benötigte Migration beschreiben.
- Für neue Backendfelder DCA und passende deutsche/englische Sprachdateien gemeinsam prüfen. Bei Templateänderungen Controllerdaten und verwendete Teiltemplates berücksichtigen; dynamische HTML-Ausgaben passend escapen.
- Für Zähler-History gilt `docs/zaehler-history.md`: absolute Zählerstände bleiben erhalten; Differenzen entstehen bei der Historyabfrage. Fehlende Basiswerte sind unbekannt, keine erfundenen Nullen. Kalendertage und Sommer-/Winterzeit berücksichtigen.
- Für KM271-Schaltzeiten gilt `docs/KM271-Schaltzeiten.md`: Vorschau schreibt nichts; Schreiben braucht die bestehende Bestätigung und einen frisch geprüften Ausgangsstand. Konflikte, unvollständige Daten und Teilfehler nicht durch automatische Wiederholung des Gesamtplans übergehen.
- Bei Modbus-Registern und JSON-Pfaden `docs/AMPERE_STORAGEPRO_MODBUS_JSON_REGISTER.md` konsultieren.
- Konfiguration anhand des tatsächlich verwendeten Services und seiner DI-Parameter ermitteln. Ältere Beschreibungen im Collector-`PROJECT.md` sind kein Nachweis, dass deren Backend-Einstellungstabellen im aktuellen Bundle vorhanden sind.

## Arbeiten und Prüfen

- Vor Änderungen `git status --short` prüfen und vorhandene Nutzeränderungen erhalten. `.editorconfig` und den Stil der betroffenen Datei beachten; keine pauschale Neuformatierung.
- Zugangsdaten, Cookies und Tokens aus lokalen Dateien oder Altcode nicht in Antworten, Dokumentation, Testfixtures oder neue Commits übernehmen. `.env.local` bleibt lokal.
- Geänderte PHP-Dateien mit `php -l <Datei>` prüfen. HTML5-Templates mit eingebettetem PHP bei Bedarf ebenfalls linten.
- KM271-Templates lassen sich ohne Hardware mit `php tests/Km271ScheduleTemplateTest.php` prüfen.
- `tests/Service/Sensors/` enthält PHPUnit-Tests. Erst vorhandenen PHPUnit-Runner und Bootstrap im ausführenden Contao-Projekt ermitteln. Hier fehlen ein lokales `vendor/` und eine PHPUnit-Konfiguration; `composer unit-tests` ist deshalb kein nachgewiesen lauffähiger Testweg.
- ECS ist in `ecs.php` konfiguriert. `composer cs-fixer` verändert Dateien in `src/` und `tests/`; nicht als pauschalen Check verwenden. Wenn ECS verfügbar ist, zunächst ohne `--fix` prüfen und Änderungen auf den Auftrag begrenzen.
- `npm run build` ist derzeit unvollständig: Es ruft nicht definierte Scripts `copyf7:*` und `copyyaml` auf. Für benötigte Bibliotheken vorhandene einzelne Kopierscripts prüfen; den Gesamtbuild nicht als erfolgreiche Validierung angeben. Dateien in `node_modules/` und kopierte Bibliotheksdateien nicht manuell bearbeiten.
- Bei Änderungen an der Schnittstelle erforderliche Dateien auf beiden Projekten, Cache-/Asset-Aktualisierung und Raspberry-Neustart benennen. Lokale Prüfung ersetzt keine Installation oder Hardwareprüfung; solche Aktionen nur im beauftragten Umfang ausführen.
