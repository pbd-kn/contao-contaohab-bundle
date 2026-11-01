# Modbus-Schreibdialog im aktuellen Frontend-Element

Zusätzlich zu den zwei ausgeführten Schreibfreigaben zeigt die Auswahl jetzt
84 weitere dokumentierte Felder in Gruppen an. Diese sind mit „noch nicht
freigegeben“ gekennzeichnet; ihre Auswahl zeigt Register und Bereich, schreibt
aber nichts. Umfang, Quellen und offene Prüfungen stehen in
`docs/Modbus-Schreiberweiterung-Pruefung.md`.

Der Dialog wird im Inhaltselement „COH Aktuell“ über **Modbus-Schreibdialog
aktivieren** freigegeben. Er erscheint nur für angemeldete Frontend-Mitglieder.
Alle angemeldeten Mitglieder mit Zugriff auf dieses freigegebene Element können
die Einstellungen schreiben. Das Element deshalb auf einer passend geschützten
Seite verwenden.

Nach Installation der Bundle-Dateien das Contao-Datenbankschema aktualisieren
(`tl_content.coh_modbus_write`) und den Contao-Cache erneuern. Keine bestehenden
Messwerte oder Sensor-IDs müssen migriert werden.

Mindestens ein aktiver Sensor der Quelle `IQBox` oder `wechselrichterModbus`
muss im Element ausgewählt sein. Die Geräteadresse kommt über
`sensorSource = geraeteID` aus `tl_coh_geraete.geraeteUrl`, entsprechend dem
Collector. Der Browser erhält weder Gerätehost noch API-Token.

Die Templates `ce_coh_aktuell_chart_display` und `ce_coh_aktuell_chart` binden
`coh_modbus_control` ein. Eigene Templates müssen dieses Teiltemplate ebenfalls
mit den Controllerdaten einbinden.

Im Frontend steht eine eigene Togglegruppe „Wechselrichter / IQBox – Einstellungen“
unter den Messwerten und vor „HeizstabServer“. Darin sind die Einstellungen je
Gerät und Themenbereich als Karten mit Kurzbeschreibung angeordnet. Zeitfenster
haben eigene aufklappbare Untergruppen. „Bearbeiten“ beziehungsweise „Beschreibung“
öffnet ein modales Dialogfenster für genau diese Einstellung mit ausführlicher
Erklärung, Voraussetzungen und den verfügbaren Auswahlwerten.
Nicht abgefragte Werte sind ausdrücklich als „Noch nicht gelesen“ gekennzeichnet.
Das komplette Fenster einschließlich
Vorschau, Bestätigung und Bedienlogik liegt im eigenen Teiltemplate
`contao/templates/frontend/coh_modbus_control.html5`.
Die Wirkungsbeschreibungen stehen zentral in `src/Service/ModbusSettingDescriptions.php`;
sie beeinflussen keine Schreibfreigaben. Bei nicht ausreichend belegter Wirkung
wird die Unsicherheit in der Erklärung benannt.
Schließen ist per Schaltfläche oder Escape möglich. Nach Prüfung oder Schreiben
öffnet sich das Fenster einmal automatisch für die Vorschau beziehungsweise das
Ergebnis. Die Auswahl bleibt nach der Vorschau erhalten.

## Freigegebene Einstellungen

Die gemeinsame Definition steht unter
`COH-Sensorcollector/sensorCollect/Modbus/Profiles/saj-h2.php` in `controls`.
Das AMPERE-Profil übernimmt sie. Beleg ist die lokale Beschreibung
`execScripts/WechelrichterRegister.txt`, Abschnitt „Writable input entities“.
Diese beschreibt eine Home-Assistant-Integration, kein vollständiges
Herstellerprotokoll für jede Firmware. Ein erfolgreicher Offline-Test ist kein
Nachweis der Schreibunterstützung des installierten Geräts.

| Einstellung | Register | Erlaubte Werte |
|---|---|---|
| Betriebsmodus | `0x3647` | 0 Eigenverbrauch, 1 Zeitbetrieb, 2 Backup, 3 Passiv |
| Passive Batteriesteuerung | `0x3636` | 0 Bereitschaft, 1 Entladen, 2 Laden |

Passive Steuerung setzt einen frisch gelesenen Betriebsmodus 3 voraus. Der
Alterungsmodus 14 wird bewusst nicht freigegeben. Leistungsgrenzen und Zeitpläne
werden erst nach Klärung von Codierung, Grenzen und Abhängigkeiten ergänzt.
Die bestehenden Sollwert-Messregister `0x4023…` werden nicht zum Schreiben
freigegeben.

## Ablauf und Prüfung

