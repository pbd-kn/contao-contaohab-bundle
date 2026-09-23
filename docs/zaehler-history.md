# Wertdarstellung für Zähler

Im Sensor **Wertdarstellung → Zähler: Differenz im Historychart** wählen und History
aktivieren. Stündliche Kontrollpunkte sind empfehlenswert. Absolute Zählerstände
bleiben gespeichert und im Displaychart sichtbar. Die Tageskurve zeigt die
aufsummierte Differenz seit Tagesbeginn (Start bei null). Woche/Monat zeigen den
Zuwachs je Kalendertag, Jahr je Kalendermonat. Ohne Tagesanfangswert bleibt die
Tageskurve unbekannt.

Die bisherigen Optionen und ihre gespeicherten Schlüssel bleiben unverändert.
Es gibt keine Schemaänderung und keine automatische Umstellung vorhandener Sensoren.

## Installation

Contao-Bundle aktualisieren und Cache leeren. Auf dem Raspberry gemeinsam installieren:

- `sensorCollect/SensorManager.php`
- `var/www/html/api/coh/sensorvalues.php`
- `var/www/html/api/coh/counter_history.php` (neu)

Collector neu starten, dann den neuen Modus einstellen und die Sensorkonfiguration
zum Raspberry übertragen. Eine alte API wird beim Zähler-Historyabruf erkannt.

## Berechnung

Der letzte bekannte Stand am oder vor Beginn dient als Basis. Ein Messwert genau
auf der Intervallgrenze schließt das vorherige Intervall ab. Bei einem Rücksprung
wird eine Rücksetzung auf null angenommen. Gerätewechsel und Überläufe können
damit nicht sicher unterschieden werden. Ohne Basis bleibt das erste Intervall leer.

Ohne neue Messung wird der letzte Stand fortgeschrieben (Zuwachs null); dies ist
kein Nachweis für die Erreichbarkeit des Collectors. Zuwächse werden dem Intervall
der Beobachtung zugeordnet, nicht über Datenlücken verteilt. Das laufende Intervall
ist vorläufig, zukünftige Intervalle entfallen. Es gilt die Contao-Zeitzone.

Bestehende absolute Historien sind verwendbar. Bereits mit Heute/7/30/365 Tage
umgerechnet gespeicherte Historien werden nicht in absolute Stände zurückgerechnet.

Tests im Collector-Projekt: `php tests/counter_history_test.php`.
