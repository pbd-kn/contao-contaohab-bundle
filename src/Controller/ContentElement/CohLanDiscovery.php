<?php

namespace PbdKn\ContaoContaohabBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Contao\BackendTemplate;
use Contao\StringUtil;
use Contao\System;

#[AsContentElement(self::TYPE, category: 'COH', template: 'ce_coh_lan_discovery')]
class CohLanDiscovery extends AbstractContentElementController
{
    public const TYPE = 'coh_lan_discovery';

    protected function getResponse($template, ContentModel $model, Request $request): Response
    {

        $scope = System::getContainer()->get('request_stack')?->getCurrentRequest()?->attributes?->get('_scope');

        // -------------------------------------------------
        // Backend Darstellung
        // -------------------------------------------------

        if ('backend' === $scope) {

            $wildcard = new BackendTemplate('be_wildcard_coh');

            $wildcard->title = StringUtil::deserialize($model->headline)['value'] ?? 'LAN Discovery';
            $wildcard->id = $model->id;

            $wildcard->wildcard = "### COH LAN DISCOVERY ###";

            return new Response($wildcard->parse());
        }


        // -------------------------------------------------
        // Geräte Mapping
        // -------------------------------------------------

        $deviceMap = [

            "b8-d8-12-a1-e0-4f" => "SmartBox",
            "d4-8a-fc-15-ff-98" => "Tasmota",
            "98-6d-35-c1-23-52" => "myPV",
            "d8-3a-dd-66-0c-92" => "Raspberry"

        ];


        // -------------------------------------------------
        // ARP Tabelle lesen
        // -------------------------------------------------

        $arp = shell_exec("arp -a");

        $devices = [];

        foreach (explode("\n",$arp) as $line) {

            $line = trim($line);

            if (!$line) continue;

            // Windows Format
            if (preg_match('/^(\d+\.\d+\.\d+\.\d+)\s+([0-9a-f\-]{17})/i',$line,$m)) {

                $ip  = $m[1];
                $mac = strtolower($m[2]);

            }

            // Linux Format
            elseif (preg_match('/\((\d+\.\d+\.\d+\.\d+)\) at ([0-9a-f:]{17})/i',$line,$m)) {

                $ip  = $m[1];
                $mac = strtolower(str_replace(":","-",$m[2]));

            }

            else {
                continue;
            }


            // Multicast entfernen
            if (
                $ip === "255.255.255.255" ||
                str_starts_with($ip,"224.") ||
                str_starts_with($ip,"239.")
            ) {
                continue;
            }


            $hostname = @gethostbyaddr($ip);

            if ($hostname === $ip) {
                $hostname = null;
            }


            // Gerätetyp bestimmen
            $type = "unknown";

            if (isset($deviceMap[$mac])) {

                $type = $deviceMap[$mac];

            } else {

                if ($hostname && str_contains(strtolower($hostname),"fritz"))
                    $type="FRITZ";

                elseif ($hostname && str_contains(strtolower($hostname),"raspberry"))
                    $type="Raspberry";

                elseif ($hostname && str_contains(strtolower($hostname),"iphone"))
                    $type="iPhone";

                elseif ($hostname && str_contains(strtolower($hostname),"mypv"))
                    $type="myPV";

            }


            $devices[]=[

                "ip"=>$ip,
                "mac"=>$mac,
                "hostname"=>$hostname,
                "type"=>$type

            ];
        }


        $template->devices = $devices;
        $template->deviceCount = count($devices);

        return $template->getResponse();
    }
}