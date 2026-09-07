<?php

declare(strict_types=1);

namespace PbdKn\ContaoContaohabBundle\Controller\ContentElement;

use Symfony\Component\HttpFoundation\Response;

trait RaspberryApiErrorResponseTrait
{
    private function raspberryApiErrorResponse(\Throwable $exception): Response
    {
        $message = htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $html = <<<'HTML'
<div class="coh-api-error" role="alert" style="padding:1rem 1.25rem;border:1px solid #d99b00;border-left-width:5px;background:#fff8e1;color:#3b2f00">
    <strong>Sensorwerte können momentan nicht geladen werden.</strong>
    <p style="margin:.5rem 0 0">%s</p>
    <p style="margin:.5rem 0 0">Bitte die Raspberry-Adresse und den API-Token in der <code>.env.local</code> prüfen. Anschließend den Contao-Cache leeren.</p>
</div>
HTML;

        // Der Ausfall externer Sensordaten darf nicht die gesamte Contao-Seite
        // mit HTTP 500 unbenutzbar machen.
        return new Response(sprintf($html, $message), Response::HTTP_OK);
    }
}
