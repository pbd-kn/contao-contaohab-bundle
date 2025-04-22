<?php

namespace PbdKn\ContaoContaohabBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class AjaxController extends AbstractController
{
    public function __construct(
        private readonly ScopeMatcher $scopeMatcher,
        private readonly CsrfTokenManagerInterface $csrfTokenManager
    ) {}

    #[Route('/_ajax/contaohab/custom-action', name: 'contaohab_ajax_custom_action', methods: ['POST'])]
    public function customAction(Request $request): JsonResponse
    {
        // Optional: Nur im Backend zulassen
        if (!$this->scopeMatcher->isBackendRequest($request)) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        // CSRF-Token prüfen
        $token = $request->request->get('_token');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('contao_backend', $token))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

        // Anfrage verarbeiten
        $value = $request->request->get('value');

        // Deine Logik hier …
        return new JsonResponse([
            'success' => true,
            'message' => 'Wert empfangen: ' . $value,
        ]);
    }
}
/* bedutung name in der Annotation
generierung url
$url = $this->generateUrl('contaohab_ajax_custom_action');
Verwendung on twig oder php templates
{{ path('contaohab_ajax_custom_action') }}
bei html5 sie chatgpt
*/



/* zugehörges js
fetch('/_ajax/contaohab/custom-action', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
    },
    body: new URLSearchParams({
        value: 'Hallo vom Frontend',
        _token: Contao.request_token // Nur im Backend verfügbar
    })
})
.then(response => response.json())
.then(data => {
    console.log(data);
});
*/

