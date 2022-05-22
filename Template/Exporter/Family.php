<?php
/**
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2017 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Template\Exporter;

use Exception;
use Galilee\ImportExportBundle\Helper\AttributeHelper;
use Galilee\ImportExportBundle\Helper\BrickHelper;
use Galilee\ImportExportBundle\Helper\CsvWriter;
use Galilee\ImportExportBundle\Processor\Exporter\AbstractExporter;
use Pimcore\Model\DataObject;

class Family extends AbstractExporter
{
    public $exportFileName = 'family.csv';

    public $columnNames = [
        'attribute_code',
        'frontend_input',
        'frontend_label',
        'is_unique',
        'is_required',
        'is_searchable',
        'is_visible_in_advanced_search',
        'is_comparable',
        'is_filterable',
        'is_filterable_in_search',
        'is_used_for_promo_rules',
        'is_wysiwyg_enabled',
        'is_html_allowed_on_front',
        'is_visible_on_front',
        'used_in_product_listing',
        'used_for_sort_by',
        'attribute_options',
        'default_value',
        'group',
        'attribute_set',
    ];

    public $loggerComponent = 'Export des familles (attributs)';

    protected $manufacturerOptions = '';
    protected $brandOptions = '';
    protected $quantityPriceTypeOptions = '';
    protected $newItemUnitOptions = '';
    protected $newItemExtCategoryIdOptions = '';
    protected $packagingUnitOptions = '';

    /**
     * @throws Exception
     */
    public function process()
    {
        $data = $this->getData();
        $csvWriter = new CsvWriter($this->getCsvFileFullPath());
        $csvWriter->addRow($this->columnNames);
        foreach ($data as $row) {
            $csvWriter->addRow($row);
        }
        $csvWriter->close();
        $this->writeInfo('Fichier d\'export  : '.$this->getCsvFileFullPath());
    }

    /**
     * @return array
     *
     * @throws Exception
     */
    protected function getData()
    {
        $data = [];
        $defaultKey = BrickHelper::getBrickKeyFromAttributeSet(BrickHelper::DEFAULT);
        $defaultBrick = null;
        $list = new DataObject\Objectbrick\Definition\Listing();
        $list = $list->load();
        $bricks = [];

        // Place Default attribute set first.
        foreach ($list as $brick) {
            if ($brick->getKey() == $defaultKey) {
                array_unshift($bricks, $brick);
                $defaultBrick = $brick;
            } else {
                $bricks[] = $brick;
            }
        }

        foreach ($bricks as $brick) {
            $attributeSet = $brick->getTitle();
            $currentData = array_merge(
                [$this->getManufacturerRow($attributeSet)],
                [$this->getBrandRow($attributeSet)],
                [$this->getQuantityPriceTypeRow($attributeSet)],
                [$this->getQuantityPriceRow($attributeSet)],
                [$this->getNewItemUnitRow($attributeSet)],
                [$this->getNewItemExtCategoryIdRow($attributeSet)],
                [$this->getNumberPiecesPackaging($attributeSet)],
                [$this->getPackagingUnitRow($attributeSet)],
                [$this->getPcre($attributeSet)],
                $this->getObjectBrickRows($brick)
            );

            // Add default attributes set attributes if not exists in current attribute set.
            if ($brick->getKey() !== $defaultKey && $defaultBrick) {
                $defaultData = $this->getObjectBrickRows($defaultBrick, $attributeSet);

                foreach ($defaultData as $defaultAttribute) {
                    $exists = false;
                    for ($i = 0; $i < count($currentData) && !$exists; ++$i) {
                        var_dump($currentData[$i]);
                        if (array_key_exists('attribute_code', $currentData[$i])) {
                            if ($currentData[$i]['attribute_code'] == $defaultAttribute['attribute_code']) {
                                $exists = true;
                            }
                        }
                    }
                    if (!$exists) {
                        $currentData[] = $defaultAttribute;
                    }
                }
            }

            $data = array_merge($data, $currentData);
        }

        return $data;
    }

    /**
     * @param string|null $attributeSet
     */
    protected function getObjectBrickRows(DataObject\Objectbrick\Definition $brick, $attributeSet = null): array
    {
        $fields = [];
        $attributeSet = $attributeSet ?: $brick->getTitle();
        /** @var DataObject\ClassDefinition\Layout $layout */
        $layout = $brick->getLayoutDefinitions();
        $objectBrickFields = BrickHelper::getRecursiveFields($layout);
        foreach ($objectBrickFields as $brickField) {
            $field = $brickField['field'];
            $row = $this->getAttributeRow(
                $field->getName(),
                $attributeSet,
                $brickField['group'],
                BrickHelper::getOptionsFromFields($field)
            );

            if (!$row) {
                $row = AttributeHelper::DEFAULT_ATTRIBUTE_VALUE;
                $row['attribute_code'] = $field->getName();
                $row['frontend_label'] = $field->getTitle();
                $row['group'] = $brickField['group'];
                $row['attribute_options'] = BrickHelper::getOptionsFromFields($field);
                $this->writeWarning('Aucun objet Attribute trouvé pour "'.$brickField['field']->getName().'". Les valeurs par défaut ont été exportées.');
            }
            $fields[] = $row;
        }

        return $fields;
    }

    /**
     * @return array|null
     */
    protected function getAttributeRow(
        string $attributeCode,
        string $attributeSet,
        string $group = null,
        string $attributeOptions = ''
    ) {
        $result = null;
        $attribute = AttributeHelper::getByCode($attributeCode);
        if ($attribute) {
            $result = [
                'attribute_code' => $attributeCode,
                'frontend_input' => $attribute->getFrontendInput(),
                'frontend_label' => $attribute->getFrontendLabel(),
                'is_unique' => $attribute->getIsRequired(),
                'is_required' => $attribute->getIsRequired(),
                'is_searchable' => $attribute->getIsSearchable(),
                'is_visible_in_advanced_search' => $attribute->getIsVisibleInAdvancedSearch(),
                'is_comparable' => $attribute->getIsComparable(),
                'is_filterable' => $attribute->getIsFilterable(),
                'is_filterable_in_search' => $attribute->getIsFilterableInSearch(),
                'is_used_for_promo_rules' => $attribute->getIsUsedForPromoRules(),
                'is_wysiwyg_enabled' => $attribute->getIsWysiwygEnabled(),
                'is_html_allowed_on_front' => $attribute->getIsHtmlAllowedOnFront(),
                'is_visible_on_front' => $attribute->getIsVisibleOnFront(),
                'used_in_product_listing' => $attribute->getUsedInProductListing(),
                'used_for_sort_by' => $attribute->getUsedForSortBy(),
                'attribute_options' => $attributeOptions,
                'default_value' => $attribute->getDefaultValue(),
                'group' => $group ?: $attribute->getGroup(),
                'attribute_set' => $attributeSet,
            ];
        }

        return $result;
    }

    /**
     * @param $attributeSet
     *
     * @return array|null
     *
     * @throws Exception
     */
    protected function getManufacturerRow(string $attributeSet): array
    {
        if (!$this->manufacturerOptions) {
            $list = new DataObject\Supplier\Listing();
            $attributeOptions = [];
            foreach ($list as $supplier) {
                $attributeOptions[] = $supplier->getName();
            }
            $this->manufacturerOptions = implode(
                $this->getDefaultMultiValueSeparator(),
                $attributeOptions
            );
        }

        return $this->getAttributeRow(
            AttributeHelper::MANUFACTURER_CODE,
            $attributeSet,
            null,
            $this->manufacturerOptions
        );
    }

    /**
     * @param $attributeSet
     *
     * @return array|null
     *
     * @throws Exception
     */
    protected function getBrandRow(string $attributeSet): array
    {
        if (!$this->brandOptions) {
            $list = new DataObject\Brand\Listing();
            $attributeOptions = [];
            foreach ($list as $brand) {
                $attributeOptions[] = $brand->getName();
            }
            $this->brandOptions = implode(
                $this->getDefaultMultiValueSeparator(),
                $attributeOptions
            );
        }

        return $this->getAttributeRow(
            AttributeHelper::BRAND_CODE,
            $attributeSet,
            null,
            $this->brandOptions
        );
    }

    /**
     * @param $attributeSet
     *
     * @throws Exception
     */
    protected function getQuantityPriceTypeRow(string $attributeSet): array
    {
        if (!$this->quantityPriceTypeOptions) {
            $quantityPriceTypelist = new DataObject\QuantityPriceType\Listing();
            $attributeOptions = [];
            foreach ($quantityPriceTypelist as $quantityPriceType) {
                $attributeOptions[] = $quantityPriceType->getLabel();
            }
            $this->quantityPriceTypeOptions = implode(
                $this->getDefaultMultiValueSeparator(), $attributeOptions
            );
        }

        return $this->getAttributeRow(
            'quantity_price_type',
            $attributeSet,
            null,
            $this->quantityPriceTypeOptions
        );
    }

    protected function getQuantityPriceRow(string $attributeSet): array
    {
        return $this->getAttributeRow(
            'quantity_price',
            $attributeSet
        );
    }

    /**
     * @throws Exception
     */
    protected function getNewItemUnitRow(string $attributeSet): array
    {
        if (!$this->newItemUnitOptions) {
            $list = new DataObject\NewItemUnit\Listing();
            $attributeOptions = [];
            foreach ($list as $newItemUnit) {
                $attributeOptions[] = $newItemUnit->getLabel();
            }
            $this->newItemUnitOptions = implode(
                $this->getDefaultMultiValueSeparator(),
                $attributeOptions
            );
        }

        return $this->getAttributeRow(
            'new_item_unit',
            $attributeSet,
            null,
            $this->newItemUnitOptions
        );
    }

    /**
     * @throws Exception
     */
    protected function getNewItemExtCategoryIdRow(string $attributeSet): array
    {
        if (!$this->newItemExtCategoryIdOptions) {
            $list = new DataObject\NewItemExtCategoryId\Listing();
            $attributeOptions = [];
            foreach ($list as $newItemExtCategoryId) {
                $attributeOptions[] = $newItemExtCategoryId->getLabel();
            }
            $this->newItemExtCategoryIdOptions = implode(
                $this->getDefaultMultiValueSeparator(),
                $attributeOptions
            );
        }

        return $this->getAttributeRow(
            'new_item_ext_category_id',
            $attributeSet,
            null,
            $this->newItemExtCategoryIdOptions
        );
    }

    /**
     * @throws Exception
     */
    protected function getNumberPiecesPackaging(string $attributeSet): array
    {
        return $this->getAttributeRow(
            'number_pieces_packaging',
            $attributeSet
        );
    }

    /**
     * @throws Exception
     */
    protected function getPackagingUnitRow(string $attributeSet): array
    {
        if (!$this->packagingUnitOptions) {
            $list = new DataObject\PackagingUnit\Listing();
            $attributeOptions = [];
            foreach ($list as $packagingUnit) {
                $attributeOptions[] = $packagingUnit->getLabel();
            }
            $this->packagingUnitOptions = implode(
                $this->getDefaultMultiValueSeparator(),
                $attributeOptions
            );
        }

        return $this->getAttributeRow(
            'packaging_unit',
            $attributeSet,
            null,
            $this->packagingUnitOptions
        );
    }

    protected function getPcre(string $attributeSet): array
    {
        return $this->getAttributeRow(
            'pcre',
            $attributeSet
        );
    }
}