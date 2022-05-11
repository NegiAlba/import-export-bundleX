<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2016 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Template\Importer;

use Galilee\ImportExportBundle\Helper\BrickHelper;
use Galilee\ImportExportBundle\Helper\PimHelper;
use Galilee\ImportExportBundle\Helper\Tools;
use Pimcore\Model\DataObject;

class ProductPim extends Product
{

    const SELECT_FALSE = 2;
    const SELECT_TRUE = 1;

    public $loggerComponent = 'Import des produits PIM SOCODA';

    public $mandatoryFields = [
        'sku',
        'attribute_set_code',
        'product_type',
        'name',
        'id_socoda'
    ];

    public $mapping = [
        'sku' => 'sku',
        'attribute_set_code' => ['family', 'setFamily'],
        'product_type' => null,
        //'productType', // not exists in Pimcore
        'categories' => ['path_categories', 'setCategories'],
        'id_categories' => ['categories', 'setCategories'],
        'reset_category' => null,
        // not persisted in Pimcore
        'product_websites' => null,
        // not exists in Pimcore
        'product_online' => null,
        // Ignored for Pim Socoda. See handleAvailability
        'name' => 'name',
        'description' => ['description', 'setHtmlVal'],
        'short_description' => 'short_description',
        'price' => null,
        // Ignored for Pim Socoda
        'tax_class_name' => null,
        // not exists in Pimcore
        'visibility' => null,
        // not exists in Pimcore
        'meta_title' => 'metaTitle',
        'meta_keyword' => 'metaKeyword',
        'meta_description' => 'metaDescription',
        'weight' => null,
        // not exists in Pimcore
        'base_image' => ['images', 'handleImages', ['additional_images']],
        'thumbnail_image' => null,
        // not exists in Pimcore
        'small_image' => null,
        // not exists in Pimcore
        'additional_images' => ['images', 'handleImages'],
        // cf. base_image
        'qty' => ['qty', 'setFloatVal'],
        'url_key' => 'urlKey',
        'is_in_stock' => 'inStock',
        'associated_skus' => null,
        // ['associatedProduct', 'multiHrefObject', ['Product', 'sku', self::FIELD_VALUE_SEPARATOR]],
        'use_config_enable_qty_inc' => null,
        // not exists in Pimcore
        'enable_qty_increments' => null,
        // not exists in Pimcore
        'qty_increments' => 'packaging',
        'related_skus' => null,
        // not exists in Pimcore
        'crosssell_skus' => ['crossSelling', 'multiHrefObject', ['Product', 'sku', self::FIELD_VALUE_SEPARATOR]],
        'upsell_skus' => ['upSelling', 'multiHrefObject', ['Product', 'sku', self::FIELD_VALUE_SEPARATOR]],
        'id_socoda' => null,
        'wait_import_socoda' => null,
        'status_import_socoda' => null,
        'category_import_socoda' => null,

        // Predefined Attributes
        'supplier' => ['supplier', 'setSupplier'],
        'brand_name' => ['brand', 'setBrand'],
        'quantity_price' => 'quantity_price',
        'quantity_price_type' => ['quantityPriceType', 'setQuantityPriceType'],

        'reset_images' => ['resetImages', 'handleResetImages'],

        // All other columns
        '*' => ['attributes', 'handleAllAttributes']
    ];
    /**
     * @var bool
     */
    protected $isImagesProcessed = false;

    const WARN_PIM_NOT_FOUND = 'PIM_NOT_FOUND';
    const WARN_PIM_ID_NOT_MATCH = 'PIM_ID_NOT_MATCH';
    const WARN_PIM_ID_MISSING = 'PIM_ID_MISSING';
    const WARN_PIM_NOT_SYNC = 'PIM_NOT_SYNC';
    const WARN_PRODUCT_IMAGE_COULD_NOT_BE_DELETED = 'WARN_PRODUCT_IMAGE_COULD_NOT_BE_DELETED';
    const WARN_PRODUCT_RESET_IMAGES_ATTRIBUTE_INVALID = 'WARN_PRODUCT_RESET_IMAGES_ATTRIBUTE_INVALID';

