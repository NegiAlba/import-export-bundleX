<?php

namespace Galilee\ImportExportBundle\Installer;

use Galilee\ImportExportBundle\Helper\AttributeHelper;
use Pimcore\Model\DataObject;

class EiffageBis extends AbstractInstaller
{
    /**
     * @throws \Exception
     */
    public function install()
    {
        $newItemUnitPath = DIRECTORY_SEPARATOR . 'unité de conditionnement';
        $parent = DataObject\Service::createFolderByPath($newItemUnitPath);
        foreach (Eiffage::NEW_ITEM_UNIT_VALUES as $value) {
            $object = new DataObject\PackagingUnit();
            $object->setKey($value)->setLabel($value)->setParent($parent)->setPublished(true)->save();
            $this->output->writeln($value . ' attribute successfully created.');
        }

        $this->createAttributes();
    }

    /**
     * @throws \Exception
     */
    protected function createAttributes()
    {
        $path = '/' . AttributeHelper::PARENT_FOLDER_ATTRIBUTE_KEY;
        $parent = DataObject\Service::createFolderByPath($path);

        $key = 'number_pieces_packaging';
        $this->create($path, $key, [
            'attributeCode' => $key,
            'frontendLabel' => 'Nombre de pièce dans le conditionnement',
            'frontendInput' => 'text',
            'parent' => $parent,
            'locked' => true,
            'published' => true,
            'group' => 'Eiffage',
        ]);

        $key = 'packaging_unit';
        $this->create($path, $key, [
            'attributeCode' => $key,
            'frontendLabel' => 'Unité de conditionnement',
            'frontendInput' => 'select',
            'isSearchable' => true,
            'isFilterable' => true,
            'isVisibleOnFront' => true,
            'parent' => $parent,
            'locked' => true,
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
        }
    }
}
