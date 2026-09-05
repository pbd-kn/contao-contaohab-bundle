<?php

namespace PbdKn\ContaoContaohabBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use PbdKn\ContaoContaohabBundle\Service\SyncService;
use PbdKn\ContaoContaohabBundle\Service\LoggerService;

class SyncController extends AbstractController
{
    public function __construct(
        private readonly SyncService $syncService,
        private readonly LoggerService $logger
    ) {}

    #[Route('/coh/sync', name: 'coh_sync', methods: ['POST'])]
    public function sync(Request $request): Response
    {
        $this->logger->debugMe('SyncController: manueller Sync gestartet');

        $result = $this->syncService->sync();

        if (($result['status'] ?? 'NOK') !== 'OK') {
            $message = (string) ($result['msg'] ?? 'Synchronisation fehlgeschlagen.');
            $this->logger->Error('SyncController Fehler: '.$message);

            return new Response($message, 410);
        }

        return new Response('OK');
    }
}
