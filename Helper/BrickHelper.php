<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2017 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Helper;

use Exception;
use Pimcore\File;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Objectbrick;

class BrickHelper
{
    const RESERVED_NAMES = [
        'id', 'key', 'path', 'type', 'index', 'classname', 'creationdate', 'userowner', 'value', 'class', 'list', 'fullpath', 'childs', 'values', 'cachetag', 'cachetags', 'parent', 'published', 'valuefromparent', 'userpermissions', 'dependencies', 'modificationdate', 'usermodification', 'byid', 'bypath', 'data', 'versions', 'properties', 'permissions', 'permissionsforuser', 'childamount', 'apipluginbroker', 'resource', 'parentClass', 'definition', 'locked', 'language', 'omitmandatorycheck', 'idpath', 'object', 'fieldname', 'property', 'localizedfields', 'parentId'
    ];

    const DEFAULT = 'Default';

    /**
     *  Magento type => Pimcore type
     */
    const FIELD_TYPE_MAPPING = [
        'select' => 'select',
        'multiselect' => 'multiselect',
        'textarea' => 'wysiwyg',
        'date' => 'date',
        'text' => 'input',
        'boolean' => 'checkbox'
    ];

    public static function getBrickKeyFromAttributeSet($attributeSet)
    {
        return File::getValidFilename($attributeSet . '_set');
    }

    /**
     * @param string $attributeSet
     * @param array $attributes
     * @return mixed|null|Objectbrick\Definition
     * @throws Exception
     */
    public static function create(string $attributeSet, array $attributes)
    {
        $brickKey = self::getBrickKeyFromAttributeSet($attributeSet);
        foreach ($attributes as &$attribute) {
            $attribute['name'] = static::getValidName($attribute['name']);
        }
        $attributes = static::addFieldsFromBrick($brickKey, $attributes);
        $brickObject = static::createBrick($brickKey, $attributeSet, $attributes);
        return $brickObject;
    }


    /**
     * @param string $brickKey
     * @param string $attributeSet
     * @param array $attributes
     * @return mixed|null|Objectbrick\Definition
     * @throws Exception
     */
    public static function createBrick(
        string $brickKey,
        string $attributeSet,
        array $attributes
    )
    {
        $objectBrick = new Objectbrick\Definition();
        $objectBrick->setKey($brickKey);
        $layout = new ClassDefinition\Layout();

        $attributeGroups = [];
        foreach ($attributes as $attribute) {
            $attributeGroups[$attribute['group']][] = $attribute;
        }

        foreach ($attributeGroups as $group => $attributes) {

            $groupPanel = new ClassDefinition\Layout\Panel();
            $groupPanel->setName($group)
                ->setTitle($group)
                ->setLabelWidth(250);
            $layout->addChild($groupPanel);

            foreach ($attributes as $attribute) {
                $field = static::createField($attribute);
                $groupPanel->addChild($field);
                $objectBrick->addFieldDefinition($field->getName(), $field);
            }
        }

        $objectBrick->setLayoutDefinitions($layout);
        $objectBrick->setClassDefinitions([['classname' => 'Product', 'fieldname' => "attributes"],]);
        $objectBrick->setTitle($attributeSet);
        $objectBrick->save();
        return $objectBrick;
    }

    public static function addFieldsFromBrick(
        $brickKey,
        array $attributes
    )
    {
        try {
            /** @var Objectbrick\Definition $brick */
            $brick = Objectbrick\Definition::getByKey($brickKey);
            if ($brick) {
                $attributesName = array_column($attributes, 'name');
                $layout = $brick->getLayoutDefinitions();
                $existingFields = static::getRecursiveFields($layout);

                foreach ($existingFields as $existingField) {
                    $field = $existingField['field'];
                    $foundField = array_search($field->getName(), $attributesName);

                    if ($foundField === false) {
                        $attributes[] = [
                            'group' => $existingField['group'],
                            'name' => $field->getName(),
                            'title' => $field->getTitle(),
                            'fieldType' => $field->getFieldType(),
                            'options' => static::getOptionsFromFields($field)
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            // Brick doesn't exists. Nothing special to do!
        }
        return $attributes;
    }

    public static function createField($attribute)
    {
        switch ($attribute['fieldType']) {

            case 'wysiwyg':
                $field = new ClassDefinition\Data\Wysiwyg();
                break;

            case 'checkbox':
                $field = new ClassDefinition\Data\Checkbox();
                break;

            case 'date':
                $field = new ClassDefinition\Data\Date();
                break;

            case 'select':
                $field = new ClassDefinition\Data\Select();
                $field->setOptions(static::getOptions($attribute));
                break;

            case 'multiselect':
                $field = new ClassDefinition\Data\Multiselect();
                $field->setOptions(static::getOptions($attribute));
                break;

            default:
                $field = new ClassDefinition\Data\Input();
                $field->setColumnLength(80);

        }

        $field->setName($attribute['name']);
        $field->setTitle($attribute['title']);
        $field->setVisibleGridView(false);
        return $field;
    }

    public static function getValidName($name)
    {
        $name = Tools::normalize($name);
        if (in_array($name, BrickHelper::RESERVED_NAMES)) {
            $name .= '_';
        }
        return $name;
    }

    /**
     * Return objects brick fields with attribute group.
     *
     * @param ClassDefinition\Layout $layout
     * @param array $fields
     * @param string $group
     * @return array
     */
    public static function getRecursiveFields(
        ClassDefinition\Layout $layout,
        array $fields = [],
        ?string $group = ''
    ): array
    {
        foreach ($layout->getChildren() as $child) {
            if ($child instanceof ClassDefinition\Layout\Panel) {
                $group = $child->getTitle();
                $fields = static::getRecursiveFields($child, $fields, $group);
            } elseif ($child instanceof ClassDefinition\Data) {
                $fields[] = [
                    'group' => $group,
                    'field' => $child
                ];
            }
        }
        return $fields;
    }


    public static function getOptionsFromFields($field)
    {
        $attributeOptions = '';
        if (method_exists($field, 'getOptions')) {
            $attributeOptions = implode('|',
                array_map(function ($option) {
                    return $option['value'];
                }, $field->getOptions())
            );
        }
        return $attributeOptions;
    }

    /**
     *
     * @param $attribute
     * @return array
     */
    public static function getOptions($attribute)
    {
        $opts = explode('|', $attribute['options']);
        $options = [];
        foreach ($opts as $option) {
            $options[] = ['key' => $option, 'value' => $option];
        }
        return $options;
    }

}