    protected $warningMessages = [
        self::WARN_PIM_NOT_FOUND => 'Produit non trouvé',
        self::WARN_PIM_ID_NOT_MATCH => 'Id Socoda non identique',
        self::WARN_PIM_ID_MISSING => 'Id Socoda manquant',
        self::WARN_PIM_NOT_SYNC => 'Statut de synchronisation "Ne pas synchroniser"',
        self::WARN_PRODUCT_RESET_IMAGES_ATTRIBUTE_INVALID => 'Valeur incorrecte dans la colonne reset_images',
        self::WARN_PRODUCT_IMAGE_COULD_NOT_BE_DELETED => 'Une image n\'a pas pu être supprimée',

        self::WARN_PRODUCT_FAMILY_NOT_FOUND => 'Famille non trouvée',
        self::WARN_PRODUCT_FAMILY_NOT_VALID => 'Famille non valide',
        self::WARN_PRODUCT_ATTRIBUTE_NOT_FOUND => 'Attribut non trouvé dans la famille',
        self::WARN_PRODUCT_CATEGORY_CODE_NOT_FOUND => 'Code catégorie non trouvé',
        self::WARN_PRODUCT_CATEGORY_NAME_NOT_FOUND_IN_PARENT => 'Catégorie non trouvée dans le parent',
    ];

    /**
     * Don't create new product from Pim socoda.
     *
     * @param array $csvRow
     * @param int $csvLineNumber
     *
     * @return bool If false the row is ignored
     */
    protected function initObject($csvRow, $csvLineNumber)
    {
        // Don't create new product from Pim Socoda import
        if (!$this->initProduct($csvRow, false)) {
            $this->logWarning(self::WARN_PIM_NOT_FOUND,
                'SKU : ' . $csvRow['sku'],
                $csvLineNumber);
            return false;
        }

        $csvIdSocoda = isset($csvRow[self::COL_ID_SOCODA]) ? trim($csvRow[self::COL_ID_SOCODA]) : '';

        if ($csvIdSocoda == '') {
            $this->logWarning(self::WARN_PIM_ID_MISSING,
                'SKU : ' . $this->inProcessObject->getSku(),
                $csvLineNumber);
            return false;
        }

        if ($this->inProcessObject->getIdSocoda() != $csvIdSocoda) {
            $this->logWarning(self::WARN_PIM_ID_NOT_MATCH,
                'SKU : ' . $this->inProcessObject->getSku() . ' Csv : ' . $csvIdSocoda . ' - Produit : ' . $this->inProcessObject->getIdSocoda(),
                $csvLineNumber);
            return false;
        }

        // Don't import product if status_import_socoda = 4 (no sync)
        if ($this->inProcessObject->getStatusImportSocoda() == PimHelper::STATUS_IMPORT_SOCODA_NO_SYNC) {
            $this->logWarning(
                self::WARN_PIM_NOT_SYNC,
                'SKU : ' . $this->inProcessObject->getSku(),
                $csvLineNumber
            );
            return false;
        }
        $this->handleAvailability();
        $this->handleStatusImportSocoda();
        // todo Called twice (already called in initProduct)
        $this->setParentProduct($csvRow);
        $this->checkResetImages($csvRow);
        $this->isCategoriesProcessed = false;
        $this->isImagesProcessed = false;
        return true;
    }


    protected function handleAvailability(
        string $objectFieldName = '',
        string $csvValue = ''
    )
    {
        // Enable product if status_import_socoda = 1 (init) and wait_import_socoda = true
        if ($this->inProcessObject->getStatusImportSocoda() == PimHelper::STATUS_IMPORT_SOCODA_INIT
            && $this->inProcessObject->getWaitImportSocoda() == true
        ) {
            if ($this->inProcessObject->getAvailability() === self::SELECT_FALSE) {
                $this->inProcessObject->setAvailability(self::SELECT_TRUE);
                $this->setIsUpdated(true);
            }
        }
    }

    protected function handleStatusImportSocoda(
        string $objectFieldName = '',
        string $csvValue = ''
    )
    {
        $prev = $this->inProcessObject->getStatusImportSocoda();
        if ($prev != PimHelper::STATUS_IMPORT_SOCODA_SYNC) {
            // Set status_import_socoda to synchronized (2)
            $this->inProcessObject->setStatusImportSocoda(PimHelper::STATUS_IMPORT_SOCODA_SYNC);
            $this->setIsUpdated(true);
            $this->statusNotes[] = [
                'typeImport' => PimHelper::NOTE_TYPE_IMPORT_PIM,
                'status' => PimHelper::STATUS_IMPORT_SOCODA_SYNC
            ];
        }
    }

    protected function getCategoriesValueSeparator()
    {
        return '|';
    }

    /**
     * @param string $objectFieldName
     * @param string $csvValue
     *
     * @return bool
     * @throws \Exception
     */
    protected function setCategories(
        string $objectFieldName,
        string $csvValue
    )
    {
        // Don't create Pim Socoda Categories if category_import_socoda = 0
        if ($this->inProcessObject->getCategoryImportSocoda() !== true) {
            $this->isCategoriesProcessed = true;
            return false;
        }

        return parent::setCategories($objectFieldName, $csvValue);
    }

