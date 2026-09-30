# KM271: Heizprogramme und eigene Schaltzeiten

In beiden Buderus-Templates gibt es den Bereich „Heizprogramme und Schaltzeiten“.
Voraussetzung bleibt die aktivierte KM271-Schreibfunktion am Inhaltselement.

## Bedienung

- HK1 und HK2: Eigen, Familie, Früh, Spät, Vormittag, Nachmittag, Mittag,
  Single und Senior über „Programm auswählen“ aktivieren.
- „Schaltzeiten aus der Heizung laden“ liest den eigenen Programmspeicher des
  gewählten Heizkreises. Der vorhandene eigene Zeitplan ist nicht automatisch
  eine Kopie des gerade ausgewählten Standardprogramms.
- Bis zu 21 Intervalle mit Beginn/Ende und Wochentag, 10-Minuten-Raster.
  Intervalle nach Beginn (Montag bis Sonntag) anordnen. Ende am Folgetag oder
  über den Wochenwechsel ist möglich. Abgewählte Zeilen werden gelöscht.
- „Änderungen prüfen“ erzeugt eine Vorschau ohne Schreibzugriff. Nach Änderungen
  an den Eingaben ist eine neue Vorschau nötig. Erst die Bestätigung speichert
  und wählt Eigen. Automatik-/Tag-/Nachtbetrieb wird dabei nicht geändert.
- Anschließend erneut laden und Zeiten kontrollieren. Ein DLE bestätigt die
  Übertragung, nicht den unabhängig überprüften Speicherinhalt.

Die Standardprogramme sind auswählbar, ihre einzelnen Zeitpläne werden nicht
überschrieben. Beschreibbar ist der eigene Programmspeicher je Heizkreis.
Grundlage: [FHEM KM271, hk1_timer/hk2_timer](https://raw.githubusercontent.com/fhem/fhem-mirror/master/fhem/FHEM/00_KM271.pm).

## Installation

Contao: Controller `CohBuderusKm271Chart`, Client `Km271WriteApiClient`, beide
`ce_coh_buderus_km271_chart_display*`-Templates, neues Teiltemplate
`coh_km271_schedule.html5` und `public/css/coh_aktuell_panel.css` übernehmen.
Contao-Cache und gegebenenfalls veröffentlichte Bundle-Assets aktualisieren.

Auf dem Raspberry gemeinsam aktualisieren:

| Quelldatei in COH-Sensorcollector | Ziel |
| --- | --- |
| `sensorCollect/Sensor/Km271/Km271Schedule.php` | `/home/peter/scripts/coh/sensorcollect/Sensor/Km271/` |
| `sensorCollect/Sensor/Km271/Km271ConnectionLock.php` | gleiches Verzeichnis |
| `sensorCollect/Sensor/Km271/Km271WriteCommandEncoder.php` | gleiches Verzeichnis |
| `sensorCollect/Sensor/Km271/Km271Decoder.php` | gleiches Verzeichnis |
| `sensorCollect/Sensor/BuderusKm271SensorService.php` | `/home/peter/scripts/coh/sensorcollect/Sensor/` |
| `var/www/html/api/coh/km271-command.php` | `/var/www/html/api/coh/km271-command.php` |

Den Collector anschließend neu starten. Die vorhandene Einstellung
`COH_KM271_WRITE_ENABLED` bleibt maßgeblich; Zugangstoken und HTTPS bleiben
unverändert erforderlich. Vor Installation der neuen API zeigt Contao einen
Hinweis statt eines nicht funktionierenden Editors.

Collector und API müssen dieselbe KM271-Hostadresse/Port verwenden.
Unter Linux liegt die gemeinsame Sperrdatei in `/run/lock`, damit sie auch
mit Apache `PrivateTmp` für beide Prozesse sichtbar bleibt. Auf anderen
Systemen wird der temporäre Ordner verwendet.
Der PHP-/Proxy-Request-Timeout muss lange Lesezyklen zulassen
(Einlesen bis 120 Sekunden, Schreiben einschließlich erneutem Einlesen länger;
Client/PHP setzen 360 Sekunden).

Vor dem Schreiben werden alle 14 Timerblöcke frisch gelesen und mit der
Vorschau verglichen. Bei Konflikten oder unvollständigen Daten wird nichts
geschrieben. Unbekannte Punktformate oder eine nicht als Tag-/Nacht-Paare
interpretierbare Folge werden ebenfalls abgelehnt. Es gibt keine erfundenen
Standardwerte als Ersatz für fehlende Daten.

Mehrere Telegramme sind nicht atomar. Bei einem Fehler kann ein Teil bereits
gespeichert sein; die Oberfläche fordert erneutes Einlesen. Eigen wird erst
nach Bestätigung aller Zeitblöcke gewählt. War Eigen schon aktiv, können
Teiländerungen unmittelbar wirken. Ausgangsstand und geplante Zeiten werden
vor dem Versand unter `KM271_SCHEDULE_INTENT` im PHP-Fehlerprotokoll gesichert.
Ein Webauftrag wird nach einem Versandversuch ungültig; kein automatisches
Wiederholen des gesamten Plans.

## Lokale Prüfung ohne Heizungszugriff

```powershell
php tests/Km271ScheduleTemplateTest.php
php ../COH-Sensorcollector/execScripts/km271-schedule-test.php
php ../COH-Sensorcollector/execScripts/km271-write-command-test.php
```

Der Schaltzeiten-Test verwendet nur lokale TCP-Testverbindungen und prüft die
produktive 3964R-Implementierung, Blockgrenzen, HK2, Wochenwechsel, Löschung,
ungültige Eingaben, konkurrierende Änderungen, Teilfehler und Verbindungssperren.
Ein Hardwaretest und die Übertragung auf den Raspberry sind damit nicht ersetzt.
