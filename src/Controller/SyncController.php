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

        $error = $this->syncService->sync();

        if ($error !== null) {
            $this->logger->Error('SyncController Fehler: '.$error);
            return new Response($error, 500);
        }

        return new Response('OK');
    }
}
