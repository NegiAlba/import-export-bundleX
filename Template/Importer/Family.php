<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2016 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Template\Importer;

use Exception;
use Galilee\ImportExportBundle\Helper\AttributeHelper;
use Galilee\ImportExportBundle\Helper\BrickHelper;
use Galilee\ImportExportBundle\Helper\FamilyHelper;
use Galilee\ImportExportBundle\Helper\Tools;
use Galilee\ImportExportBundle\Processor\Importer\AbstractImporter;
use Pimcore\Model\DataObject;

class Family extends AbstractImporter
{

    const FAMILY_PARENT_FOLDER_KEY = 'products';

    /** @var DataObject\Attribute */
    protected $inProcessObject;

    /** @var DataObject\AbstractObject */
    protected $familyParentFolder;

    protected $attributeParentFolder;

    protected $csvDataByAttributeSet = [];

    public $mandatoryFields = [
        'attribute_code',
        'frontend_input',
        'frontend_label',
        'attribute_set'
    ];

    /**
     * {csv column} => {object field}
     * {csv column} => array({object field}, {method}, array {arguments})
     * @var array
     */
    public $mapping = [
        'attribute_code' => 'attributeCode',
        'frontend_input' => 'frontendInput',
        'frontend_label' => 'frontendLabel',
        'is_unique' => 'isUnique',
        'is_required' => 'isRequired',
        'is_searchable' => 'isSearchable',
        'is_visible_in_advanced_search' => 'isVisibleInAdvancedSearch',
        'is_comparable' => 'isComparable',
        'is_filterable' => 'isFilterable',
        'is_filterable_in_search' => 'isFilterableInSearch',
        'is_used_for_promo_rules' => 'isUsedForPromoRules',
        'is_wysiwyg_enabled' => 'isWysiwygEnabled',
        'is_html_allowed_on_front' => 'isHtmlAllowedOnFront',
        'is_visible_on_front' => 'isVisibleOnFront',
        'used_in_product_listing' => 'usedInProductListing',
        'used_for_sort_by' => 'usedForSortBy',
        'attribute_options' => 'attributeOptions',
        'default_value' => 'defaultValue',
    ];

    public $loggerComponent = 'Import des familles';

    /**
     * @throws Exception
     */
    public function preProcess()
    {
        $this->familyParentFolder = DataObject\Service::createFolderByPath(self::FAMILY_PARENT_FOLDER_KEY);
        $this->attributeParentFolder = DataObject\Service::createFolderByPath(AttributeHelper::PARENT_FOLDER_ATTRIBUTE_KEY);
        return parent::preProcess();
    }

    /**
     * @param $row
     * @param integer $csvLineNumber
     *
     * @return bool
     * @throws Exception
     */
    protected function initObject($row, $csvLineNumber)
    {
        $attributeKey = Tools::normalize($row['attribute_code']);
        $this->inProcessObject = AttributeHelper::getByCode($attributeKey);
        if (!$this->inProcessObject) {
            $this->inProcessObject = new DataObject\Attribute();
            $this->inProcessObject
                ->setParent($this->attributeParentFolder)
                ->setKey($attributeKey);
        } else {
            $this->setMode(self::UPDATE_MODE);
        }

        $this->csvDataByAttributeSet[$row['attribute_set']][] = $row;
        $this->initObjectMessage = 'Attribut : ' . $attributeKey;
        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function postProcessRow()
    {
        if ($this->inProcessObject->getAttributeCode() == 'quantity_price') {
            $this->inProcessObject->setIsSearchable(false);
            $this->inProcessObject->setIsVisibleInAdvancedSearch(false);
            $this->inProcessObject->setIsFilterable(false);
            $this->inProcessObject->setIsFilterableInSearch(false);
            $this->inProcessObject->setUsedForSortBy(false);
            
            $this->inProcessObject->save();
        }
        if ($this->inProcessObject->getFrontendInput() == 'text') {
            $this->inProcessObject->setIsFilterable(false);
            $this->inProcessObject->setIsFilterableInSearch(false);

            $this->inProcessObject->save();
        }
        return parent::postProcessRow();
    }

    public function postProcess()
    {
        $this->handleAttributes();
        parent::postProcess();
    }

    /**
     * Create Object brick and Family.
     */
    protected function handleAttributes()
    {
        foreach ($this->csvDataByAttributeSet as $attributeSet => $rows) {
            $this->handleObjectBrick($attributeSet, $rows);
            $this->handleFamily($attributeSet);
        }
    }

    /**
     * Create Family object.
     *
     * @param $attributeSet
     *
     * @throws Exception
     */
    protected function handleFamily($attributeSet)
    {
        $family = FamilyHelper::getByCode($attributeSet);
        if (!$family) {
            $parentFolder = DataObject\Service::createFolderByPath(FamilyHelper::PARENT_FOLDER_FAMILY_KEY);
            $family = new DataObject\Family();
            $family
                ->setParent($parentFolder)
                ->setKey($attributeSet)
                ->setFamilyCode($attributeSet)
                ->setName($attributeSet)
                ->setObjectBrickKey(BrickHelper::getBrickKeyFromAttributeSet($attributeSet))
                ->setPublished(true)
                ->save();
        }
    }

    /**
     * Create / Update ObjectBrick
     *
     * @param $attributeSet
     * @param $rows
     *
     * @throws Exception
     */
    protected function handleObjectBrick($attributeSet, $rows)
    {
        $attributes = [];
        foreach ($rows as $row) {
            $fieldType = isset(BrickHelper::FIELD_TYPE_MAPPING[$row['frontend_input']])
                ? BrickHelper::FIELD_TYPE_MAPPING[$row['frontend_input']]
                : 'input';
            $attributes[] = [
                'group' => $row['group'],
                'name' => $row['attribute_code'],
                'title' => $row['frontend_label'],
                'fieldType' => $fieldType,
                'options' => $row['attribute_options']
            ];
        }
        if (count($attributes)) {
            BrickHelper::create($attributeSet, $attributes);
            $this->vMessage('Création de la brick ' . $attributeSet . ' : ' . implode(',', $attributes));
        }
    }
}
