<?php

namespace Galilee\ImportExportBundle\Installer;

use Galilee\ImportExportBundle\Helper\AttributeHelper;
use Pimcore\Model\DataObject;

class EiffageTer extends AbstractInstaller
{
    /**
     * @throws \Exception
     */
    public function install()
    {
        $this->createAttributes();
    }

    /**
     * @throws \Exception
     */
    protected function createAttributes()
    {
        $path = '/' . AttributeHelper::PARENT_FOLDER_ATTRIBUTE_KEY;
        $parent = DataObject\Service::createFolderByPath($path);

        $key = 'pcre';
        $this->create($path, $key, [
            'attributeCode' => $key,
            'frontendLabel' => 'PCRE',
            'frontendInput' => 'boolean',
            'parent' => $parent,
            'locked' => true,
            'usedInProductListing' => true,
            'isVisibleOnFront' => true,
            'published' => true,
            'group' => 'Eiffage',
        ]);
    }

    /**
     * @param $path
     * @param $key
     * @param $values
     *
     * @throws \Exception
     */
    protected function create($path, $key, $values)
    {
        if (!DataObject\Service::pathExists($path . '/' . $key)) {
            $object = new DataObject\Attribute();
            $object->setKey($key)->setValues($values)->save();
            $this->output->writeln($key . ' attribute successfully created.');
        } else {
            $object = DataObject\Service::getElementByPath('object', $path . '/' . $key);
            $object->setValues($values);
            $object->save();
        }
    }
}
