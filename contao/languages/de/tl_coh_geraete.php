<?php
/* Sprachdatei deutsch fuer dca tl_coh_graete */

declare(strict_types=1);

/*
 * This file is part of ContaoHab.
 *
 * (c) Peter Broghammer 2025 <pb-contao@gmx.de>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/pbd-kn/contao-contaohab-bundle
 */
 
 
 
/**
 * Operations
 */
$GLOBALS['TL_LANG']['tl_coh_geraete']['edit'] = "Datensatz mit ID: %s bearbeiten";
$GLOBALS['TL_LANG']['tl_coh_geraete']['copy'] = "Datensatz mit ID: %s kopieren";
$GLOBALS['TL_LANG']['tl_coh_geraete']['delete'] = "Datensatz mit ID: %s löschen";
$GLOBALS['TL_LANG']['tl_coh_geraete']['show'] = "Datensatz mit ID: %s ansehen";
$GLOBALS['TL_LANG']['tl_coh_geraete']['pushRaspberryConfig'] = ['Konfiguration zum Raspberry übertragen', 'Überträgt alle Geräte und aktiven Sensoren per HTTPS zum Raspberry.'];
$GLOBALS['TL_LANG']['tl_coh_geraete']['pushRaspberryConfigConfirm'] = 'Geräte und aktive Sensoren jetzt zum Raspberry übertragen?';
$GLOBALS['TL_LANG']['tl_coh_geraete']['pullRaspberryConfig'] = ['Konfiguration vom Raspberry holen', 'Übernimmt Geräte und Sensoren vom Raspberry in die lokale Datenbank.'];
$GLOBALS['TL_LANG']['tl_coh_geraete']['pullRaspberryConfigConfirm'] = 'Geräte und Sensoren jetzt vom Raspberry holen? Lokale Datensätze mit gleicher ID werden aktualisiert.';


/**
 * Fields
 */
$GLOBALS['TL_LANG']['tl_coh_geraete']['geraeteID']  = ['GeräteID','Eineutige Id des Geräts wird zur Auswahl des entsprechen Service (SensorService) verwendet'];
$GLOBALS['TL_LANG']['tl_coh_geraete']['geraeteTitle']  = ['Gerätetitel','Anzeigename des Greäts '];
$GLOBALS['TL_LANG']['tl_coh_geraete']['geraeteUrl']  = ['Geräteurl','IP Adresse des Gerates aaa.bbb.ccc.ddd oder serialnumber:APIKey'];
$GLOBALS['TL_LANG']['tl_coh_geraete']['geraeteDescription'] = ['Gerätebeschreibung','Ausführliche Beschreibung des Geräts'];



/**
 * References für selectboxen
 */
