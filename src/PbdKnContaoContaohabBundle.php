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

namespace PbdKn\ContaoContaohabBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;




class PbdKnContaoContaohabBundle extends Bundle
{

    public function getContaoResourcesPath(): string
    {
        return 'Resources/contao/';
    }

    
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

}
