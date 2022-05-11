<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2017 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Helper;

use Exception;
use Pimcore\Model\DataObject;

class AttributeHelper
{

    const DEFAULT_ATTRIBUTE_SET = 'Default';
    const PARENT_FOLDER_ATTRIBUTE_KEY = 'attributes';
    const DEFAULT_GROUP_ATTRIBUTE = 'Socoda attributes';
    const ADVANCED_PRICING_GROUP_ATTRIBUTE = 'Advanced Pricing';
    const BRAND_CODE = 'brand_name';
    const MANUFACTURER_CODE = 'manufacturer';
    const DEFAULT_ATTRIBUTE_VALUE = [
        'attribute_code' => '',
        'frontend_input' => 'text',
        'frontend_label' => '',
        'is_unique' => false,
        'is_required' => false,
        'is_searchable' => false,
        'is_visible_in_advanced_search' => false,
        'is_comparable' => false,
        'is_filterable' => false,
        'is_filterable_in_search' => false,
        'is_used_for_promo_rules' => false,
        'is_wysiwyg_enabled' => false,
        'is_html_allowed_on_front' => false,
        'is_visible_on_front' => false,
        'used_in_product_listing' => false,
        'used_for_sort_by' => false,
        'attribute_options' => '',
        'default_value' => '',
        'group' => AttributeHelper::DEFAULT_GROUP_ATTRIBUTE,
        'attribute_set' => AttributeHelper::DEFAULT_ATTRIBUTE_SET,
    ];

    /**
     * @param $data
     * @param DataObject\Family $attributeSet
     * @throws Exception
     */
    public static function create($data, DataObject\Family $attributeSet)
    {
        $data = static::setDefault($data);
        $data['attributeCode'] = Tools::normalize($data['attributeCode']);
        $key = DataObject\Service::getValidKey($data['attributeCode'], 'object');
        $path = '/' . static::PARENT_FOLDER_ATTRIBUTE_KEY . '/' . $key;
        $parent = DataObject\Service::createFolderByPath(static::PARENT_FOLDER_ATTRIBUTE_KEY);

        if (DataObject\Service::pathExists($path)) {
            $attribute = DataObject\Attribute::getByPath($path);
        } else {
            $attribute = new DataObject\Attribute();
        }
        $attribute
            ->setValues($data)
            ->setAttributeSet($attributeSet)
            ->setParent($parent)
            ->setKey($key)
            ->setPublished(true)
            ->save();
    }

    public static function setDefault($data)
    {
        static::set($data, 'group', AttributeHelper::DEFAULT_GROUP_ATTRIBUTE);
        static::set($data, 'isSearchable', true);
        static::set($data, 'isVisibleInAdvancedSearch', false);
        return $data;
    }

    protected static function set(&$data, $key, $default)
    {
        if (!isset($data[$key]) || $data[$key] == '') {
            $data[$key] = $default;
        }
    }

    public static function getByCode($code)
    {
        $attribute = null;
        $path = '/' . AttributeHelper::PARENT_FOLDER_ATTRIBUTE_KEY . '/' . $code;
        if (DataObject\Service::pathExists($path)) {
            $attribute = DataObject\Attribute::getByPath($path);
        }
        return $attribute;

    }

}
