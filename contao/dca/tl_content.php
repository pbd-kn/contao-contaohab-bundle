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

use PbdKn\ContaoCohCanvasBundle\Controller\ContentElement\CohCanvasElement;

/**
 * Content elements
 */

$GLOBALS['TL_DCA']['tl_content']['palettes'][CohCanvasElement::TYPE] = 
    '{type_legend},type,headline;
     {template_legend:hide},coh_canvas_template;
     {expert_legend:hide},cssID;
     {invisible_legend:hide},invisible,start,stop';

$GLOBALS['TL_DCA']['tl_content']['fields']['coh_canvas_template'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['coh_canvas_template'],
    'exclude' => true,
    'inputType' => 'select',
    'options_callback' => static function () {
        return \Contao\Controller::getTemplateGroup('ce_coh_');
    },
    'eval' => ['tl_class' => 'w50', 'includeBlankOption' => true, 'helpwizard' => true],
    'sql' => "varchar(64) NOT NULL default ''"
];
