<?php

namespace PbdKn\ContaoContaohabBundle\Controller;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\DBAL\Connection;

use PbdKn\ContaoContaohabBundle\Service\LoggerService;

class COHGetValuesController extends AbstractController
{

    private ContaoFramework $framework;
    private Connection $connection; 
    private HttpClientInterface $httpClient;
    private ?LoggerService $logger = null;
        
    public function __construct(ContaoFramework $framework,HttpClientInterface $httpClient, Connection $connection,LoggerService $logger)
    {
        $this->framework = $framework;
        $this->connection = $connection;
        $this->httpClient = $httpClient;
        $this->logger = $logger;        
    }
    #[Route('/COH/getSensoreValues/{aktion}/{ID}', name: 'coh_data_getSensorValues', methods: ['GET'])]
public function getSensoreValues(Request $request, string $aktion, string $ID = '-1'): JsonResponse
{
    $range = $request->query->get('range', '1d');
    $since = match ($range) {
        '1w' => strtotime('-1 week'),
        '1m' => strtotime('-1 month'),
        default => strtotime('-1 day')
    };

    $stmt = $this->connection->executeQuery(
        'SELECT tstamp, sensorID, sensorValue FROM tl_coh_sensorvalue WHERE tstamp >= ? ORDER BY tstamp ASC',
        [$since]
    );

    $rows = $stmt->fetchAllAssociative();

    $datasets = [];
    $allTimestamps = [];

    foreach ($rows as $row) {
        $timestamp = date('c', $row['tstamp']);
        $sensorId = $row['sensorID'];
        $value = (float)$row['sensorValue'];

        // Sammle Labels für die X-Achse
        $allTimestamps[] = $timestamp;

        // Erzeuge falls nötig neuen Dataset für diesen Sensor
        if (!isset($datasets[$sensorId])) {
            $datasets[$sensorId] = [
                'label' => 'Sensor ' . $sensorId,
                'data' => [],
                'borderColor' => $this->getSensorColor($sensorId),
                'fill' => false,
                'tension' => 0.1,
            ];
        }

        // Füge Messpunkt hinzu
        $datasets[$sensorId]['data'][] = ['x' => $timestamp, 'y' => $value];
    }

    // Duplikate in Labels entfernen und sortieren
    $labels = array_values(array_unique($allTimestamps));
    sort($labels);

    return new JsonResponse([
        'labels' => $labels,
        'datasets' => array_values($datasets)
    ]);
}
private function getSensorColor(int|string $id): string
{
    $colors = ['#60A5FA', '#F87171', '#34D399', '#FBBF24', '#A78BFA', '#F472B6'];
$idNumeric = is_numeric($id) ? (int)$id : crc32($id);
return $colors[$idNumeric % count($colors)];
}


    #[Route('/COH/getSensoreValuesTest/{aktion}/{ID}', name: 'coh_data_getSensorValuesTest', methods: ['GET'])]
    public function getSensoreValuesTest(string $aktion, string $ID = '-1'): JsonResponse
    {
        if (!ctype_digit($ID)) {
            return new JsonResponse([
                'error' => 'Ungültige ID: Muss eine ganze Zahl sein.'
            ], 400); // 400 = Bad Request
        }

        $idInt = (int)$ID;

        return new JsonResponse([
            'aktion' => $aktion,
            'id' => $idInt,
            'status' => 'OK'
        ]);
    }
} // 👈 diese Klammer war bei dir gefehlt!
