<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2017 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Installer;

use Galilee\ImportExportBundle\Helper\AttributeHelper;
use Galilee\ImportExportBundle\Helper\BrickHelper;
use Galilee\ImportExportBundle\Helper\FamilyHelper;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\Objectbrick;

class Data extends AbstractInstaller
{

    public function install()
    {
        $this->createAttributes();
        $this->createAttributeSet();
    }

    protected function createAttributes()
    {
        $path = '/' . AttributeHelper::PARENT_FOLDER_ATTRIBUTE_KEY;
        $parent = DataObject\Service::createFolderByPath($path);

        $commonValues = [
            'parent' => $parent,
            'frontendInput' => 'select',
            'isSearchable' => true,
            'isFilterable' => true,
            'isVisibleOnFront' => true,
            'published' => true,
            'locked' => true,
            'group' => AttributeHelper::DEFAULT_GROUP_ATTRIBUTE,
        ];

        // Manufacturer
        $key = AttributeHelper::MANUFACTURER_CODE;
        $values = array_merge($commonValues, [
            'attributeCode' => $key,
            'frontendLabel' => 'Fournisseur'
        ]);
        $this->create($path, $key, $values);

        // Brand
        $key = AttributeHelper::BRAND_CODE;
        $values = array_merge($commonValues, [
            'attributeCode' => $key,
            'frontendLabel' => 'Marque'
        ]);
        $this->create($path, $key, $values);

        // Quantity Price Type
        $key = 'quantity_price_type';
        $values = array_merge($commonValues, [
            'attributeCode' => $key,
            'frontendLabel' => 'Type d\'unité Prix',
            'group' => AttributeHelper::ADVANCED_PRICING_GROUP_ATTRIBUTE
        ]);
        $this->create($path, $key, $values);

        // Quantity Price
        $key = 'quantity_price';
        $values = array_merge($commonValues, [
            'attributeCode' => $key,
            'frontendLabel' => 'Quantité d\'unité Prix',
            'group' => AttributeHelper::ADVANCED_PRICING_GROUP_ATTRIBUTE
        ]);
        $this->create($path, $key, $values);

        $key = 'ean';
        $values = array_merge($commonValues, [
            'attributeCode' => $key,
            'frontendLabel' => 'Ean13',
            'frontendInput' => 'text',
            'isComparable' => true,
            'isVisible' => true,
            'isHtmlAllowedOnFront' => true,
            'isFilterable' => false,
            'isSearchable' => false
        ]);
        $this->create($path, $key, $values);

        $key = 'pim_caracteristics';
        $values = array_merge($commonValues, [
            'attributeCode' => $key,
            'frontendLabel' => 'Caractéristiques produit',
            'frontendInput' => 'textarea',
            'isComparable' => true,
            'isVisible' => true,
            'isHtmlAllowedOnFront' => true,
            'isFilterable' => false,
            'isSearchable' => false
        ]);
        $this->create($path, $key, $values);

        $key = 'article_code';
        $values = array_merge($commonValues, [
            'attributeCode' => $key,
            'frontendLabel' => 'Référence fabricant',
            'frontendInput' => 'text',
            'isComparable' => true,
            'isVisible' => true,
            'isHtmlAllowedOnFront' => true,
            'isFilterable' => false,
            'isSearchable' => false
        ]);
        $this->create($path, $key, $values);

        $key = 'ean13';
        $values = array_merge($commonValues, [
            'attributeCode' => $key,
            'frontendLabel' => 'Ean13',
            'frontendInput' => 'text',
            'isComparable' => true,
            'isVisible' => true,
            'isHtmlAllowedOnFront' => true,
            'isFilterable' => false,
            'isSearchable' => false
        ]);
        $this->create($path, $key, $values);

        $key = 'weee_tax';
        $values = array_merge($commonValues, [
            'attributeCode' => $key,
            'frontendLabel' => 'Taxe DEEE',
            'frontendInput' => 'text',
            'isComparable' => true,
            'isVisible' => true,
            'isHtmlAllowedOnFront' => true,
            'isFilterable' => false,
            'isSearchable' => false
        ]);
        $this->create($path, $key, $values);
    }

    protected function create($path, $key, $values)
    {
        if (!DataObject\Service::pathExists($path . '/' . $key)) {
            $object = new DataObject\Attribute();
            $object->setKey($key)->setValues($values)->save();
            $this->output->writeln($key . ' attribute successfully created.');
        }
    }

    public function createAttributeSet()
    {
        $attributes = [];
        $attributes[] = [
            'group' => 'Socoda attributes',
            'name' => 'ean',
            'title' => 'Ean13',
            'fieldType' => 'input'
        ];
        $attributes[] = [
            'group' => 'Socoda attributes',
            'name' => 'pim_caracteristics',
            'title' => 'Caractéristiques produit',
            'fieldType' => 'wysiwyg'
        ];
        $attributes[] = [
            'group' => 'Socoda attributes',
            'name' => 'article_code',
            'title' => 'Référence fabricant',
            'fieldType' => 'input'
        ];
        $attributes[] = [
            'group' => 'Socoda attributes',
            'name' => 'ean13',
            'title' => 'Ean13',
            'fieldType' => 'input'
        ];
        $attributes[] = [
            'group' => 'Socoda attributes',
            'name' => 'weee_tax',
            'title' => 'Taxe DEEE',
            'fieldType' => 'input'
        ];
        try {
            Objectbrick\Definition::getByKey('default_set');
        } catch (\Exception $exception) {
            BrickHelper::create('Default', $attributes);
        }

        $family = FamilyHelper::getByCode('default');
        if (!$family) {
            $parentFolder = DataObject\Service::createFolderByPath(FamilyHelper::PARENT_FOLDER_FAMILY_KEY);
            $family = new DataObject\Family();
            $family
                ->setParent($parentFolder)
                ->setKey('Default')
                ->setFamilyCode('default')
                ->setName('Default')
                ->setObjectBrickKey(BrickHelper::getBrickKeyFromAttributeSet('default'))
                ->setPublished(true)
                ->save();
        }
    }
}
