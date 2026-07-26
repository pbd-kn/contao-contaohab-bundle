<?php

namespace PbdKn\ContaoContaohabBundle\Service\Sensors;

use PbdKn\ContaoContaohabBundle\Model\SensorModel;
use PbdKn\ContaoContaohabBundle\Service\LoggerService;

/* implementiert die Heizstab Zugriffe */

class HeizstabSensorService implements SensorFetcherInterface
{
    private ?LoggerService $logger = null;
    private ?array $aktData  = null;
    private ?array $setupData  = null;
    

    public function __construct(LoggerService $logger, private readonly string $paramsFile)
    {
        $this->logger = $logger;
    }

    public function supports(SensorModel $sensor): bool
    {
        return strtolower($sensor->sensorSource) === 'heizstab';
    }

    public function fetch(SensorModel $sensor): ?array
    {
        return $this->fetchArr([$sensor])[(string) $sensor->sensorID] ?? null;

        /* Alter Einzelabruf bleibt vorlaeufig als Referenz unerreichbar. */
        $res=array();
        try {
            $this->logger->debugMe('Heizstab Sensorservice sensorID: '.$sensor->sensorID.' Cloud-Parameter '.$this->paramsFile);
            if ($this->getDataFromDevice() === null) {
                return null;
            }
            
            $lokalAccess=$sensor->sensorID;
            if (!empty($sensor->sensorLokalId)) $lokalAccess=$sensor->sensorLokalId;
            $value = $this->getHeizstabdata[$lokalAccess];

            if ($value === null) {
                $message = "Heizstab: Keinen  fÃ¼r Sensor {$sensor->sensorID} access: getHeizstabdata[$lokalAccess]";
                $this->logger->debugMe($message);    

                return null;
            }

            // ? Erfolg: Log + Datenbank-Update
            $this->logger->debugMe("Heizstab: Sensor {$sensor->sensorID} liefert {$value} W");    

            $value = $this->getHeizstabdata[$sensor->sensorID];
            $res[]= [
                'sensorID'        => $sensor->sensorID,
                'sensorValue'     => $value,
                'sensorEinheit'   => $sensor->sensorEinheit,
                'sensorValueType' => $sensor->sensorValueType,
                'sensorSource'    => $sensor->sensorSource,
            ];
        } catch (\Throwable $e) {
            $message = "Heizstab: Fehler bei {$sensor->sensorID}: " . $e->getMessage();
            $this->logger->debugMe($message);    

            return null;
        }
        return $res;
    }
    public function fetchArr(array $sensors, ?string $date = null, array $fetchedValues = []): ?array
    {   
        $res=array();
        try {
            if (count($sensors) === 0) {
                return null;
            }
            $this->logger->debugMe('Heizstab Sensorservice Cloud-Parameter '.$this->paramsFile.' len sensors:'.count($sensors));
            if ($this->getDataFromDevice() === null) {
                $this->logger->debugMe('getDataFromDevice null');
                return null;
            }
//            $this->logger->debugMe('Heizstab Sensorservice vor schleife count:  '.count($sensors));    
            // Zugriff auf Werte, z.B.:
            foreach ($sensors as $sensor) {
                $lokalAccess=$sensor->sensorID;
                if (!empty($sensor->sensorLokalId)) $lokalAccess=$sensor->sensorLokalId;
                $value = $this->getHeizstabdata($lokalAccess);
                $einheit=$sensor->sensorEinheit;  
                $transform = trim((string) $sensor->transFormProcedur);
                if ($transform !== '' && $transform !== '-') {
                 if (method_exists($this, $transform)) {
                        $arr = $this->{$transform}($value);
                        $einheit=$arr['einheit'];                    
                        $value=$arr['wert'];
                    } else {
                        $this->logger->Error("Heizstab transFormProcedur '$transform' fuer SensorID '{$sensor->sensorID}' existiert nicht");
                    }                 
                }                   
                $this->logger->debugMe("Heizstab Sensorservice SensorID  '.$sensor->sensorID.' lokalAccess $lokalAccess value $value Einheit $einheit");  
                if ($value === null) {
                    $this->logger->Error("Heizstabwert '$lokalAccess' fuer SensorID '{$sensor->sensorID}' wurde weder in data noch setup gefunden.");
                } else {    
                    $res[$sensor->sensorID] = [
                        'sensorID'        => $sensor->sensorID,
                        'sensorValue'     => $value,
                        'sensorEinheit'   => $einheit,
                        'sensorValueType' => $sensor->sensorValueType,
                        'sensorSource'    => $sensor->sensorSource,
                    ];
                }
            }

            return $res;
        } catch (\Throwable $e) {
            $message = "Heizstab: Fehler bei : " . $e->getMessage();
            $this->logger->Error($message);    

            return null;
        }
        return $res;
    }
    private function getDataFromDevice() {
        try {
            if (!is_file($this->paramsFile)) {
                throw new \RuntimeException("Heizstab-Parameterdatei fehlt: {$this->paramsFile}");
            }
            $parameters = TaskAccess::loadParameters($this->paramsFile);
            $apiConfig = is_array($parameters['heizstabApi'] ?? null) ? $parameters['heizstabApi'] : [];
            if (empty($apiConfig['enabled'])) {
                throw new \RuntimeException('heizstabApi ist in der Parameterdatei nicht aktiviert.');
            }
            $cloud = TaskAccess::heizstabCloud($parameters, TaskAccess::loggerAdapter($this->logger));
            $this->aktData = $cloud->data();
            $setupResponse = $cloud->setup();
            $this->setupData = is_array($setupResponse['setup'] ?? null)
                ? $setupResponse['setup']
                : $setupResponse;
        } catch (\Throwable $e) {
            $this->logger->Error('Heizstab: Cloud-Zugriff fehlgeschlagen: '.$e->getMessage());
            return null;
        }
        return "OK";
    }
    // liest die data.jsn vom Heizstab und gibt sie als Array zurÃ¼ck
    // liefert False bei einem Fehler
    // liest die setup.jsn vom Heizstab und gibt sie als Array zurÃ¼ck
    // liefert False bei einem Fehler

