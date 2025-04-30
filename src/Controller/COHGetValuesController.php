<?php

namespace PbdKn\ContaoContaohabBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\DBAL\Connection;
use Symfony\Component\Routing\Annotation\Route;

class COHGetValuesController extends AbstractController
{
    //#[Route('/{id}', name: 'coh_canvas_data', methods: ['GET'])]
    #[Route('/COH/coh-canvas-data/{id}', name: 'coh_data_invoke', methods: ['GET'])]
    public function __invoke(Request $request, int $id, Connection $connection): JsonResponse
    {
        $range = $request->query->get('range', '1d');
        $since = match ($range) {
            '1w' => strtotime('-1 week'),
            '1m' => strtotime('-1 month'),
            default => strtotime('-1 day')
        };

        $stmt = $connection->executeQuery(
            'SELECT timestamp, value FROM tl_coh_sensordata WHERE tstamp >= ? ORDER BY tstamp ASC',
            [$since]
        );

        $rows = $stmt->fetchAllAssociative();
        $labels = [];
        $data = [];

        foreach ($rows as $row) {
            $labels[] = date('c', $row['timestamp']);
            $data[] = (float) $row['value'];
        }

        return new JsonResponse([
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Sensorwert',
                'data' => array_map(fn($ts, $val) => ['x' => $ts, 'y' => $val], $labels, $data),
                'borderColor' => '#3b82f6',
                'fill' => false,
                'tension' => 0.1,
            ]]
        ]);
    }

    #[Route('/COH/getSensoreValues/{aktion}/{ID}', name: 'coh_data_getSensorValues', methods: ['GET'])]
    public function getSensorValues(string $aktion, string $ID = '-1'): JsonResponse
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
