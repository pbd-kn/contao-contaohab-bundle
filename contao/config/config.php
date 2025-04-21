<?php

/*
 * This file is part of ContaoHab.
 *
 * (c) Peter Broghammer 2025 <pb-contao@gmx.de>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/pbd-kn/contao-contaohab-bundle
 */

use PbdKn\ContaoContaohabBundle\Model\ThingsModel;
use Contao\System;

/**
 * Backend modules
 */
$GLOBALS['BE_MOD']['contao_hab']['Things'] = array(
    'tables' => array('tl_coh_things'),
	'icon'       => 'bundles/pbdkncontaocontaohab/icons/formdata_all.gif',
    'stylesheet' => 'bundles/pbdkncontaocontaohab/css/style.css',
);

$GLOBALS['BE_MOD']['contao_hab']['Sensor'] = array (
	'tables'     => ['tl_coh_sensors'],
	'icon'       => 'bundles/pbdkncontaocontaohab/icons/formdata_all.gif',
    'stylesheet' => 'bundles/pbdkncontaocontaohab/css/style.css',
);
$GLOBALS['BE_MOD']['contao_hab']['SensorValues'] = array (
	'tables'     => ['tl_coh_sensorvalue'],
	'icon'       => 'bundles/pbdkncontaocontaohab/icons/formdata_all.gif',
    'stylesheet' => 'bundles/pbdkncontaocontaohab/css/style.css',
);
$GLOBALS['BE_MOD']['contao_hab']['Geräte'] = array (
	'tables'     => ['tl_coh_geraete'],
	'icon'       => 'bundles/pbdkncontaocontaohab/icons/formdata_all.gif',
    'stylesheet' => 'bundles/pbdkncontaocontaohab/css/style.css',
);
$GLOBALS['BE_MOD']['contao_hab']['RemotCfg'] = array (
	'tables'     => ['tl_coh_cfgcollect'],
	'icon'       => 'bundles/pbdkncontaocontaohab/icons/formdata_all.gif',
    'stylesheet' => 'bundles/pbdkncontaocontaohab/css/style.css',
);
/**
 * Models
 */
$GLOBALS['TL_MODELS']['tl_coh_things'] = ThingsModel::class;
$GLOBALS['TL_MODELS']['tl_coh_sensorvalue'] = SensorValueModel::class;
$GLOBALS['TL_MODELS']['tl_coh_sensors'] = SensorModel::class;
$GLOBALS['TL_MODELS']['tl_coh_geraete'] = GeraeteModel::class;


/**
 * Fe Modules
 */

$GLOBALS['FE_MOD']['COH']['coh_things'] = 'PbdKn\ContaoContaohabBundle\Module\ModuleCohThings';

