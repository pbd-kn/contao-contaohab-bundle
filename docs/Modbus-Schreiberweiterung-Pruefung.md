# Zusätzliche Modbus-Einstellungen zur Freigabeprüfung

Stand: 01.11.2026. Die neue Anzeige enthält **84 zusätzliche Informationsfelder**.
Die ausführbare Allowlist bleibt bei den zwei vorhandenen Einstellungen.
Auswahl eines Informationsfelds zeigt Register und dokumentierten Bereich,
erzeugt aber weder einen Lese- noch einen Schreibauftrag am Gerät.

Die Katalogerweiterung wurde auf dem Raspberry installiert. Vorherige Dateien
liegen unter `/home/peter/scripts/coh/modbus-catalog-backup-20261101-105111/`.
Der authentifizierte HTTP-Katalog wurde für beide Profile geprüft: jeweils
2 bestehende Schreibfreigaben und 84 gesperrte Informationsfelder. Collector nach
Neustart aktiv. Kein echter Modbus-Schreibauftrag wurde ausgeführt.

Die automatische Sicherheitsprüfung hat den Versuch, alle zusätzlichen Felder
direkt zum Schreiben freizugeben, wegen der noch nicht ausreichend verifizierten
Codierungen/Adresszuordnungen und möglicher Auswirkungen auf Ladeverhalten und
Leistungsgrenzen abgelehnt. Der Informationskatalog ist keine Umgehung dieser
Sperre: Schreibzugriffe auf seine `planned.*`-Kennungen werden in Contao sowie
in der Raspberry-API und im Modbus-Control-Service abgewiesen.

## Prüfbarer Erweiterungsumfang

| Gruppe | Register | Dokumentierter Umfang | Vor Schreibfreigabe erforderlich |
|---|---|---|---|
| Batteriegrenzen | `0x3644–0x3646` | Entladegrenzen Netz-/Inselbetrieb, Ladeobergrenze; 0–100 % | Abstände und Abhängigkeiten anhand der installierten Firmware bestätigen und serverseitig prüfen |
| Passive Leistung | `0x3637–0x363A` | Netz/Batterie, Laden/Entladen | Referenz erlaubt Rohwerte 0–1100; Nennleistungsbezug und Skalierung verifizieren, kein ungeprüftes Wattfeld |
| Leistungsgrenzen | `0x364D–0x3650` | Batterie laden/entladen, Netzbezug/Einspeisung | Wie passive Leistung; modellabhängige Grenzen prüfen |
| Einspeisebegrenzung | `0x365A` | Relativer Grenzwert | Zusätzlich aktuellen Modus `0x365C` (1 oder 3) und dessen Wirkung prüfen |
| Zeitverhalten | `0x365F`, `0x3660` | Referenzintegration erlaubt 0/1 | Bedeutungen pro Firmware klären; derzeit keine erfundenen Klartextoptionen |
| Lade-/Entladefenster | `0x3604–0x362F` | Je sieben Fenster; Aktivierung, Beginn, Ende, Wochentage, Leistung | Einzelne Bits/Bytes erhalten, Ausgangsregister frisch vergleichen, Grenzen validieren, keine automatische Aktivierung oder Modusumschaltung |

Zeitcodierung laut Referenz: Stunde im oberen Byte, Minute im unteren Byte.
Wochentage und Leistungsanteil teilen sich jeweils ein Register. Eine Änderung
muss den jeweils anderen Anteil unverändert erhalten. Die Aktivierungsregister
enthalten je ein Bit pro Fenster. Ein späterer Schreibdialog muss deshalb auch
bei Änderung eines einzelnen Teilfelds das gesamte ursprüngliche Register gegen
Konflikte prüfen. Ein Auftrag darf nur die ausdrücklich bestätigte Änderung
ausführen und nach Timeout nicht automatisch wiederholt werden.

## Quellen und Grenze der Aussage

- Lokale Registerbeschreibung: `COH-Sensorcollector/execScripts/WechelrichterRegister.txt`.
- [Registerübersicht der Referenzintegration](https://github.com/stanus74/home-assistant-saj-h2-modbus/blob/main/register_overview.md).
- [Eingabebereiche](https://github.com/stanus74/home-assistant-saj-h2-modbus/blob/main/custom_components/saj_h2_modbus/number.py).
- [Adressen und Registerpackung](https://github.com/stanus74/home-assistant-saj-h2-modbus/blob/main/custom_components/saj_h2_modbus/charge_control.py).

Die Referenz ist Implementierungscode einer Home-Assistant-Integration, keine
Herstellerbestätigung für jede AMPERE-/SAJ-Firmware. Die Quelle erlaubt bei
Leistungsvorgaben 0–1100; daraus allein folgt noch keine sichere Wattumrechnung
oder zulässige maximale Geräteleistung.

Die neue Metadatendatei `sensorCollect/Modbus/Profiles/saj-h2-control-catalog.php`
wird von beiden Profilen genutzt. Sie enthält keine ausführbaren Schreibadressen
und wird nicht von `ModbusControl::execute()` ausgewertet. Die Modbus-Transport-
und Schreibimplementierung wurde bei dieser Erweiterung nicht verändert.
