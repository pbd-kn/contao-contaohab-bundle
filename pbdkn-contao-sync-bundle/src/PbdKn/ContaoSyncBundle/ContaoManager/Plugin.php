<?php

namespace PbdKn\ContaoSyncBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Parser\BundleConfigParser;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;

class Plugin implements BundlePluginInterface
{
    public function getBundles(BundleConfigParser $parser, KernelInterface $kernel): array
    {
        return [
            BundleConfig::create(PbdKnContaoSyncBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class]),
        ];
    }
}
