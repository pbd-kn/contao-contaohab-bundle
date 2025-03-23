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

use PbdKn\ContaoContaohabBundle\Controller\FrontendModule\DisplayThingsController;

/**
 * Backend modules
 */

$GLOBALS['TL_LANG']['MOD']['ThingsListe'] = ['Things-Liste', 'Hier können Sie Ihre Things verwalten.'];
$GLOBALS['TL_LANG']['MOD']['Sensorliste'] = ['Sensor-Liste', 'Hier können Sie Ihre Sensoren verwalten.'];

$GLOBALS['TL_LANG']['MOD']['contao_hab'] = 'ContaoHab';
$GLOBALS['TL_LANG']['MOD']['things_collection'] = ['contaohab things', 'Modul für Things'];

/**
 * Frontend modules
 */
$GLOBALS['TL_LANG']['FMD']['contao_hab'] = 'ContaoHab';
$GLOBALS['TL_LANG']['FMD'][DisplayThingsController::TYPE] = ['DisplayThings', 'stellt alle Things dar'];

$GLOBALS['TL_LANG']['tl_module']['coh_template'] = ['Template','Template mod_coh_things_ auswählen'];
$GLOBALS['TL_LANG']['tl_module']['coh_selectedSensor'] = ['Sensoren aller Things','die ausgewählten sensoren die in diesem Modul verarbeitet werden'];
$GLOBALS['TL_LANG']['tl_module']['coh_sensorThingMap'] = ['Sensor-Zuordnung (JSON)', 'Geben Sie eine JSON-Zuordnung von Things zu Sensoren an.'];
/**
 * 'FMD' ist der Schlüssel für Frontend-Module. damit bei der auswahl des Moduls dies angezeigt wird
 */
$GLOBALS['TL_LANG']['FMD']['coh_things'] = ['COH Thing Modul', 'Modul zur Ausgabe eines Things im FE'];
 
/**
 * Help explatation texte
 */

$GLOBALS['TL_LANG']['XPL']['coh_selectedSensor_help'] = [
    ['Wähle hier die Sensorvariablen aus.', 'Diese Werte werden basierend auf dem ausgewählten Thing angezeigt.']
];

$GLOBALS['TL_LANG']['XPL']['coh_selectedThing_help'] = [
    ['Wähle hier die Things aus.', 'alle ausgewählten things mit den entsprechenden Sensoren werden ausgewählt.']
];
$GLOBALS['TL_LANG']['XPL']['coh_template_help'] = [
    ['Wähle hier das template aus.', 'die Templates heißen mod_coh_things_...  Default Template ist mod_coh_things_default']
];
