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
$GLOBALS['TL_LANG']['MOD']['contao_hab'] = 'ContaoHab';
$GLOBALS['TL_LANG']['MOD']['things_collection'] = ['contaohab things', 'Modul für Things'];

/**
 * Frontend modules
 */
$GLOBALS['TL_LANG']['FMD']['contao_hab'] = 'ContaoHab';
$GLOBALS['TL_LANG']['FMD'][DisplayThingsController::TYPE] = ['DisplayThings', 'stellt alle Things dar'];

