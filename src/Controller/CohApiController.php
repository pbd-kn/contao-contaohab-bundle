<?php

namespace PbdKn\ContaoContaohabBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Contao\Database;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CohApiController extends AbstractController
{
    #[Route('/api/coh_items', name: 'api_coh_items_hallo', methods: ['GET'])]
    public function fetchItems(): JsonResponse
    {
        $db = Database::getInstance();
        $result = $db->execute("SELECT item_name, count FROM tl_coh_items ORDER BY item_name ASC");

        $data = [];
        while ($result->next()) {
            $data[] = ['item_name' => $result->item_name, 'count' => $result->count];
        }

        return new JsonResponse($data);
    }

    #[Route('/api/coh_update_interval', name: 'api_coh_update_interval', methods: ['GET'])]
    public function fetchUpdateInterval(): JsonResponse
    {
        return new JsonResponse(['interval' => 5000]);
    }
}
