<?php

namespace PbdKn\ContaoContaohabBundle\Sensor;

use PbdKn\ContaoContaohabBundle\Model\SensorModel;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Doctrine\DBAL\Connection;
use PbdKn\ContaoContaohabBundle\Service\LoggerService;

class TasmotaSensorService implements SensorFetcherInterface
{
    private HttpClientInterface $httpClient;
    private Connection $connection;
    private ?LoggerService $logger = null;

    public function __construct(HttpClientInterface $httpClient, Connection $connection,LoggerService $logger)
    {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->connection = $connection;
    }

    public function supports(SensorModel $sensor): bool
    {
        return strtolower($sensor->sensorSource) === 'tasmota';
    }

    public function fetch(SensorModel $sensor): ?array
    {
        try {
            $url = $sensor->sensorReferenz;
            $this->logger->debugMe('Tasmota Sensorservice url '.$url);    

            if (!$url) {
                $message = "Tasmota: sensorReferenz fehlt bei Sensor {$sensor->sensorID}";
/*
                $this->connection->update('tl_coh_sensors', [
                    'lastError' => $message
                ], ['id' => $sensor->id]);
*/
                return null;
            }

            $response = $this->httpClient->request('GET', $url);
            $data = $response->toArray();

            $value = $data['StatusSNS']['ENERGY']['Power'] ?? null;

            if ($value === null) {
                $message = "Tasmota: Kein Power-Wert gefunden für Sensor {$sensor->sensorID}";
                $this->logger->debugMe($message);    
/*
                $this->connection->update('tl_coh_sensors', [
                    'lastError' => $message
                ], ['id' => $sensor->id]);
*/
                return null;
            }

            // ? Erfolg: Log + Datenbank-Update
            $this->logger->debugMe("Tasmota: Sensor {$sensor->sensorID} liefert {$value} W");    
/*
            $this->connection->update('tl_coh_sensors', [
                'lastUpdated' => time(),
                'lastValue' => $value,
                'lastError' => '',
            ], ['id' => $sensor->id]);
*/
            return [
                'sensorID'        => $sensor->sensorID,
                'sensorValue'     => $value,
                'sensorEinheit'   => $sensor->sensorEinheit,
                'sensorValueType' => $sensor->sensorValueType,
                'sensorSource'    => $sensor->sensorSource,
            ];
        } catch (\Throwable $e) {
            $message = "Tasmota: Fehler bei {$sensor->sensorID}: " . $e->getMessage();
            $this->logger->debugMe($message);    
/*
            $this->connection->update('tl_coh_sensors', [
                'lastError' => $e->getMessage()
            ], ['id' => $sensor->id]);
*/
            return null;
        }
    }
}