    /**
     * @param string $objectFieldName
     * @param string $csvValue
     * @param string|null $additionalCsvKey
     *
     * @return bool Updated ?
     * @throws \Exception
     */
    protected function handleImages(
        string $objectFieldName,
        string $csvValue,
        string $additionalCsvKey = null
    ): bool
    {
        if (!$this->isImagesProcessed) {
            // base_images + additional_images
            $imagesString[] = $csvValue;
            if (!is_null($additionalCsvKey) && isset($this->csvRow[$additionalCsvKey])) {
                $imagesString[] = $this->csvRow[$additionalCsvKey];
            }

            // isImageProcessed is here to prevent reading twice additionalImage if base and additional was in import
            $this->isImagesProcessed = true;
            return $this->multiHrefAsset(
                $objectFieldName,
                implode('|', $imagesString),
                '|'
            );
        }
        return false;
    }

    protected function setParentProduct(array $row)
    {
        // Ignore Variant (simple of grouped product)
        if ($this->inProcessObject->getType() != DataObject\AbstractObject::OBJECT_TYPE_VARIANT) {

            $previousParentId = $this->inProcessObject->getParent()->getId();
            $newParent = $this->parentFolder;

            // Famille
            $attributeSet = isset($row['attribute_set_code']) ? $row['attribute_set_code'] : '';
            if ($attributeSet !== '') {
                /** @var DataObject\Family $family */
                $family = DataObject\Family::getByFamilyCode(Tools::normalize($attributeSet), 1);
                if ($family != null) {
                    $newParent = $family;
                }
            }

            if ($newParent->getId() != $previousParentId) {
                $this->inProcessObject->setParent($newParent);
                $this->setIsUpdated(true);
            }
        }

        return true;
    }

    protected function handleAllAttributes(
        string $objectFieldName,
        string $csvValue,
        array $attributeFields
    ): bool
    {
        $updated = false;
        $brickName = BrickHelper::getBrickKeyFromAttributeSet($this->csvRow['attribute_set_code']);
        $brickGetter = 'get' . ucfirst($brickName);
        $brickSetter = 'set' . ucfirst($brickName);
        $objectBrick = $this->inProcessObject->getAttributes()->$brickGetter();
        if (!$objectBrick) {
            return false;
        }
        $brickClassName = '\\Pimcore\\Model\\DataObject\\Objectbrick\\Data\\' . ucfirst($brickName);
        $newObjectBrick = new $brickClassName($this->inProcessObject, 'attributes');
        $newObjectBrick->setFieldname('attributes');

        // Default
        $defaultKey = BrickHelper::getBrickKeyFromAttributeSet(BrickHelper::DEFAULT);
        $defaultBrickGetter = 'get' . ucfirst($defaultKey);
        $defaultBrickSetter = 'set' . ucfirst($defaultKey);
        $defaultObjectBrick = $this->inProcessObject->getAttributes()->$defaultBrickGetter();
        $newDefaultObjectBrick = clone($defaultObjectBrick);


        foreach ($attributeFields as $attribute) {
            $value = $this->csvRow[$attribute];
            // mettre à jour default + spécifique
            if ($value != '') {

                $result = $this->updateObjectBrickAttribute($objectBrick, $newObjectBrick, $attribute, $value, false);
                $resultDefault = $this->updateObjectBrickAttribute($defaultObjectBrick, $newDefaultObjectBrick,
                    $attribute, $value, false);
                if (is_null($result) && is_null($resultDefault)) {

                    $this->logWarning(
                        self::WARN_PRODUCT_ATTRIBUTE_NOT_FOUND,
                        'Attribut : ' . $attribute . ' - Famille ' . $objectBrick->getType(),
                        $this->line
                    );
                    continue;
                }
                if ($result === true || $resultDefault === true) {
                    $updated = true;
                }
            }
        }

        if ($updated === true) {
            $this->inProcessObject->getAttributes()->$brickSetter($newObjectBrick);
            $this->inProcessObject->getAttributes()->$defaultBrickSetter($newDefaultObjectBrick);
        }

        return $updated;
    }

    protected function getChunkCommand($from, $to)
    {
        return sprintf('php bin/console galilee:import -t %s --from=%d --to=%d &' . PHP_EOL, $this->getType(), $from,
            $to);
    }


    /**
     * if no image to import, in case of reset_images we need to delete old ones
     * @param array $csvRow
     * @return bool
     */
    protected function hasImagesToImport(array $csvRow)
    {
        // current import does'nt import small_image and thumbnail_image
        return (isset($csvRow['base_image']) && !empty($csvRow['base_image']))
            || (isset($csvRow['additional_images']) && !empty($csvRow['additional_images']));
    }

}
