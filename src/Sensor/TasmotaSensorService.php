<?php

namespace PbdKn\ContaoContaohabBundle\Sensor;

use PbdKn\ContaoContaohabBundle\Model\SensorModel;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Doctrine\DBAL\Connection;
//use Psr\Log\LoggerInterface;

class TasmotaSensorService implements SensorFetcherInterface
{
    private HttpClientInterface $httpClient;
//    private LoggerInterface $logger;
    private Connection $connection;

    public function __construct(HttpClientInterface $httpClient, Connection $connection)
    {
        $this->httpClient = $httpClient;
//        $this->logger = $logger;
        $this->connection = $connection;
    }

    public function supports(SensorModel $sensor): bool
    {
        return (int)$sensor->sensorSource === 2;
    }

    public function fetch(SensorModel $sensor): ?array
    {
        try {
            $url = $sensor->sensorReferenz;

            if (!$url) {
                $message = "Tasmota: sensorReferenz fehlt bei Sensor {$sensor->sensorID}";
//                $this->logger->warning($message);
                $this->connection->update('tl_coh_sensors', [
                    'lastError' => $message
                ], ['id' => $sensor->id]);

                return null;
            }

            $response = $this->httpClient->request('GET', $url);
            $data = $response->toArray();

            $value = $data['StatusSNS']['ENERGY']['Power'] ?? null;

            if ($value === null) {
                $message = "Tasmota: Kein Power-Wert gefunden für Sensor {$sensor->sensorID}";
//                $this->logger->warning($message);
                $this->connection->update('tl_coh_sensors', [
                    'lastError' => $message
                ], ['id' => $sensor->id]);

                return null;
            }

            // ? Erfolg: Log + Datenbank-Update
//            $this->logger->info("Tasmota: Sensor {$sensor->sensorID} liefert {$value} W");

            $this->connection->update('tl_coh_sensors', [
                'lastUpdated' => time(),
                'lastValue' => $value,
                'lastError' => '',
            ], ['id' => $sensor->id]);

            return [
                'sensorID'        => $sensor->sensorID,
                'sensorValue'     => $value,
                'sensorEinheit'   => $sensor->sensorEinheit,
                'sensorValueType' => $sensor->sensorValueType,
                'sensorSource'    => $sensor->sensorSource,
            ];
        } catch (\Throwable $e) {
            $message = "Tasmota: Fehler bei {$sensor->sensorID}: " . $e->getMessage();
//            $this->logger->error($message);

            $this->connection->update('tl_coh_sensors', [
                'lastError' => $e->getMessage()
            ], ['id' => $sensor->id]);

            return null;
        }
    }
}
