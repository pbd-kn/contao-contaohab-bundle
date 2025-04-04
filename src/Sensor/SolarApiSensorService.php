<?php

namespace PbdKn\ContaoContaohabBundle\Sensor;

use PbdKn\ContaoContaohabBundle\Model\SensorModel;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Doctrine\DBAL\Connection;
use PbdKn\ContaoContaohabBundle\Service\LoggerService;


class SolarApiSensorService implements SensorFetcherInterface
{
    private HttpClientInterface $httpClient;
    private ?LoggerService $logger = null;
    private Connection $connection;

    public function __construct(HttpClientInterface $httpClient, Connection $connection,LoggerService $logger)
    {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->connection = $connection;
    }

    public function supports(SensorModel $sensor): bool
    {
        return strtolower($sensor->sensorSource) === 'iqbox';
    }

    public function fetch(SensorModel $sensor): ?array
    {
        try {
            $url = $sensor->sensorReferenz;

            if (!$url) {
                $message = "SolarAPI: sensorReferenz fehlt bei Sensor {$sensor->sensorID}";
                $this->logger->debugMe($message);
/*
                $this->connection->update('tl_coh_sensors', [
                    'lastError' => $message
                ], ['id' => $sensor->id]);
*/
                return null;
            }

            $response = $this->httpClient->request('GET', $url);
            $data = $response->toArray();

            $key = $this->mapTransform($sensor->transFormProcedur, $data);

            if ($key === null || !isset($data[$key])) {
                $message = "SolarAPI: Kein passender Wert für '{$sensor->transFormProcedur}' bei Sensor {$sensor->sensorID}";
                $this->logger->debugMe($message);
/*
                $this->connection->update('tl_coh_sensors', [
                    'lastError' => $message
                ], ['id' => $sensor->id]);
*/
                return null;
            }

            $value = $data[$key];

            $this->logger->debugMe("SolarApi: Sensor {$sensor->sensorID} liefert {$value} W");    
/*
            $this->connection->update('tl_coh_sensors', [
                'lastUpdated' => time(),
                'lastValue'   => $value,
                'lastError'   => '',
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
            $message = "SolarAPI: Fehler bei Sensor {$sensor->sensorID}: " . $e->getMessage();
           $this->logger->debugMe($message);
/*
            $this->connection->update('tl_coh_sensors', [
                'lastError' => $e->getMessage()
            ], ['id' => $sensor->id]);
*/
            return null;
        }
    }

    /**
     * Zuordnung transFormProcedur ? Schlüssel im API-JSON
     */
    private function mapTransform(?string $procedure, array $data): ?string
    {
        return match ($procedure) {
            'IQkWh'   => 'total_power',
            'IQkW'    => 'current_power',
            'IQTemp'  => 'temperature',
            'IQSOC'   => 'soc',
            default   => null,
        };
    }
}