    /*  liefert den wert vom Heizstab aus global $aktData,$setupData;
     *  
     */
    private function getHeizstabdata ($sensorID) {
        if (isset($this->aktData[$sensorID]) )  {  
          return $this->aktData[$sensorID];
        } else if (isset($this->setupData[$sensorID]) )  {  
          return $this->setupData[$sensorID];
        } else {
          return null;
        }
    } 
    /*
     *  Routinen zur Anpassung des Values wird in der Konfig des Servers unter transFormProcedur angegheben
     */
     
    private function elwaPwrkWh($stat) {   // Power akt Heizstab
        $resArr['wert'] = round($stat/1000,2);
        $resArr['einheit']='kWh';
        return $resArr;
    }
    private function elwaPwr($stat) {   // max Power in %
        $resArr['wert'] = $stat;
        $resArr['einheit']='%';
        return $resArr;
    }
    private function elwaTemp($stat) {   // temperatur
        $resArr['wert'] = round($stat/10,2);
        $resArr['einheit']='°C';
        return $resArr;
    }

    private function elwaProt($stat) {   // Protokoll
        $resArr['wert'] = $stat;
        switch ($stat) {
            case 0: case 0: $v='Auto Detec';break;
            case 1: $v='HTTP';break; 
            case 2: $v='Modbus TCP';break; 
            default: $v='Protokoll undefinioert';break;
/*
    case 3: $v='Fronius Auto';break; 
    case 4: $v='Fronius Manual';break; 
    case 5: $v='SMA Home Manager';break; 
    case 6: $v='Steca Auto';break; 
    case 7: $v='Varta Auto';break; 
    case 8: $v='Varta Manual';break; 
    case 12: $v='my-PV Meter Auto';break; 
    case 12: $v='my-PV Meter Manual';break; 
    case 14: $v='my-PV Power Meter Direct';break; 
    case 10: $v='RCT Power Manual';break; 
    case 15: $v='SMA Direct meter communication Auto';break; 
    case 16: $v='SMA Direct meter communication Manual';break; 
    case 19: $v='Digital Meter P1';break; 
    case 20: $v='Frequency';break; 
    case 100: $v='Fronius Sunspec Manual';break; 
    case 102: $v='Kostal PIKO IQ Plenticore plus Manual';break; 
    case 103: $v='Kostal Smart Energy Meter Manual';break; 
    case 104: $v='MEC electronics Manual';break; 
    case 105: $v='SolarEdge Manual';break; 
    case 106: $v='Victron Energy 1ph Manual';break; 
    case 107: $v='Victron Energy 3ph Manual';break; 
    case 108: $v='Huawei (Modbus TCP) Manual';break; 
    case 109: $v='Carlo Gavazzi EM24 Manual';break; 
    case 111: $v='Sungrow Manual';break; 
    case 112: $v='Fronius Gen24 Manual';break; 
    case 200: $v='Huawei (Modbus RTU)';break;   
    case 201: $v='Growatt (Modbus RTU)';break; 
    case 202: $v='Solax (Modbus RTU)';break; 
    case 203: $v='Qcells (Modbus RTU)';break; 
    case 204: $v='IME Conto D4 Modbus MID (Modbus RTU)';break; 
*/
        }
        $resArr['einheit']=$v;
        return $resArr;
    }
}

