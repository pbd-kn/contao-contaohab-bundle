<?php
declare(strict_types=1);

namespace PbdKn\ContaoContaohabBundle\Service;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Geschuetzter Client fuer KM271-Schreibvorschauen und ausdruecklich bestaetigte Auftraege.
 */
final class Km271WriteApiClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly array $settings,
    ) {
    }

    /** @return array<string, array<string, mixed>> */
    public function catalog(): array
    {
        return $this->configuration()['catalog'];
    }

    /** @return array{catalog: array<string, array<string, mixed>>, writeEnabled: bool, executable: list<string>} */
    public function configuration(): array
    {
        $payload = $this->request('GET');
        $commands = $payload['Schreibauftraege'] ?? null;
        if (!is_array($commands)) {
            throw new \RuntimeException('Der Raspberry lieferte keinen KM271-Schreibkatalog.');
        }
        $executable = is_array($payload['EchtbetriebErlaubt'] ?? null)
            ? array_values(array_filter(array_map('strval', $payload['EchtbetriebErlaubt'])))
            : [];
        return [
            'catalog' => $commands,
            'writeEnabled' => !empty($payload['Sendebereit']),
            'executable' => $executable,
        ];
    }

    /** @return array<string, mixed> */
    public function preview(string $localId, mixed $value): array
    {
        $payload = $this->request('POST', ['LokaleId' => $localId, 'Wert' => $value]);
        $preview = $payload['Vorschau'] ?? null;
        if (!is_array($preview)) {
            throw new \RuntimeException('Der Raspberry lieferte keine KM271-Schreibvorschau.');
        }
        return $preview;
    }

    /** @return array<string, mixed> */
    public function execute(string $localId, mixed $value, ?string $operator = null): array
    {
        $payload = $this->request('POST', [
            'LokaleId' => $localId,
            'Wert' => $value,
            'Ausfuehren' => true,
            'Bestaetigung' => 'SCHREIBEN',
            'Auftraggeber' => $operator,
        ]);
        $result = $payload['Ergebnis'] ?? null;
        if (!is_array($result)) {
            throw new \RuntimeException('Der Raspberry lieferte kein Ergebnis zum KM271-Schreibauftrag.');
        }
        return $result;
    }

    private function request(string $method, ?array $json = null): array
    {
        $baseUrl = rtrim(trim((string) ($this->settings['raspberryApiBaseUrl'] ?? '')), '/');
        $token = trim((string) ($this->settings['raspberryApiToken'] ?? ''));
        $path = (string) ($this->settings['km271CommandPath'] ?? '/api/coh/km271-command.php');

        if (!str_starts_with(strtolower($baseUrl), 'https://')) {
            throw new \RuntimeException('Fuer KM271-Schreibauftraege ist eine HTTPS-Raspberry-Adresse erforderlich.');
        }
        if ($token === '') {
            throw new \RuntimeException('Der Raspberry-API-Token fehlt.');
        }

        $options = [
            'headers' => ['X-COH-TOKEN' => $token, 'Accept' => 'application/json'],
            'timeout' => max(1, (int) ($this->settings['raspberryApiTimeout'] ?? 10)),
        ];
        if ($json !== null) {
            $options['json'] = $json;
        }

        try {
            $response = $this->httpClient->request($method, $baseUrl . $path, $options);
            $status = $response->getStatusCode();
            $payload = $response->toArray(false);
        } catch (TransportExceptionInterface $error) {
            throw new \RuntimeException('Der Raspberry ist fuer den KM271-Auftrag nicht erreichbar.', 0, $error);
        }

        if ($status !== 200 || empty($payload['ok'])) {
            $message = is_string($payload['error'] ?? null) ? $payload['error'] : "HTTP $status";
            throw new \RuntimeException('Raspberry meldet: ' . $message);
        }
        return $payload;
    }
}
