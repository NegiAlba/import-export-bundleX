<?php

namespace Galilee\ImportExportBundle\Installer;

use Pimcore\Model\DataObject;

class Categories extends AbstractInstaller
{

    /**
     * @throws \Exception
     */
    public function install()
    {
        $list = new DataObject\Category\Listing();

        foreach ($list->load() as $category) {
            if ($category->getDisplayDescription() !== false) {
                $category->setDisplayDescription(true);
            }
            if ($category->getDisplayLogo() !== false) {
                $category->setDisplayLogo(true);
            }
            if ($category->getVisibleInMenu() !== false) {
                $category->setVisibleInMenu(true);
            }
            $category->save();
        }

        $this->output->writeln('Categories updated.');
    }
}
