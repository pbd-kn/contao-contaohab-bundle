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
            return new JsonResponse(['success'=>false, 'error'=>'unauthorized'], 403);
        }

        $action = $request->get('action');

        // ===================================================
        // ?? VENTIL STEUERUNG (MIT WAIT)
        // ===================================================
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

        // ===================================================
        // ?? PROFIL SETZEN (WICHTIG: PAx + PRF !!!)
        // ===================================================
        if ($action === 'setProfile') {

            $profile = (int)$request->get('profile');

            if ($profile >= 1 && $profile <= 8) {

                // zuerst aktivieren
                @file_get_contents($this->baseSet . "pa" . $profile . "/true");

                // dann setzen
                @file_get_contents($this->baseSet . "prf/" . $profile);

                return new JsonResponse([
                    'success' => true,
                    'profile' => $profile
                ]);
            }

            return new JsonResponse(['success'=>false]);
        }

        // ===================================================
        // ?? GENERIC SET (pv, pt, pf, pm, drp, dtt, dex)
        // ===================================================
        if ($action === 'setValue') {

            $type    = $request->get('type');
            $value   = $request->get('value');
            $profile = (int)$request->get('profile');

            if ($profile < 1 || $profile > 8) {
                return new JsonResponse(['success'=>false, 'error'=>'invalid profile']);
            }

            // -----------------------------------
            // PROFILWERTE (pv, pt, pf)
            // -----------------------------------
            if (in_array($type, ['pv','pt','pf'], true)) {

                $v = (int)$value;

                @file_get_contents(
                    $this->baseSet . $type . $profile . "/" . $v
                );

                return new JsonResponse(['success'=>true]);
            }

            // -----------------------------------
            // MIKROLECKAGE AN/AUS
            // -----------------------------------
            if ($type === 'pm') {

                $v = ((int)$value === 1) ? 'true' : 'false';

                @file_get_contents(
                    $this->baseSet . "pm" . $profile . "/" . $v
                );

                return new JsonResponse(['success'=>true]);
            }

            // -----------------------------------
            // INTERVALL (1-3)
            // -----------------------------------
            if ($type === 'drp') {

                $v = (int)$value;

                if ($v >= 1 && $v <= 3) {
                    @file_get_contents($this->baseSet . "drp/" . $v);
                    usleep(300000);
                }

                return new JsonResponse(['success'=>true]);
            }

            // -----------------------------------
            // UHRZEIT (HH:MM)
            // -----------------------------------
            if ($type === 'dtt') {

                $v = trim((string)$value);

                if (preg_match('/^\d{2}:\d{2}$/', $v)) {
                    @file_get_contents($this->baseSet . "dtt/" . $v);
                    usleep(300000);
                }

                return new JsonResponse(['success'=>true]);
            }

            // -----------------------------------
            // TEST STARTEN
            // -----------------------------------
            if ($type === 'dex') {

                @file_get_contents($this->baseSet . "dex/true");
                usleep(300000);

                return new JsonResponse(['success'=>true]);
            }

            return new JsonResponse([
                'success'=>false,
                'error'=>'unknown type'
            ]);
        }

        // ===================================================
        // ?? OPTIONAL: GET ALL (für späteres Auto-Refresh)
        // ===================================================
        if ($action === 'getAll') {

            $data = $this->syrGetAll();

            return new JsonResponse([
                'success' => true,
                'data'    => $data
            ]);
        }

        // ===================================================
        // ? DEFAULT
        // ===================================================
        return new JsonResponse([
            'success' => false,
            'error'   => 'unknown action'
        ]);
    }

    // ===================================================
    // ?? HELPER: GET EINZELWERT
    // ===================================================
    private function syrGet(string $cmd)
    {
        $ctx = stream_context_create([
            'http' => ['timeout' => 3]
        ]);

        $json = @file_get_contents(
            $this->baseGet . strtolower($cmd),
            false,
            $ctx
        );

        if (!$json) return null;

        $data = json_decode($json, true);

        if (!is_array($data) || empty($data)) return null;

        return array_values($data)[0] ?? null;
    }

    // ===================================================
    // ?? HELPER: GET ALL
    // ===================================================
    private function syrGetAll(): array
    {
        $ctx = stream_context_create([
            'http' => ['timeout' => 10]
        ]);

        $json = @file_get_contents(
            $this->baseGet . "all",
            false,
            $ctx
        );

        if (!$json) return [];

        $data = json_decode($json, true);

        if (!is_array($data)) return [];

        return $data;
    }
}