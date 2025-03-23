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
$GLOBALS['TL_LANG']['tl_coh_sensors']['coh_selectedThing'] = ['Things','alle ausgewählten things mit den entwprechendn Sensoren werden ausgewählt'];


/**
 * References
 */

/**
 * Buttons
 */
$GLOBALS['TL_LANG']['tl_coh_sensors']['customButton'] = "Custom Routine starten";
