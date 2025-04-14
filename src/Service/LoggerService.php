<?php

// src/Service/MyLoggerService.php
namespace PbdKn\ContaoContaohabBundle\Service;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Psr\Log\LoggerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

use Monolog\Logger;


class LoggerService
{
    private string $dateiname;
    private Logger $contaoLogger;
    private string $projectDir;
    private bool $debug = false;
    // Variablen für späte Initialisierung
    private ?StreamHandler $streamHandler = null;
    
    public function __construct(string $dateiname, Logger $contaoLogger, ContainerInterface $container)
    {
        $this->dateiname = $dateiname;
        $this->contaoLogger = $contaoLogger;
        $this->debug=$container->getParameter('kernel.debug');;
        $this->projectDir = $container->getParameter('kernel.project_dir');;
    }
    

    public function debugMe(string $txt): void
    {
        if ($this->debug) {
            if ($this->streamHandler === null) { // Erst wenn der Debug-Modus aktiv ist und noch nicht initialisiert wurde
                $logPath = $this->projectDir . '/var/logs/' . $this->dateiname;
               // Erstelle einen LineFormatter, der nur die Nachricht loggt
                $formatter = new LineFormatter('%datetime% [Logger] %message%'. PHP_EOL, null, true, true);            
                $this->streamHandler = new StreamHandler($logPath, Logger::INFO);
                $this->streamHandler->setFormatter($formatter);  // Setze den benutzerdefinierten Formatter
                $this->contaoLogger->pushHandler($this->streamHandler);

                // Optional: Log-Nachrichten beim ersten Initialisieren
                //$this->contaoLogger->info('Logger initialisiert für ' . $this->dateiname);
            }    
            $this->contaoLogger->info($this->addDebugInfoToText($txt));
        }
    }

    public function Error(string $txt): void
    {
        if ($this->streamHandler === null) { // Erst wenn der Debug-Modus aktiv ist und noch nicht initialisiert wurde
            $logPath = $this->projectDir . '/var/logs/' . $this->dateiname;
            // Erstelle einen LineFormatter, der nur die Nachricht loggt
            $formatter = new LineFormatter('%datetime% [Logger] %message%'. PHP_EOL, null, true, true);            
            $this->streamHandler = new StreamHandler($logPath, Logger::INFO);
            $this->streamHandler->setFormatter($formatter);  // Setze den benutzerdefinierten Formatter
            $this->contaoLogger->pushHandler($this->streamHandler);

            // Optional: Log-Nachrichten beim ersten Initialisieren
            //$this->contaoLogger->info('Logger initialisiert für ' . $this->dateiname);
        }    
        $this->contaoLogger->error($this->addDebugInfoToText($txt));
    }
    public function isDebug(): bool
    {
        return $this->debug;
    }
    
    /* füege modul funktion und zeile dazu
     *
     */
    private function addDebugInfoToText(string $text): string
    {
        // Hole den aktuellen Stack-Trace und extrahiere Informationen
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $backtrace[1];

        // Extrahiere den Dateinamen und die Zeilennummer
        $file = isset($caller['file']) ? $caller['file'] : 'unknown file';
        $line = isset($caller['line']) ? $caller['line'] : 'unknown line';

        // Extrahiere den Funktionsnamen
        $function = isset($caller['function']) ? $caller['function'] : 'unknown function';

        // Baue den Log-Text mit dem Modulnamen (Dateiname, Zeilennummer und Funktionsname) zusammen
        $logInfo = sprintf('[%s:%d] %s : %s', basename($file), $line, $function, $text);

        // Rückgabe des erweiterten Text
        return $logInfo;
    }

}
