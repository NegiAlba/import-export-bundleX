<?php

namespace Galilee\ImportExportBundle\EventListener;

use Pimcore\Model\WebsiteSetting;
use Symfony\Component\EventDispatcher\GenericEvent;

class AdminBeforeListLoadListener
{

    /**
     * FIX #3789 : removed the order by from to prevent timeouts on projects with huge "objects" table
     * @param GenericEvent $e
     *
     */
    public function onBeforeListLoad(GenericEvent $e)
    {
        if (WebsiteSetting::getByName('disable_sort') === null || WebsiteSetting::getByName('disable_sort')->getData() === "1") {

            /** @var \Pimcore\Model\DataObject\Listing $list */
            $list = $e['list'];
            $list->setOrderKey(null);
        }
    }
}
