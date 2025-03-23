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

namespace PbdKn\ContaoContaohabBundle\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\Input;
use Contao\System;

#[AsCallback(table: 'tl_coh_things', target: 'edit.buttons', priority: 100)]
class CohThings
{

    public function __construct(
        private readonly ContaoFramework $framework,
    ) {
    }

    public function __invoke(array $arrButtons, DataContainer $dc): array
    {
        $inputAdapter = $this->framework->getAdapter(Input::class);
        $systemAdapter = $this->framework->getAdapter(System::class);

        $systemAdapter->loadLanguageFile('tl_coh_things');
 // Debugging: Welche Sprachdatei wird geladen?

    //$systemAdapter->loadLanguageFile('tl_things'); // Testweise alte Datei laden

    //dump($GLOBALS['TL_LANG']); // Zeigt die geladenen Sprachdateien
if (!isset($GLOBALS['TL_LANG']['tl_coh_things']['customButton'])) {
        throw new \Exception("Sprachkey GLOBALS['TL_LANG']['tl_coh_things']['customButton'] wurde nicht geladen!");
    }
            if ('edit' === $inputAdapter->get('act')) {
            $arrButtons['customButton'] = '<button type="submit" name="customButton" id="customButton" class="tl_submit customButton" accesskey="x">'.$GLOBALS['TL_LANG']['tl_coh_things']['customButton'].'</button>';
        }

        return $arrButtons;
    }
}
