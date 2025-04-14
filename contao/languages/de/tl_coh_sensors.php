<?php

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
use Contao\DC_Table;
use Contao\DataContainer;
use PbdKn\ContaoBesslichschmuck\Resources\contao\dataContainer\tableList;
use Contao\Backend;
use Contao\System;
use Contao\Image;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Legends
 */
$GLOBALS['TL_LANG']['tl_coh_sensors ']['first_legend'] = "Basis Einstellungen";


/**
* Global operations
*/
$GLOBALS['TL_LANG']['tl_coh_sensors']['new'] = ["Neu", "Ein neues Element anlegen"];

/**
 * Operations
 */
$GLOBALS['TL_LANG']['tl_coh_sensors']['edit'] = "Datensatz mit ID: %s bearbeiten";
$GLOBALS['TL_LANG']['tl_coh_sensors']['copy'] = "Datensatz mit ID: %s kopieren";
$GLOBALS['TL_LANG']['tl_coh_sensors']['delete'] = "Datensatz mit ID: %s löschen";
$GLOBALS['TL_LANG']['tl_coh_sensors']['show'] = "Datensatz mit ID: %s ansehen";

/**
 * Fields
 */
$GLOBALS['TL_LANG']['tl_coh_sensors']['sensorID'] = ['SensorId ','Eindeutige ID über alle Sensoren'];
$GLOBALS['TL_LANG']['tl_coh_sensors']['sensorTitle'] = ['Sensortitel','Zur Anzeige'];
$GLOBALS['TL_LANG']['tl_coh_sensors']['sensorEinheit'] = ['Einheit','Einheit des werte kann beim Aufruf des Wertze geändert werden. s. tranformProzedur '];
$GLOBALS['TL_LANG']['tl_coh_sensors']['sensorSource'] = ['SensorSozrce','Geraät das diesem Senxor zugeprdnet ist'];
$GLOBALS['TL_LANG']['tl_coh_sensors']['transFormProcedur'] = ['transFormProcedur','Beim Lesen des Wertes vom Value kann anbgebenen werden wie der gelesene Wert geändert wird. Dies ist eine Prozedurname der in der Sensorklass verwendet werden kann.'];
$GLOBALS['TL_LANG']['tl_coh_sensors']['sensorLokalId'] = ['SensorlokalId','Beim zugriff auf den sensor kann dieser Wert verwendet werden. Ansonsten wird sensorID verwendet'];
$GLOBALS['TL_LANG']['tl_coh_sensors']['persistent'] = ['Persostent speichern',' '];  // wird (noch) nicht verwendet
$GLOBALS['TL_LANG']['tl_coh_sensors']['lastUpdated'] = [' ',' ']; // wird  nicht angezeigt
$GLOBALS['TL_LANG']['tl_coh_sensors']['pollInterval'] = [' ',' ']; // wird  nicht angezeigt
$GLOBALS['TL_LANG']['tl_coh_sensors']['lastValue'] = [' ',' ']; // wird  nicht angezeigt
$GLOBALS['TL_LANG']['tl_coh_sensors']['lastError'] = [' ',' ']; // wird  nicht angezeigt


/**
 * References
 */
$GLOBALS['TL_LANG']['tl_coh_sensors']['persistent'] = ['Persistent speichern', 'Soll der Sensorwert dauerhaft gespeichert werden?'];

$GLOBALS['TL_LANG']['tl_coh_sensors']['persistent_options'] = [
    0 => 'Nein',
    1 => 'Ja',
];
/**
 * Buttons
 */
$GLOBALS['TL_LANG']['tl_coh_sensors']['customButton'] = "Custom Routine starten";
$GLOBALS['TL_LANG']['tl_coh_sensors']['persistent'] = ['Persistent speichern', 'Soll der Sensorwert dauerhaft gespeichert werden?'];
