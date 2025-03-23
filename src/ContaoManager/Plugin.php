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

namespace PbdKn\ContaoContaohabBundle\ContaoManager;

use PbdKn\ContaoContaohabBundle\PbdKnContaoContaohabBundle;
use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouteCollection;




class Plugin implements BundlePluginInterface, RoutingPluginInterface
{
    /**
     * @return array
     */
    public function getBundles(ParserInterface $parser)
    {
        return [
            BundleConfig::create(PbdKnContaoContaohabBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class]),
        ];
    }
    public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel): ?RouteCollection 
    {
 
      // echo "PBD contao-obenhab-bundle Plugin getRouteCollection ".__DIR__;
      // der pfad ist der Pfad in dem das Plugin liegt 
      // hier also C:\wampneu\www\co5\co5Bundles\contao-contaohab-bundle\src\ContaoManager
        return $resolver
            ->resolve(__DIR__.'/../Resources/config/routes.yaml')
            ->load   (__DIR__.'/../Resources/config/routes.yaml')        ;
    }        

}
