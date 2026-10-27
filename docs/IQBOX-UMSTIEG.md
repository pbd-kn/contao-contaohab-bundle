# IQBox durch Raspi ersetzen: Vorbereitung

Stand: 22.10.2026. Umsetzung und Hardware-Inbetriebnahme erfolgen spaeter.

Das Vorbereitungspaket liegt im Schwesterprojekt:

- `../COH-Sensorcollector/energy-manager/README.md`: Architektur, offene Arbeiten,
  Installation, stufenweiser Umstieg, Abnahme und Rueckfall.
- `../COH-Sensorcollector/energy-manager/config.example.json`: deaktivierte
  Konfigurationsvorlage ohne Zugangsdaten und ohne erfundene Geraeteparameter.
- `../COH-Sensorcollector/energy-manager/schema.draft.sql`: Tabellenentwurf;
  noch nicht auf einer Datenbank ausfuehren.

Ziel ist ein eigener 24/7-PHP-Dienst auf dem Raspi: StoragePro-Modbus lesen,
PV-Ueberschuss auswerten, vorhandenen my-PV-Heizstab lokal regeln und eine
zukuenftige Waermepumpe ueber Shelly/SG Ready anfordern. Shelly-Modell und
SG-Ready-Belegung bleiben bis zur Installation offen; dieser Ausgang bleibt aus.

Contao/WAMP soll die Raspi-API fuer Livewerte, History und Einstellungen verwenden.
Die Regelung muss ohne WAMP funktionieren. Bestehende Sensor-IDs, JSON-Pfade und
absolute Zaehlerwerte bleiben kompatibel. Der deaktivierte SyncService wird
nicht verwendet. Fuer Konfiguration und Befehle werden spaeter passende API-
Erweiterungen mit Authentifizierung und Revisionspruefung benoetigt.

In `energy-manager/` sind inzwischen ein lesender PHP-CLI-Dienst mit
DB-Speicherung, eine reine Ueberschussberechnung, ein Offline-Test und eine
systemd-Vorlage vorhanden. Startanleitung steht in der dortigen README.
Aktive Steuerung, Heizstab-Telemetriezuordnung, neue API und Oberflaeche fehlen noch.
Zusaetzlich ist unter `energy-manager/StorageProWriter.php` ein separater manueller
FC06-/FC16-Schreibclient vorbereitet. Freigabeliste ist leer/deaktiviert;
Vorschau, Bestaetigung, Konfliktpruefung und Ruecklesepruefung stehen im JSON-Dialog
bereit. Anleitung: `../COH-Sensorcollector/energy-manager/WRITING.md`.
Bestehende Collector- und HTTP-API-Clients bleiben lesend.
Keine Raspi-Dateien hochgeladen, Dienste veraendert oder Datenbanken migriert.
Die vorhandene IQBox und Heizstabregelung bleiben bis zur kontrollierten
Uebernahme in Betrieb. Ein vollstaendiger IQBox-Ersatz wird erst nach Pruefung
aller tatsaechlichen Geraeteabhaengigkeiten und erfolgreicher Abnahme bestaetigt.
