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
use Contao\Controller;
use Contao\System;


/**
 * Content elements CohHistoryChart
 */
$GLOBALS['TL_DCA']['tl_content']['palettes'][CohHistoryChart::TYPE] = '{type_legend},type,headline,coh_canvas_things,selectedSensors;{template_legend:hide},coh_canvas_template;{expert_legend:hide},cssID;{invisible_legend:hide},invisible,start,stop';

// Felder für Template-Auswahl definieren
$GLOBALS['TL_DCA']['tl_content']['fields']['coh_canvas_template'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['coh_canvas_template'],
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

