<?php

declare(strict_types=1);

namespace PbdKn\ContaoContaohabBundle\Service;

/** Erklärungen für die Anzeige; erteilt keine Schreibfreigaben. */
final class ModbusSettingDescriptions
{
    public static function describe(string $setting): string
    {
        $key = preg_replace('/^(control|planned)\./', '', $setting);
        $descriptions = [
            'appMode' => 'Bestimmt die Betriebsstrategie des Wechselrichters: Eigenverbrauch nutzt die Batterie für den eigenen Verbrauch; Backup ist der Reservebetrieb; Passiv verwendet externe Lade-/Entladevorgaben. Ein Moduswechsel kann das aktuelle Ladeverhalten ändern. Er setzt keine Leistungsvorgaben.',
            'passiveAction' => 'Wählt im bereits aktiven passiven Betriebsmodus zwischen Bereitschaft, Entladen und Laden. Bereitschaft hebt die aktive passive Lade-/Entladeanforderung auf. Die tatsächlich erreichbare Leistung hängt von den Leistungsvorgaben, dem Ladezustand und den Schutzgrenzen des Batteriemanagements ab. Die Einstellung schaltet den Betriebsmodus nicht automatisch um.',
            'onGridSoc' => 'Legt die untere Ladezustandsgrenze für die Batterieentladung bei vorhandenem Stromnetz fest. Ein höherer Grenzwert hält mehr Energie in der Batterie zurück; ein niedrigerer erlaubt eine stärkere Entladung. Die Beziehung zur Ladeobergrenze und zur Inselbetrieb-Grenze muss berücksichtigt werden. Schutzgrenzen des Batteriemanagements gelten weiterhin.',
            'offGridSoc' => 'Legt die untere Ladezustandsgrenze der Batterie im Inselbetrieb ohne verfügbares Stromnetz fest. Ein höherer Grenzwert lässt eine größere Restladung zurück. Unterhalb der Grenze kann die Versorgung aus der Batterie eingeschränkt werden. Die Grenze muss zur Netzbetrieb-Grenze und zur installierten Gerätefirmware passen.',
            'chargeUpperSoc' => 'Begrenzt den Ladezustand, bis zu dem die Batterie geladen werden darf. Ein niedrigerer Wert verringert die maximal gespeicherte Energie. Ein höherer Wert erlaubt mehr Ladung, erzwingt aber keinen Ladevorgang. Die Ladeobergrenze muss ausreichend oberhalb der Entladegrenzen liegen.',
            'passiveGridChargePower' => 'Gibt die Netz-Ladeleistung im passiven Betrieb relativ zur Nennleistung vor. Es handelt sich nicht unmittelbar um Watt. Die Vorgabe wirkt zusammen mit passiver Ladeanforderung, Batteriegrenzen und Netz-Ladegrenzen; sie allein startet keine Ladung. Die Skalierung und Gerätegrenzen sind vor Schreibfreigabe noch zu bestätigen.',
            'passiveGridDischargePower' => 'Gibt die Netz-Entladeleistung im passiven Betrieb relativ zur Nennleistung vor. Die Wirkung hängt von der aktiven Entladeanforderung und den Netz-/Batteriegrenzen ab. Ein höherer Wert bedeutet nicht, dass die Leistung unter allen Bedingungen erreicht wird. Skalierung und zulässige Werte sind geräteabhängig zu prüfen.',
            'passiveBatteryChargePower' => 'Gibt die Batterie-Ladeleistung im passiven Betrieb relativ zur Nennleistung vor. Verfügbare Energie, Ladezustand und Batteriemanagement begrenzen die tatsächliche Leistung zusätzlich. Die Vorgabe allein startet keine Ladung. Eine Wattumrechnung ist ohne bestätigte Nennleistung und Skalierung nicht verlässlich.',
            'passiveBatteryDischargePower' => 'Gibt die Batterie-Entladeleistung im passiven Betrieb relativ zur Nennleistung vor. Die aktive Entladeanforderung, Ladezustandsgrenzen und das Batteriemanagement bestimmen, ob diese Leistung tatsächlich abgegeben werden kann. Die Vorgabe allein startet keine Entladung.',
            'batteryChargePowerLimit' => 'Begrenzt die erlaubte Ladeleistung der Batterie relativ zur Nennleistung. Ein niedrigerer Wert begrenzt die Ladegeschwindigkeit. Die Einstellung erzwingt keine Ladung; die tatsächliche Leistung hängt außerdem vom Betriebsmodus, der verfügbaren Energie und den Grenzen des Batteriemanagements ab. Skalierung und Maximalwert müssen zur Gerätefirmware passen.',
            'batteryDischargePowerLimit' => 'Begrenzt die erlaubte Entladeleistung der Batterie relativ zur Nennleistung. Ein niedrigerer Wert begrenzt, wie schnell die Batterie Energie abgeben darf. Verbrauch, Betriebsmodus, Ladezustand und Schutzgrenzen bestimmen die tatsächliche Entladung. Die Einstellung erzwingt keine Entladung.',
            'gridChargePowerLimit' => 'Begrenzt die zum Laden zugelassene Leistungsaufnahme aus dem Netz relativ zur Nennleistung. Ein niedrigerer Grenzwert begrenzt die mögliche Netzladung. Er aktiviert die Netzladung nicht selbst. Andere Leistungs- und Batteriegrenzen können die tatsächlich aufgenommene Leistung weiter reduzieren.',
            'gridDischargePowerLimit' => 'Begrenzt die zulässige Leistungsabgabe an das Netz relativ zur Nennleistung. Der Grenzwert erzeugt keine Einspeisung und ersetzt nicht automatisch die separate Einspeisebegrenzung. Die tatsächliche Abgabe hängt von verfügbarer Erzeugung, Batterie und Betriebsmodus ab.',
            'exportLimit' => 'Legt den Wert der Einspeisebegrenzung fest. Laut Registerbeschreibung wirkt er nur bei passendem Begrenzungsmodus in Register 0x365C: Modus 1 verwendet eine Gesamtbegrenzung, Modus 3 eine Begrenzung pro Phase. Die Wirkung und Skalierung müssen vor einer Änderung für das konkrete Gerät bestätigt werden.',
        ];
        if (isset($descriptions[$key])) return $descriptions[$key];
        return 'Für diese Einstellung liegt noch keine verifizierte Wirkungsbeschreibung vor.';
    }
}
