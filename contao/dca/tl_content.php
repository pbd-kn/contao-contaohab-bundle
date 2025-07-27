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

use PbdKn\ContaoContaohabBundle\Controller\ContentElement\CohHistoryChart;
use PbdKn\ContaoContaohabBundle\Controller\ContentElement\CohAktuellChart;
use Contao\Controller;
use Contao\System;

/**
 * Content elements Coh EKD Bilder
 */
/**
 * Content elements Coh EKD Bilder
 */


$GLOBALS['TL_DCA']['tl_content']['palettes']['canvas_ekd'] =
    '{type_legend},type,headline;' .
    '{image_legend},canvas_ekd_data;' .
    '{template_legend:hide},canvas_ekd_template;' .
    '{expert_legend:hide},cssID,space';

// Feld für das Template
$GLOBALS['TL_DCA']['tl_content']['fields']['canvas_ekd_template'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['canvas_ekd_template'],
    'exclude' => true,
    'inputType' => 'select',
    'options_callback' => function () {
        return \Contao\Controller::getTemplateGroup('ce_canvas_ekd');
    },
    'eval' => ['tl_class' => 'w50', 'includeBlankOption' => true],
    'sql' => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['canvas_ekd_data'] = [
    'inputType' => 'multiColumnWizard',
    'eval' => [
        'tl_class' => 'clr',
        'columnOrder' => [  // <<< HIER Reihenfolge fixieren!
            'image', 'type', 'x', 'y', 'width', 'height',
            'rotation', 'opacity', 'color', 'background',
            'value', 'direction', 'label'
        ],
        'columnFields' => [

            // 1. Bild und Typ
            'image' => [
                'label' => ['Bild'],
                'inputType' => 'fileTree',
                'eval' => ['filesOnly' => true, 'fieldType' => 'radio', 'tl_class' => 'w50'],
            ],
            'type' => [
                'label' => ['Typ'],
                'inputType' => 'select',
                'options' => ['Solarzelle','HomeSolar','Akku','Einspeisung','Haus','Heizstab','Bar'],
                'eval' => ['chosen' => true, 'tl_class' => 'w25'],
            ],

            // 2. Position und Größe
            'x' => ['label' => ['X'], 'inputType' => 'text', 'eval' => ['rgxp' => 'digit', 'tl_class' => 'w15']],
            'y' => ['label' => ['Y'], 'inputType' => 'text', 'eval' => ['rgxp' => 'digit', 'tl_class' => 'w15']],
            'width' => ['label' => ['Breite'], 'inputType' => 'text', 'eval' => ['rgxp' => 'digit', 'tl_class' => 'w15']],
            'height' => ['label' => ['Höhe'], 'inputType' => 'text', 'eval' => ['rgxp' => 'digit', 'tl_class' => 'w15']],

            // 3. Stil & Dynamik
            'rotation' => ['label' => ['Rotation'], 'inputType' => 'text', 'eval' => ['rgxp' => 'numeric', 'tl_class' => 'w15']],
            'opacity' => ['label' => ['Deckkraft'], 'inputType' => 'text', 'eval' => ['rgxp' => 'numeric', 'tl_class' => 'w15']],
            'color' => ['label' => ['Farbe'], 'inputType' => 'text', 'eval' => ['tl_class' => 'w15']],
            'background' => ['label' => ['Hintergrund'], 'inputType' => 'text', 'eval' => ['tl_class' => 'w15']],
            'value' => ['label' => ['Füllstand'], 'inputType' => 'text', 'eval' => ['rgxp' => 'numeric', 'tl_class' => 'w15']],
            'direction' => [
                'label' => ['Richtung'],
                'inputType' => 'select',
                'options' => ['up' => 'oben', 'down' => 'unten', 'left' => 'links', 'right' => 'rechts'],
                'eval' => ['tl_class' => 'w15'],
            ],

            // 4. Text
            'label' => [
                'label' => ['Text'],
                'inputType' => 'textarea',
                'eval' => ['style' => 'height:40px', 'tl_class' => 'w50'],
            ],
        ]
    ],
    'sql' => "blob NULL"
];



/**
 * Content elements CohHistoryChart
 */
$GLOBALS['TL_DCA']['tl_content']['palettes'][CohHistoryChart::TYPE] = '{type_legend},type,headline,coh_canvas_things,selectedSensors;{template_legend:hide},coh_history_template;{expert_legend:hide},cssID;{invisible_legend:hide},invisible,start,stop';

/**
 * Content elements CohAktuellChart
 */
$GLOBALS['TL_DCA']['tl_content']['palettes'][CohAktuellChart::TYPE] = '{type_legend},type,headline,coh_canvas_things,selectedSensors;{template_legend:hide},coh_aktuell_template;{expert_legend:hide},cssID;{invisible_legend:hide},invisible,start,stop';

// Felder für Template-Auswahl definieren
$GLOBALS['TL_DCA']['tl_content']['fields']['coh_history_template'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['coh_history_template'],
    'exclude' => true,
    'inputType' => 'select',
    'options_callback' => static function () {
        // Alle Templates holen, die mit 'ce_coh_' beginnen
        $options = \Contao\Controller::getTemplateGroup('ce_coh_history_');

        // Das gewünschte Standard-Template
        $defaultTemplate = 'ce_coh_history_chart';

        // Prüfen, ob das Standard-Template bereits in der Liste ist
        if (!isset($options[$defaultTemplate])) {
            // Nicht vorhanden → an den Anfang setzen und '(Standard)' ergänzen
            $options = [$defaultTemplate => $defaultTemplate . ' (Standard)'] + $options;
        } else {
            // Vorhanden → nur '(Standard)' ergänzen
            $options[$defaultTemplate] .= ' (Standard)';
        }

        return $options;
    },
    'eval' => ['tl_class' => 'w50', 'includeBlankOption' => false],
    'sql' => "varchar(64) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['coh_aktuell_template'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['coh_aktuell_template'],
    'exclude' => true,
    'inputType' => 'select',
    'options_callback' => static function () {
        // Alle Templates holen, die mit 'ce_coh_' beginnen
        $options = \Contao\Controller::getTemplateGroup('ce_coh_aktuell_');

        // Das gewünschte Standard-Template
        $defaultTemplate = 'ce_coh_aktuell_chart';

        // Prüfen, ob das Standard-Template bereits in der Liste ist
        if (!isset($options[$defaultTemplate])) {
            // Nicht vorhanden → an den Anfang setzen und '(Standard)' ergänzen
            $options = [$defaultTemplate => $defaultTemplate . ' (Standard)'] + $options;
        } else {
            // Vorhanden → nur '(Standard)' ergänzen
            $options[$defaultTemplate] .= ' (Standard)';
        }

        return $options;
    },
    'eval' => ['tl_class' => 'w50', 'includeBlankOption' => false],
    'sql' => "varchar(64) NOT NULL default ''",
];


$GLOBALS['TL_DCA']['tl_content']['fields']['coh_canvas_things'] = [
    'label' => ['Anzuzeigende Things', 'Wählen Sie die Things, die in der Visualisierung angezeigt werden sollen.'],
    'inputType' => 'checkboxWizard',
    'eval' => ['multiple' => true, 'chosen' => true, 'submitOnChange' => true], // <- das ist neu!
    'options_callback' => function () {
        $db = \Contao\System::getContainer()->get('database_connection');
        $rows = $db->fetchAllAssociative('SELECT thingID, thingTitle FROM tl_coh_things ORDER BY thingID');

        return array_column($rows, 'thingID', 'thingID');
    },
    'sql' => "blob NULL"
];

$GLOBALS['TL_DCA']['tl_content']['fields']['selectedSensors'] = [
    'label' => ['Sensorvariablen', 'Wählen Sie die Sensorvariablen aus, die angezeigt werden sollen.'],
    'inputType' => 'select',
    'eval' => ['multiple' => true, 'chosen' => true, 'tl_class' => 'clr'],
    'options_callback' => function (\Contao\DataContainer $dc) {
        $thingIds = \Contao\StringUtil::deserialize($dc->activeRecord->coh_canvas_things, true);

        if (empty($thingIds)) {
            return [];
        }

        $db = \Contao\System::getContainer()->get('database_connection');
        $rows = $db->fetchAllAssociative(
            'SELECT Sensorvariable FROM tl_coh_things WHERE thingID IN (?)',
            [$thingIds],
            [\Doctrine\DBAL\Connection::PARAM_STR_ARRAY]
        );

        $sensoren = [];

        foreach ($rows as $row) {
            $decoded = \Contao\StringUtil::deserialize($row['Sensorvariable'], true);

            if (is_array($decoded)) {
                foreach ($decoded as $sensorName) {
                    $sensoren[$sensorName] = $sensorName;
                }
            } elseif (!empty($row['Sensorvariable'])) {
                $sensoren[$row['Sensorvariable']] = $row['Sensorvariable'];
            }
        }

        ksort($sensoren); // alphabetisch sortieren (optional)

        return $sensoren;
    },
    'sql' => "blob NULL"
];

