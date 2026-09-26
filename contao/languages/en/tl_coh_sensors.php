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
$GLOBALS['TL_LANG']['tl_coh_sensors']['title'] = ["Titel", "Geben Sie den Namen des Sensors ein"];

/**
 * References
 */

/**
 * Buttons
 */
$GLOBALS['TL_LANG']['tl_coh_sensors']['customButton'] = "Custom Routine starten";
$GLOBALS['TL_LANG']['tl_coh_sensors']['pushRaspberryConfig'] = ['Transfer configuration to Raspberry', 'Transfers all devices and active sensors to the Raspberry via HTTPS.'];
$GLOBALS['TL_LANG']['tl_coh_sensors']['pushRaspberryConfigConfirm'] = 'Transfer devices and active sensors to the Raspberry now?';
$GLOBALS['TL_LANG']['tl_coh_sensors']['pullRaspberryConfig'] = ['Fetch configuration from Raspberry', 'Imports devices and sensors from the Raspberry into the local database.'];
$GLOBALS['TL_LANG']['tl_coh_sensors']['pullRaspberryConfigConfirm'] = 'Fetch devices and sensors from the Raspberry now? Local records with the same ID will be updated.';

$GLOBALS['TL_LANG']['tl_coh_sensors']['base_legend'] = 'Basic settings';
$GLOBALS['TL_LANG']['tl_coh_sensors']['calc_legend'] = 'Calculation and components';
$GLOBALS['TL_LANG']['tl_coh_sensors']['history_legend'] = 'History';
$GLOBALS['TL_LANG']['tl_coh_sensors']['historyListLabel'] = 'Record history';
$GLOBALS['TL_LANG']['tl_coh_sensors']['historyListYes'] = 'Yes';
$GLOBALS['TL_LANG']['tl_coh_sensors']['historyListNo'] = 'No';