1. Contao lädt den Einstellungskatalog über HTTPS mit dem bestehenden
   Raspberry-API-Token von `modbus-control.php`.
2. Gerät, Einstellung und erlaubten Zielwert auswählen. „Änderung prüfen“ liest
   nur den aktuellen Registerwert. Es wird nichts geschrieben.
3. Die Vorschau nennt Gerät, Einstellung, Ausgangs- und Zielwert jeweils mit
   Bezeichnung und Rohwert. Der Auftrag wird zwei Minuten in der Sitzung
   gespeichert. Adresse, Wert und Profil des Schreibauftrags stammen daraus.
4. Checkbox und zusätzliche JavaScript-Bestätigungsfrage bestätigen die konkrete
   Änderung. Auch ohne JavaScript verlangt der Server die explizite Checkbox;
   die dynamischen Auswahlfelder benötigen JavaScript.
5. Contao prüft Anmeldung, Elementfreigabe, aktive ausgewählte Sensoren,
   Request-Token und einmalige Vorschaukennung. Die Vorschau wird vor dem
   API-Aufruf verbraucht. POST wird per 303 auf GET umgeleitet.
6. Der Raspberry prüft Token, Profilfreigabe, Auswahlwert, privaten Zielhost,
   frisch gelesenen Ausgangswert und Voraussetzungen. Er schreibt ein Register
   mit FC06 und liest es zurück. Schreibdialoge desselben aufgelösten Hosts
   werden serialisiert. Collector und externe Modbus-Clients nehmen an dieser
   Dialogsperre nicht teil; gleichzeitige Zugriffe können Gerätefehler verursachen.
7. Timeout, Konflikt und abweichendes Rücklesen führen zu einem Fehler und keiner
   automatischen Wiederholung. Nach einem unklaren Ergebnis zunächst neu lesen.

Der Dialog ändert keine Collector-Messwertspeicherung; die Anzeige der regulären
Sensorwerte folgt weiterhin dem nächsten Poll. Ein Betriebsmoduswechsel kann
das Lade-/Entladeverhalten ändern; der Dialog schaltet nicht automatisch durch
mehrere Modi oder Folgeaktionen.

## Raspberry-Dateien

Vor dem Ersetzen vorhandene Zieldateien sichern:

- `sensorCollect/Modbus/Profiles/saj-h2.php` → `/home/peter/scripts/coh/sensorcollect/Modbus/Profiles/saj-h2.php`
- `sensorCollect/Modbus/ModbusProfile.php`, `ModbusTcpClient.php`, `ModbusControl.php` → entsprechend unter `/home/peter/scripts/coh/sensorcollect/Modbus/`
- `var/www/html/api/coh/modbus-control.php` → `/var/www/html/api/coh/modbus-control.php`

Die übrigen Modbus-Bootstrap-Dateien und `api_env.php` müssen installiert sein.
Der Webserver benötigt Lese-/Durchgangsrechte auf den Codepfad einschließlich
`Modbus/` und `Modbus/Profiles/`. Auf dem vorhandenen Raspberry ist `www-data`
Mitglied der Gruppe `peter`; für diese beiden Verzeichnisse wurden die fehlenden
Gruppenrechte `g+rx` ergänzt, ohne zusätzliche Schreibrechte zu vergeben.
Nach Änderungen am vom Collector geladenen Profil/Transport `collect.service`
neu starten und den Status prüfen. Die PHP-API lädt die Dateien je Anfrage.

Offline prüfen: `php tests/modbus_control_test.php` und
`php tests/modbus_snapshot_test.php` im Collector sowie
`php tests/ModbusControlTemplateTest.php` im Bundle. Die TCP-Tests greifen
ausschließlich auf `127.0.0.1` zu. Kein echter Schreibauftrag gehört zur Prüfung.

Den Contao-Dialog zusätzlich mit echten Symfony-/Contao-Klassen und simuliertem
HTTP-/DB-Zugriff prüfen:
`php tests/ModbusWriteDialogTest.php /pfad/zum/contao/vendor/autoload.php`.

Raspberry-Installation am 01.11.2026 durchgeführt, vorherige Dateien gesichert
unter `/home/peter/scripts/coh/modbus-control-backup-20261101-102741/`.
Dateiprüfsummen stimmen mit den lokalen Quellen überein; Collector läuft nach
Neustart. HTTP-Katalog mit Authentifizierung und Ablehnung ohne Token geprüft.
Kein echter Geräteschreibauftrag wurde ausgeführt. Die Contao-Schemaaktualisierung
und Aktivierung des Inhaltselements gehören zur jeweiligen Contao-Installation.
