<?php

declare(strict_types=1);

namespace PbdKn\ContaoContaohabBundle\EventListener;

use Contao\Backend;
use Contao\Controller;
use Contao\DataContainer;
use Contao\Input;
use Contao\Message;
use PbdKn\ContaoContaohabBundle\Service\RaspberryConfigPushService;

final class RaspberryConfigPushCallback
{
    public function __construct(private readonly RaspberryConfigPushService $configPushService)
    {
    }

    public function __invoke(?DataContainer $dc = null): void
    {
        $action = Input::get('key');

        if (!in_array($action, ['pushRaspberryConfig', 'pullRaspberryConfig'], true)) {
            return;
        }

        try {
            if ('pullRaspberryConfig' === $action) {
                $result = $this->configPushService->pull();
                Message::addConfirmation(sprintf(
                    'Konfiguration erfolgreich vom Raspberry geholt (%d Geraete, %d Sensoren).',
                    $result['devices'],
                    $result['sensors'],
                ));
            } else {
                $result = $this->configPushService->push();
                Message::addConfirmation(sprintf(
                    'Konfiguration erfolgreich zum Raspberry uebertragen (%d Geraete, %d aktive Sensoren).',
                    $result['devices'],
                    $result['sensors'],
                ));
            }
        } catch (\Throwable $exception) {
            Message::addError('Raspberry-Konfigurationsabgleich fehlgeschlagen: '.$exception->getMessage());
        }

        Controller::redirect(Backend::addToUrl('key='));
    }
}
