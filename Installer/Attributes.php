<?php

namespace Galilee\ImportExportBundle\Installer;

use Pimcore\Model\DataObject;

class Attributes extends AbstractInstaller
{
    public function install()
    {
        $list = DataObject\Attribute::getByAttributeCode('quantity_price');
        /** @var DataObject\Attribute $item */
        foreach ($list->load() as $item) {
            $item->setIsSearchable(false);
            $item->setIsVisibleInAdvancedSearch(false);
            $item->setIsFilterable(false);
            $item->setIsFilterableInSearch(false);
            $item->setUsedForSortBy(false);

            $item->save();
        }

        $list = new DataObject\Attribute\Listing();
        $list->setCondition('frontendInput = "text"');
        /** @var DataObject\Attribute $item */
        foreach ($list->load() as $item) {
            $item->setIsFilterable(false);
            $item->setIsFilterableInSearch(false);

            $item->save();
        }
    }
}
