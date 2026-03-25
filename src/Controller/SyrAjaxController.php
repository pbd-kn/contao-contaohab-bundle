<?php

namespace PbdKn\ContaoContaohabBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class SyrAjaxController
{
    private string $baseSet = "http://192.168.178.65:5333/trio/set/";
    private string $baseGet = "http://192.168.178.65:5333/trio/get/";

    #[Route('/api/syr', name: 'coh_syr_ajax', methods: ['GET','POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        // ---------------------------------------------------
        // ?? TOKEN CHECK
        // ---------------------------------------------------
        if ($request->get('token') !== 'COH_CODE') {
            return new JsonResponse(['success'=>false], 403);
        }

        $action = $request->get('action');

        // ---------------------------------------------------
        // ?? VENTIL STEUERUNG MIT WARTEN
        // ---------------------------------------------------
        if ($action === 'open' || $action === 'close') {

            $target = null;

            if ($action === 'close') {
                @file_get_contents($this->baseSet . "ab/true");
                $target = 10; // ZU
            }

            if ($action === 'open') {
                @file_get_contents($this->baseSet . "ab/false");
                $target = 20; // OFFEN
            }

            $maxSeconds = 50;
            $start = time();
            $vlvNow = null;

            while (true) {

                sleep(1);

                $vlvNow = $this->syrGet("vlv");

                if ($vlvNow == $target) break;
                if ($vlvNow === null) break;
                if ((time() - $start) >= $maxSeconds) break;
            }

            return new JsonResponse([
                'success' => ($vlvNow == $target),
                'action'  => $action,
                'vlv'     => $vlvNow,
                'target'  => $target,
                'time'    => time() - $start
            ]);
        }

        // ---------------------------------------------------
        // ?? PROFIL SETZEN
        // ---------------------------------------------------
        if ($action === 'setProfile') {
            $profile = (int)$request->get('profile');

            if ($profile >= 1 && $profile <= 8) {
                @file_get_contents($this->baseSet . "prf/" . $profile);

                return new JsonResponse([
                    'success' => true,
                    'profile' => $profile
                ]);
            }
        }

        return new JsonResponse([
            'success' => false,
            'error'   => 'unknown action'
        ]);
    }

    // ---------------------------------------------------
    // ?? GET HELPER (deine Funktion sauber integriert)
    // ---------------------------------------------------
    private function syrGet(string $cmd)
    {
        $ctx = stream_context_create([
            'http' => ['timeout' => 2]
        ]);

        $json = @file_get_contents($this->baseGet . strtolower($cmd), false, $ctx);

        if (!$json) return null;

        $data = json_decode($json, true);

        if (!is_array($data) || empty($data)) return null;

        return array_values($data)[0] ?? null;
    }
}