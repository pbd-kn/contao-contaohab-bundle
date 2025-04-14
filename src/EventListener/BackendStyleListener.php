<?php

namespace PbdKn\ContaoContaohabBundle\EventListener;

use Contao\CoreBundle\Event\ContaoEvents;
use Contao\CoreBundle\Event\Backend\LoadDataContainerEvent;
// wird nur im BE ausgelöst

class BackendStyleListener
{
    public function __invoke(LoadDataContainerEvent $event): void
    { 
//    die('BackendStyleListener');
       $GLOBALS['TL_CSS'][] = 'bundles/pbdkncontaocontaohab/css/backendStyles.css|static';
    }
}
