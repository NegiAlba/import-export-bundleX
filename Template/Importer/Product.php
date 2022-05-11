<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2016 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Template\Importer;

use Carbon\Carbon;
use Galilee\ImportExportBundle\Helper\BrickHelper;
use Galilee\ImportExportBundle\Helper\ObjectHelper;
use Galilee\ImportExportBundle\Helper\PimHelper;
use Galilee\ImportExportBundle\Helper\Tools;
use Galilee\ImportExportBundle\Processor\Importer\AbstractImporter;
use Pimcore\Config;
use Pimcore\File;
use Pimcore\Model\Asset\Image;
use Pimcore\Model\DataObject;
use Symfony\Component\Process\Process;

class Product extends AbstractImporter
{

    const COL_ID_SOCODA = 'id_socoda';

    const CHUNK = 500;
    const PARALLEL_IMPORT = 4;

    const FIELD_VALUE_SEPARATOR = ',';
    const PARENT_FOLDER_KEY = 'products';
    public $parentFolder;

    const PRICE = "price";

    const SELECT_FALSE = 2;
    const SELECT_TRUE = 1;

    const PRICE_SETTING = "nePasImporterProduitsAvecPrixAZero";

    /**
     * @var DataObject\Product
     */
    protected $inProcessObject;

    /**
     * @var bool
     */
    protected $isCategoriesProcessed;

    public $countProductWithNoPrice = 0;

    public $mandatoryFields = [
        'sku',
        'name',
        'price',
        'family',
        'in_stock',
        'availability',
    ];

    /**
     * @var array
     * {csv column} => {object field}
     * or
     * {csv column} => array({object field}, {method}, array {arguments})
     */
    public $mapping = [
        'sku' => 'sku',
        'name' => 'name',
        'designation' => 'designation',
        'short_description' => 'short_description',
        'description' => array('description', 'setHtmlVal'),
        'url_key' => 'urlKey',
        'code_client' => 'codeClient',
        'code_socoda' => 'codeSocoda',
        'price' => array('price', 'setFloatVal'),
        'tva' => array('tva', 'setFloatVal'),
        'meta_title' => 'metaTitle',
        'meta_keyword' => 'metaKeyword',
        'meta_description' => 'metaDescription',
        'images' => array('images', 'multiHrefAsset', array(self::FIELD_VALUE_SEPARATOR)),
        'documents' => array('documents', 'multiHrefAsset', array(self::FIELD_VALUE_SEPARATOR)),
        'in_stock' => 'inStock',
        'quantity' => array('qty', 'setFloatVal'),
        'packaging' => 'packaging',
        'availability' => ['availability', 'handleAvailability'],
        'order' => 'position',
        'quantity_price' => 'quantity_price',

        'categories' => [
            'categories',
            'setCategories',
        ],

        'path_categories' => [
            'path_categories',
            'setCategories',
        ],

        'reset_category' => null,

        'associated_product' => array(
            'associatedProduct',
            'multiHrefObject',
            array(
                'Product', // Class name of linked object
                'sku', // UID
                self::FIELD_VALUE_SEPARATOR
            )
        ),

        'up_selling' => array(
            'upSelling',
            'multiHrefObject',
            array(
                'Product', // Class name of linked object
                'sku', // UID
                self::FIELD_VALUE_SEPARATOR
            )
        ),

        'cross_selling' => array(
            'crossSelling',
            'multiHrefObject',
            array(
                'Product', // Class name of linked object
                'sku', // UID
                self::FIELD_VALUE_SEPARATOR
            )
        ),

        'supplier' => array(
            'supplier',
            'setSupplier'
        ),

        'brand' => array(
            'brand',
            'setBrand'
        ),

        'quantity_price_type' => [
            'quantityPriceType',
            'setQuantityPriceType'
        ],

        'websites' => [
            'websites',
            'setWebsites'
        ],

        'store_views' => [
            'storeViews',
            'setStoreViews'
        ],

        'id_socoda' => 'idSocoda',
        'wait_import_socoda' => 'waitImportSocoda',
        'status_import_socoda' => ['statusImportSocoda', 'handleStatusImportSocoda'],
        'category_import_socoda' => 'categoryImportSocoda',
        'new_item_unit' => ['newItemUnit', 'setNewItemUnit'],
        'new_item_ext_category_id' => ['newItemExtCategoryId', 'setNewItemExtCategoryId'],
        'family' => ['family', 'setFamily'],
        'attributes' => ['attributes', 'handleAttributes'],
        'special_price' => 'specialPrice',
        'special_from_date' => ['specialFromDate', 'setSpecialFromDate'],
        'special_to_date' => ['specialToDate', 'setSpecialToDate'],
        'reset_images' => ['resetImages', 'handleResetImages'],
        'number_pieces_packaging' => 'numberPiecesPackaging',
        'packaging_unit' => ['packagingUnit', 'setPackagingUnit'],
        'pcre' => ['pcre', 'handlePcre']
    ];

    public $loggerComponent = 'Import des produits';

    const WARN_PRODUCT_ZERO_PRICE = 'WARN_PRODUCT_ZERO_PRICE';
    const WARN_PRODUCT_FAMILY_NOT_FOUND = 'WARN_PRODUCT_FAMILY_NOT_FOUND';
    const WARN_PRODUCT_FAMILY_NOT_VALID = 'WARN_PRODUCT_FAMILY_NOT_VALID';
    const WARN_PRODUCT_ATTRIBUTE_NOT_FOUND = 'WARN_PRODUCT_ATTRIBUTE_NOT_FOUND';
    const WARN_PRODUCT_CATEGORY_CODE_NOT_FOUND = 'WARN_PRODUCT_CATEGORY_CODE_NOT_FOUND';
    const WARN_PRODUCT_CATEGORY_NAME_NOT_FOUND_IN_PARENT = 'WARN_PRODUCT_CATEGORY_NAME_NOT_FOUND_IN_PARENT';
    const WARN_PRODUCT_WEBSITE_NOT_FOUND = 'WARN_PRODUCT_WEBSITE_NOT_FOUND';
    const WARN_PRODUCT_WEBSITE_NO_STOREVIEW_SET = 'WARN_PRODUCT_WEBSITE_NO_STOREVIEW_SET';
    const WARN_PRODUCT_STOREVIEW_NOT_FOUND = 'WARN_PRODUCT_STOREVIEW_NOT_FOUND';
    const WARN_PRODUCT_STOREVIEW_NOT_FOUND_IN_WEBSITE = 'WARN_PRODUCT_STOREVIEW_NOT_FOUND_IN_WEBSITE';
    const WARN_PRODUCT_WEBSITE_STOREVIEW_BOTH_MUST_BE_SET = 'WARN_PRODUCT_WEBSITE_STOREVIEW_BOTH_MUST_BE_SET';
    const WARN_PRODUCT_EIFFAGE_ATTRIBUTE_BOTH_MUST_BE_SET = 'WARN_PRODUCT_EIFFAGE_ATTRIBUTE_BOTH_MUST_BE_SET';
    const WARN_PRODUCT_EIFFAGE_ATTRIBUTE_NEWITEMUNIT_NOT_FOUND = 'WARN_PRODUCT_EIFFAGE_ATTRIBUTE_NEWITEMUNIT_NOT_FOUND';
    const WARN_PRODUCT_EIFFAGE_ATTRIBUTE_NEWITEMEXTCATEGORYID_NOT_FOUND = 'WARN_PRODUCT_EIFFAGE_ATTRIBUTE_NEWITEMEXTCATEGORYID_NOT_FOUND';
    const WARN_PRODUCT_SPECIAL_FROM_DATE_INVALID = 'WARN_PRODUCT_SPECIAL_FROM_DATE_INVALID';
    const WARN_PRODUCT_SPECIAL_TO_DATE_INVALID = 'WARN_PRODUCT_SPECIAL_TO_DATE_INVALID';
    const WARN_PRODUCT_IMAGE_COULD_NOT_BE_DELETED = 'WARN_PRODUCT_IMAGE_COULD_NOT_BE_DELETED';
    const WARN_PRODUCT_RESET_IMAGES_ATTRIBUTE_INVALID = 'WARN_PRODUCT_RESET_IMAGES_ATTRIBUTE_INVALID';
    const WARN_PRODUCT_ID_SOCODA_IS_NOT_UNIQUE = 'WARN_PRODUCT_ID_SOCODA_IS_NOT_UNIQUE';
    const WARN_PRODUCT_EIFFAGE_ATTRIBUTE_PACKAGINGUNIT_NOT_FOUND = 'WARN_PRODUCT_EIFFAGE_ATTRIBUTE_PACKAGINGUNIT_NOT_FOUND';
    const WARN_PRODUCT_PARENT_ATTRIBUTE_SET_NOT_IDENTICAL = 'WARN_PRODUCT_PARENT_ATTRIBUTE_SET_NOT_IDENTICAL';

    // used as static message, therefore can't enter $warningMessage
    const WARN_ROW_ITEM_ASSET_ORDER_CHANGED = 'No asset deleted or added but changed asset order for product.';

    protected $warningMessages = [
        self::WARN_PRODUCT_ZERO_PRICE => 'Prix produit à 0',
        self::WARN_PRODUCT_FAMILY_NOT_FOUND => 'Famille non trouvée',
        self::WARN_PRODUCT_FAMILY_NOT_VALID => 'Famille non valide',
        self::WARN_PRODUCT_ATTRIBUTE_NOT_FOUND => 'Attribut non trouvé dans la famille',
        self::WARN_PRODUCT_CATEGORY_CODE_NOT_FOUND => 'Code catégorie non trouvé',
        self::WARN_PRODUCT_CATEGORY_NAME_NOT_FOUND_IN_PARENT => 'Catégorie non trouvée dans le parent',
        self::WARN_PRODUCT_WEBSITE_NOT_FOUND => 'Website non trouvé',
        self::WARN_PRODUCT_WEBSITE_NO_STOREVIEW_SET => 'Pas de store view importé pour ce website',
        self::WARN_PRODUCT_STOREVIEW_NOT_FOUND => 'StoreView non trouvé',
        self::WARN_PRODUCT_STOREVIEW_NOT_FOUND_IN_WEBSITE => 'StoreView non trouvé dans le(s) website(s)',
        self::WARN_PRODUCT_WEBSITE_STOREVIEW_BOTH_MUST_BE_SET => 'Couple website et store_view incomplet',
        self::WARN_PRODUCT_EIFFAGE_ATTRIBUTE_BOTH_MUST_BE_SET => 'Attribut Eiffage incomplet',
        self::WARN_PRODUCT_EIFFAGE_ATTRIBUTE_NEWITEMUNIT_NOT_FOUND => 'Unité de vente Eiffage non trouvée',
        self::WARN_PRODUCT_EIFFAGE_ATTRIBUTE_NEWITEMEXTCATEGORYID_NOT_FOUND => 'Catégorie Eiffage non trouvée',
        self::WARN_PRODUCT_SPECIAL_FROM_DATE_INVALID => 'Prix spécial depuis invalide',
        self::WARN_PRODUCT_SPECIAL_TO_DATE_INVALID => 'Prix spécial jusqu\'a invalide',
        self::WARN_PRODUCT_RESET_IMAGES_ATTRIBUTE_INVALID => 'Valeur incorrecte dans la colonne reset_images',
        self::WARN_PRODUCT_IMAGE_COULD_NOT_BE_DELETED => 'Une image n\'a pas pu être supprimée',
        self::WARN_PRODUCT_ID_SOCODA_IS_NOT_UNIQUE => "L'attribut id_socoda n'est pas unique",
        self::WARN_PRODUCT_EIFFAGE_ATTRIBUTE_PACKAGINGUNIT_NOT_FOUND => 'Unité de conditionnement non trouvée',
        self::WARN_PRODUCT_PARENT_ATTRIBUTE_SET_NOT_IDENTICAL => 'Famille parent/enfant non identique'
    ];

    /**
     * @var array
     */
    protected $statusNotes = [];

    /**
     * @var array
     */
    protected $uniqueAttributes = [];

    /**
     * @var array
     */
    protected $socodaIds = null;

    /**
     * @var bool
     */
    protected $isResetImages;

    public function process()
    {
        if (is_null($this->getFrom()) && is_null($this->getTo())) {
            $t1 = microtime(true);

            try {
                $csvReader = $this->getCsvReader();
                if (!$csvReader) {
                    return false;
                }
            } catch (\Exception $e) {
                return false;
            }
            $count = $csvReader->getCount();
            if ($count > self::CHUNK) {
                $this->processChunks($count);
                $duration = microtime(true) - $t1;
                $this->vMessage(sprintf("%f secondes", $duration));
            } else {
                parent::process();
            }
        } else {
            parent::process();
        }
    }

    protected function getChunkCommand($from, $to)
    {
        return sprintf('php bin/console galilee:import -t product --from=%d --to=%d &' . PHP_EOL, $from, $to);
    }


    protected function processChunks($totalLine)
    {
        $commandLines = [];
        $from = 1;
        $to = self::CHUNK;
        $chunkCount = ceil($totalLine / self::CHUNK);
        $p = 1;
        $cmd = '';
        for ($i = 1; $i <= $chunkCount; $i++) {
            $cmd .= $this->getChunkCommand($from, $to);
            if ($p == self::PARALLEL_IMPORT) {
                $p = 0;
                $commandLines[] = $cmd;
                $cmd = '';
            }
            $p++;
            $from = $to + 1;
            $to += self::CHUNK;
        }
        if ($p > 0) {
            $commandLines[] = $cmd;
        }

        foreach ($commandLines as $cmd) {
            $result = $this->runCmd($cmd);
        }

    }

    protected function runCmd($cmd)
    {
        $result = true;
        $this->vMessage('RUN PROCESSES...' . $cmd);
        $process = new Process($cmd);
        $process->setTimeout(60 * 60 * 24 * 4);

        $process->run(function ($type, $buffer) {
            if (Process::ERR === $type) {
                $this->vMessage('ERR > ' . $buffer);
            } else {
                $this->vMessage('OUT > ' . $buffer);
            }
        });

        if (!$process->isSuccessful()) {
            $this->writeError($process->getErrorOutput());
            $result = false;
        }

        return $result;
    }

    /**
     * @param array $csvRow
     * @param integer $csvLineNumber
     *
     * @return bool
     */
    protected function initObject($csvRow, $csvLineNumber)
    {
        $processRow = false;
        $dateError1 = $this->checkSpecialFromDate($csvRow);
        $dateError2 = $this->checkSpecialToDate($csvRow);
        $dateCheck = $dateError1 && $dateError2;

        if ($this->checkZeroPrice($csvRow, $csvLineNumber)
            && $this->initProduct($csvRow)
            && $this->checkEiffage($csvRow)
            && $this->checkWebsites($csvRow)
            && $this->checkResetImages($csvRow)
            && $dateCheck
            && $this->checkIdSocodaIsUnique($csvRow)
            && $this->checkParentAttributeSet($csvRow)) {
            $this->feedEmptyField($csvRow);
            $this->isCategoriesProcessed = false;
            $processRow = true;
        }
        return $processRow;
    }

    /**
     * If resetImages is true, we need to know it prior dealing with images
     *
     * @param array $csvRow
     *
     * @return bool
     */
    protected function checkResetImages(array $csvRow)
    {
        $this->isResetImages = false;
        if (isset($csvRow['reset_images']) && $csvRow['reset_images'] == 1) {
            $this->isResetImages = true;

            // if field is empty, we won't get to be in MhRef fn, which handle the reset, so in this case only we delete all
            if (!$this->hasImagesToImport($csvRow)) {

                $images = $this->inProcessObject->getImages();
                if ($images) {
                    /** @var Image $image */
                    foreach ($images as $image) {
                        try {
                            $fileName = $image->getFilename();
                            $image->delete();
                            $this->vMessage('  - deleted : ' . $fileName . ' for ' . $this->inProcessObject->getFullPath());
                        } catch (\Exception $e) {
                            $this->logWarning(self::WARN_PRODUCT_IMAGE_COULD_NOT_BE_DELETED,
                                'Image: ' . $image->getFullPath(),
                                $this->line
                            );
                        }
                    }
                    $this->inProcessObject->setImages(null);
                    $this->setIsUpdated(true);
                }
            }
        }
        return true;
    }


    protected function checkZeroPrice($csvRow, $csvLineNumber)
    {
        if (Config::getWebsiteConfig()->get(self::PRICE_SETTING, false)) {
            $price = $csvRow[self::PRICE];
            if ($price == 0) {
                $this->countProductWithNoPrice++;
                $this->logWarning(self::WARN_PRODUCT_ZERO_PRICE, 'SKU : ' . $csvRow['sku'], $csvLineNumber);
                return false;
            }
        }
        return true;
    }

    protected function initProduct($csvRow, $createIfNotExists = true)
    {
        $processRow = false;
        $sku = $csvRow['sku'];
        $this->inProcessObject = ObjectHelper::getProductWithVariantBy('sku', $sku);
        if ($this->inProcessObject) {
            $this->setMode(self::UPDATE_MODE);
        } elseif ($createIfNotExists) {
            $this->inProcessObject = new DataObject\Product();
            $this->inProcessObject->setSku($sku);
            $this->inProcessObject->setKey(File::getValidFilename($sku));
            $this->inProcessObject->setPublished(true);
        }

        if ($this->inProcessObject) {
            $this->initObjectMessage = 'Produit : ' . $this->inProcessObject->getFullPath();
            $this->setParentProduct($csvRow);
            $this->inProcessObject->setOmitMandatoryCheck(true);
            $processRow = true;
        }
        return $processRow;
    }


    /**
     * we skip inherited value to disable this feature on this field
     * @param string $objectFieldName
     * @param string $csvValue 0|1
     *
     * @return bool
     */
    protected function handleAvailability(
        string $objectFieldName = '',
        string $csvValue = ''
    )
    {
        $isUpdated = false;
        $csvConvertedValue = $csvValue === '1';
        $oldValue = $this->inProcessObject->getAvailability() === self::SELECT_TRUE;
        // Disable product if status_import_socoda = 1 (init) and wait_import_socoda = 1
        if ($this->getMode() == self::CREATE_MODE) {
            $statusImportSocoda = $this->csvRow['status_import_socoda'];
            $waitImportSocoda = (bool)$this->csvRow['wait_import_socoda'];
        } else {
            $statusImportSocoda = $this->inProcessObject->getStatusImportSocoda();
            $waitImportSocoda = $this->inProcessObject->getWaitImportSocoda();
        }
        if ($statusImportSocoda == PimHelper::STATUS_IMPORT_SOCODA_INIT
            && $waitImportSocoda !== false // null or true
        ) {
            if ($oldValue === true) {
                $this->inProcessObject->setAvailability(self::SELECT_FALSE);
                $isUpdated = true;
            }
        } else {
            $this->inProcessObject->setAvailability($csvConvertedValue ? self::SELECT_TRUE : self::SELECT_FALSE);
            $isUpdated = true;
        }
        return $isUpdated;
    }


    /**
     * @throws \Exception
     */
    public function preProcess()
    {
        $this->countProductWithNoPrice = 0;
        $this->parentFolder = DataObject\Service::createFolderByPath(self::PARENT_FOLDER_KEY);
        return parent::preProcess();
    }

    protected function setParentProduct(array $row)
    {
        $previousParentId = $this->inProcessObject->getParent() ? $this->inProcessObject->getParent()->getId() : null;
        $newParent = $this->parentFolder;
        $isVariant = false;

        // Produit parent
        if (isset($row['parent']) && strlen($row['parent']) > 0) {
            $parent = ObjectHelper::getProductWithVariantBy('sku', $row['parent']);
            if ($parent != null) {
                $newParent = $parent;
                $isVariant = true;
            }
        } // Famille
        elseif (isset($row['family']) && strlen($row['family']) > 0) {
            $family = DataObject\Family::getByFamilyCode(Tools::normalize($row['family']), 1);
            if ($family != null) {
                $newParent = $family;
            }
        }


        if ($newParent->getId() != $previousParentId) {
            $this->inProcessObject->setParent($newParent);
            if ($isVariant) {
                $this->inProcessObject->setType(DataObject\AbstractObject::OBJECT_TYPE_VARIANT);
            }
            $this->setIsUpdated(true);
        }
        return true;
    }

    protected function setFamily(
        string $objectFieldName,
        string $csvValue
    ): bool
    {
        $updated = false;
        $defaultKey = BrickHelper::getBrickKeyFromAttributeSet(BrickHelper::DEFAULT);
        $familyCode = $csvValue;
        $family = $this->getFamilyFromFamilyCode($familyCode);
        if (!$family) {
            return false;
        }
        $brickName = $family->getObjectBrickKey();
        $brickClassName = '\\Pimcore\\Model\\DataObject\\Objectbrick\\Data\\' . ucfirst($brickName);
        $brickGetter = 'get' . ucfirst($brickName);
        $brickSetter = 'set' . ucfirst($brickName);

        if (!class_exists($brickClassName)) {
            $this->logWarning(self::WARN_PRODUCT_FAMILY_NOT_FOUND, $brickName, $this->line);
            return false;
        }

        if (!method_exists($this->inProcessObject->getAttributes(), $brickGetter)) {
            $this->logWarning(self::WARN_PRODUCT_FAMILY_NOT_VALID, $brickName, $this->line);
            return false;
        }

        $objectBrick = $this->inProcessObject->getAttributes()->$brickGetter();
        if (!$objectBrick) {
            $objectBrick = new $brickClassName($this->inProcessObject, 'attributes');
            $this->inProcessObject->getAttributes()->$brickSetter($objectBrick);
            $updated = true;
        }

        // add Default
        if ($objectBrick->getType() !== $defaultKey) {
            $defaultBrickClassName = '\\Pimcore\\Model\\DataObject\\Objectbrick\\Data\\' . ucfirst($defaultKey);
            $defaultBrickGetter = 'get' . ucfirst($defaultKey);
            $defaultBrickSetter = 'set' . ucfirst($defaultKey);
            $defaultObjectBrick = $this->inProcessObject->getAttributes()->$defaultBrickGetter();
            if (!$defaultObjectBrick) {
                $defaultObjectBrick = new $defaultBrickClassName($this->inProcessObject, 'attributes');
                $this->inProcessObject->getAttributes()->$defaultBrickSetter($defaultObjectBrick);
                $updated = true;
            }
        }

        $allBricks = $this->inProcessObject->getAttributes()->getItems();
        foreach ($allBricks as $brickToDelete) {
            if ($brickToDelete->getType() != $objectBrick->getType()
                && $brickToDelete->getType() != $defaultKey) {
                $brickToDelete->setDoDelete(true);
                $updated = true;
            }
        }
        return $updated;
    }

    protected function getFamilyFromFamilyCode(string $familyCode)
    {
        // On récupère la famille avec le code fourni dans le csv
        $familyCode = Tools::normalize($familyCode);
        /** @var DataObject\Family $familyObject */
        $familyObject = DataObject\Family::getByFamilyCode($familyCode, 1);
        if (!$familyObject) {
            $this->logWarning(self::WARN_PRODUCT_FAMILY_NOT_FOUND, $familyCode, $this->line);
        }
        return $familyObject;
    }

    protected function handleAttributes(
        string $objectFieldName,
        string $csvValue
    ): bool
    {
        $updated = false;

        // CSV Attributes : CodeFamily:NameAttribut1:Valeur1|CodeFamily:NameAttribut1:Valeur2
        $csvAttributes = array_filter(array_map('trim', explode("|", $csvValue)));
        $bricksLoaded = [];
        foreach ($csvAttributes as $attributeString) {
            list($familyCode, $name, $value) = array_map('trim', explode(':', $attributeString));
            $brickKey = BrickHelper::getBrickKeyFromAttributeSet($familyCode);
            $brickClassName = '\\Pimcore\\Model\\DataObject\\Objectbrick\\Data\\' . ucfirst($brickKey);
            $brickGetter = 'get' . ucfirst($brickKey);
            $brickSetter = 'set' . ucfirst($brickKey);
            if (!isset($bricksLoaded[$brickKey])) {
                $newObjectBrick = new $brickClassName($this->inProcessObject, 'attributes');
                $newObjectBrick->setFieldname('attributes');
                $bricksLoaded[$brickKey] = [
                    'brick' => $newObjectBrick,
                    'setter' => $brickSetter
                ];
            }
            $objectBrick = $this->inProcessObject->getAttributes()->$brickGetter();
            $result = $this->updateObjectBrickAttribute(
                $objectBrick,
                $bricksLoaded[$brickKey]['brick'],
                $name,
                $value
            );
            if (is_null($result)) {
                continue;
            }
            $updated = $result === true ?: $updated;

        }

        if ($updated === true) {
            foreach ($bricksLoaded as $brickLoaded) {
                $this->inProcessObject
                    ->getAttributes()
                    ->{$brickLoaded['setter']}($brickLoaded['brick']);
            }
        }

        return $updated;
    }

    /**
     * @param $objectBrick
     * @param $newObjectBrick
     * @param $name
     * @param $value
     *
     * @return bool Updated?
     */
    protected function updateObjectBrickAttribute(
        $objectBrick,
        &$newObjectBrick,
        $name,
        $value,
        $printLog = true
    ): ?bool
    {
        if ($value == '') {
            return false;
        }
        $updated = false;
        $name = BrickHelper::getValidname($name);
        $fieldSetter = 'set' . ucfirst($name);
        $fieldGetter = 'get' . ucfirst($name);

        if (!method_exists($newObjectBrick, $fieldSetter)) {
            if ($printLog) {
                $this->logWarning(
                    self::WARN_PRODUCT_ATTRIBUTE_NOT_FOUND,
                    'Attribut : ' . $name . ' - Famille : ' . $newObjectBrick->getType(),
                    $this->line
                );
            }
            return null;
        }
        if (!method_exists($objectBrick, $fieldGetter)) {
            if ($printLog) {
                $this->logWarning(
                    self::WARN_PRODUCT_ATTRIBUTE_NOT_FOUND,
                    'Attribut : ' . $name . ' - Famille : ' . $newObjectBrick->getType(),
                    $this->line
                );
            }
            return null;
        }

        $oldValue = null;
        if ($newObjectBrick->getType() == $objectBrick->getType()) {
            $oldValue = $objectBrick->$fieldGetter();
        }
        if ($oldValue != $value) {
            $updated = true;
        }
        $newObjectBrick->$fieldSetter($value);

        return $updated;
    }


    /**
     * @param string $objectFieldName
     * @param string $csvValue
     *
     * @return bool
     * @throws \Exception
     */
    protected function setSupplier(
        string $objectFieldName,
        string $csvValue
    )
    {
        $parentFolderKey = 'suppliers';
        $className = 'Supplier';
        $objectClassName = sprintf('\\Pimcore\\Model\\DataObject\\%s', ucfirst($className));
        $key = File::getValidFilename($csvValue);
        $obj = $objectClassName::getByPath('/' . $parentFolderKey . '/' . $key);
        if ($obj == null) {
            $folderObject = DataObject\Service::createFolderByPath($parentFolderKey);
            $obj = new $objectClassName();
            $obj->setName($csvValue);
            $obj->setKey($key);
            $obj->setParent($folderObject);
            $obj->setPublished(true);
            $obj->save();
        }

        return $this->objectsMetaData(
            $objectFieldName,
            '/' . $parentFolderKey . '/' . $key,
            $className,
            'Path',
            self::FIELD_VALUE_SEPARATOR
        );
    }

    /**
     * @param string $objectFieldName
     * @param string $csvValue
     *
     * @return bool
     * @throws \Exception
     */
    protected function setBrand(
        string $objectFieldName,
        string $csvValue
    )
    {
        $parentFolderKey = 'brands';
        $className = 'Brand';
        $objectClassName = sprintf('\\Pimcore\\Model\\DataObject\\%s', ucfirst($className));
        $key = File::getValidFilename($csvValue);
        $obj = $objectClassName::getByPath('/' . $parentFolderKey . '/' . $key);
        if ($obj == null) {
            $folderObject = DataObject\Service::createFolderByPath($parentFolderKey);
            $obj = new $objectClassName();
            $obj->setName($csvValue);
            $obj->setKey($key);
            $obj->setParent($folderObject);
            $obj->setPublished(true);
            $obj->save();
        }

        return $this->objectsMetaData(
            $objectFieldName,
            '/' . $parentFolderKey . '/' . $key,
            $className,
            'Path',
            self::FIELD_VALUE_SEPARATOR
        );
    }

    /**
     * @param string $objectFieldName
     * @param string $csvValue
     *
     * @return bool
     * @throws \Exception
     */
    protected function setQuantityPriceType(
        string $objectFieldName,
        string $csvValue
    )
    {
        $parentFolderKey = 'quantity price type';
        $className = 'QuantityPriceType';
        $objectClassName = sprintf('\\Pimcore\\Model\\DataObject\\%s', ucfirst($className));
        $key = File::getValidFilename($csvValue);
        $obj = $objectClassName::getByPath('/' . $parentFolderKey . '/' . $key);
        if ($obj == null) {
            $folderObject = DataObject\Service::createFolderByPath($parentFolderKey);
            $obj = new $objectClassName();
            $obj->setLabel($csvValue);
            $obj->setKey($key);
            $obj->setParent($folderObject);
            $obj->setPublished(true);
            $obj->save();
        }

        return $this->objectsMetaData(
            $objectFieldName,
            '/' . $parentFolderKey . '/' . $key,
            $className,
            'Path',
            self::FIELD_VALUE_SEPARATOR
        );
    }

    /**
     * @param array $row
     */
    protected function feedEmptyField(array $row)
    {
        if ((!isset($row['wait_import_socoda']) || $row['wait_import_socoda'] == '')
            && $this->inProcessObject->getWaitImportSocoda() == null) {
            $prev = $this->inProcessObject->getWaitImportSocoda();
            if ($prev !== true) {
                $this->inProcessObject->setWaitImportSocoda(false);
                $this->isUpdated(true);
            }
        }

        if (!isset($row['status_import_socoda']) || $row['status_import_socoda'] == '') {
            $updated = $this->handleStatusImportSocoda(
                'status_import_socoda',
                PimHelper::STATUS_IMPORT_SOCODA_NO_SYNC
            );
            if ($updated) {
                $this->setIsUpdated(true);
            }
        }

        if ((!isset($row['category_import_socoda']) || $row['category_import_socoda'] == '')
            && $this->inProcessObject->getCategoryImportSocoda() == null) {
            $prev = $this->inProcessObject->getCategoryImportSocoda();
            if ($prev !== true) {
                $this->inProcessObject->setCategoryImportSocoda(false);
                $this->isUpdated(true);
            }
        }

        if ((isset($row['new_item_unit']) && isset($row['new_item_ext_category_id'])) && (empty($row['new_item_unit']) && empty($row['new_item_ext_category_id']))) {
            $this->inProcessObject->setNewItemUnit([]);
            $this->inProcessObject->setNewItemExtCategoryId([]);
            $this->setIsUpdated(true);
        }

        if (isset($row['packaging_unit']) && empty($row['packaging_unit'])) {
            $this->inProcessObject->setPackagingUnit([]);
            $this->setIsUpdated(true);
        }

        if ((!isset($row['websites']) || $row['websites'] == '') && ($this->inProcessObject->getWebsites() == null || count($this->inProcessObject->getWebsites()) < 1)) {
            if ((!isset($row['store_views'])) || $row['store_views'] == '' || $row['store_views'] == 'default') {
                /** @var DataObject\Website $baseWebsite */
                $baseWebsite = DataObject\Website::getByCode('base', 1);
                $relation = new DataObject\Data\ObjectMetadata('websites', [], $baseWebsite);
                $this->inProcessObject->setWebsites([$relation]);
                $this->setIsUpdated(true);
            }
        }

        if ((!isset($row['store_views']) || $row['store_views'] == '') && ($this->inProcessObject->getStoreViews() == null || count($this->inProcessObject->getStoreViews()) < 1)) {
            if ((!isset($row['websites'])) || $row['websites'] == '' || $row['websites'] == 'base') {
                /** @var DataObject\StoreView $defaultStoreView */
                $defaultStoreView = DataObject\StoreView::getByCode('default', 1);
                $relation = new DataObject\Data\ObjectMetadata('storeViews', [], $defaultStoreView);
                $this->inProcessObject->setStoreViews([$relation]);
                $this->setIsUpdated(true);
            }
        }

        if (isset($row['special_price']) && empty($row['special_price'])) {
            $this->inProcessObject->setSpecialPrice(null);
            $this->setIsUpdated(true);
        }

        if (isset($row['special_from_date']) && empty($row['special_from_date'])) {
            $this->inProcessObject->setSpecialFromDate(null);
            $this->setIsUpdated(true);
        }

        if (isset($row['special_to_date']) && empty($row['special_to_date'])) {
            $this->inProcessObject->setSpecialToDate(null);
            $this->setIsUpdated(true);
        }
    }

    protected function handleStatusImportSocoda(
        string $objectFieldName = '',
        string $csvValue = ''
    )
    {
        $updated = false;
        // @todo add check value status (1-4)
        $prev = $this->inProcessObject->getStatusImportSocoda();
        if ($prev != $csvValue) {
            $this->inProcessObject->setStatusImportSocoda($csvValue);
            $updated = true;
            $this->statusNotes[] = ['typeImport' => PimHelper::NOTE_TYPE_IMPORT_ADHERENT, 'status' => $csvValue];
        }
        return $updated;
    }


    /**
     * If value was true, we need to keep it as a resetImages must be done on magento
     * only export and bo action can reset this value to false
     *
     * @param string $objectFieldName
     * @param string $csvValue
     *
     * @return bool
     */
    protected function handleResetImages(
        string $objectFieldName = '',
        string $csvValue = ''
    )
    {
        $updated = false;

        $acceptedValues = ['0', '1'];

        if (!in_array((string)$csvValue, $acceptedValues)) {
            $this->logWarning(self::WARN_PRODUCT_RESET_IMAGES_ATTRIBUTE_INVALID,
                'valeur de la celulle : "' . $csvValue . '", valeurs acceptées : "pas de valeur", "0" ou "1"',
                $this->line
            );
        }

        $prev = $this->inProcessObject->getResetImages();
        if ($prev == false && $csvValue == 1) {
            $this->inProcessObject->setResetImages(true);
            $updated = true;
        }
        return $updated;
    }


    protected function postProcessRow()
    {
        foreach ($this->statusNotes as $note) {
            PimHelper::addStatusNote(
                $this->inProcessObject,
                $this->userId,
                $note['typeImport'],
                $note['status']
            );
        }
        $this->statusNotes = [];
        return parent::postProcessRow();
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
        $updated = false;
        $prevCategories = $this->inProcessObject->getCategories() ?? [];

        if (!$this->isCategoriesProcessed) {
            $resetCategory = $this->getResetCategory();
            /** @var DataObject\Category[] $categories */
            $categories = [];
            if ($objectFieldName == 'categories') {
                $categories = $this->handleCategoriesByCode($csvValue);
            } elseif ($objectFieldName == 'path_categories') {
                $categories = $this->handleCategoriesByPath($csvValue);
            }

            if ($resetCategory) {
                $categoriesMetaData = [];
            } else {
                $categoriesMetaData = $this->inProcessObject->getCategories() ?? [];
            }

            foreach ($categories as $category) {
                $categoryMetaData = new DataObject\Data\ObjectMetadata('categories', ['name'], $category);
                $categoryMetaData->setName($category->getName());
                $categoriesMetaData[] = $categoryMetaData;
            }

            $oldKeyList = $newKeyList = [];
            foreach ($categoriesMetaData as $relation) {
                $newKeyList[] = $relation->getObject()->getCodeCategory();
            }

            foreach ($prevCategories as $relation) {
                $oldKeyList[] = $relation->getObject()->getCodeCategory();
            }

            $diff1 = array_diff($oldKeyList, $newKeyList);
            $diff2 = array_diff($newKeyList, $oldKeyList);
            $changed = $diff1 || $diff2;
            if ($changed) {
                $this->inProcessObject->setCategories($categoriesMetaData);
                $updated = true;
            }
        }

        return $updated;
    }

    /**
     * @param string $objectFieldName
     * @param string $csvValue
     *
     * @return bool
     * @throws \Exception
     */
    protected function setWebsites(
        string $objectFieldName,
        string $csvValue
    )
    {
        $className = 'Website';
        /** @var DataObject\Website $objectClassName */
        $objectClassName = sprintf('\\Pimcore\\Model\\DataObject\\%s', ucfirst($className));

        $websites = explode(self::FIELD_VALUE_SEPARATOR, $csvValue);

        foreach ($websites as $key => $website) {
            /** @var DataObject\Website $obj */
            $obj = $objectClassName::getByCode($website, 1);
            if ($obj == null) {
                $this->logWarning(self::WARN_PRODUCT_WEBSITE_NOT_FOUND,
                    'Code: ' . $website,
                    $this->line
                );
                unset($websites[$key]);
            }
            if ($obj != null) {
                if ($this->csvRow['websites'] == 'base' && $this->csvRow['store_views'] == '') {
                    $this->csvRow['store_views'] = 'default';
                }
                $find = false;
                foreach (explode(self::FIELD_VALUE_SEPARATOR, $this->csvRow['store_views']) as $storeViewCode) {
                    /** @var DataObject\StoreView $storeView */
                    $storeView = DataObject\StoreView::getByCode($storeViewCode, 1);
                    if ($storeView != null) {
                        if (count($storeView->getWebsite()) > 0) {
                            /** @var DataObject\Data\ObjectMetadata $relation */
                            $relation = $storeView->getWebsite()[0];
                            /** @var DataObject\Website $storeViewWebsite */
                            $storeViewWebsite = $relation->getObject();
                            if ($obj->getCode() == $storeViewWebsite->getCode()) {
                                $find = true;
                            }
                        }
                    }
                }
                if (!$find) {
                    $this->logWarning(self::WARN_PRODUCT_WEBSITE_NO_STOREVIEW_SET,
                        'Code: ' . $website,
                        $this->line
                    );
                    unset($websites[$key]);
                }
            }
        }

        $csvValue = implode(self::FIELD_VALUE_SEPARATOR, $websites);

        return self::objectsMetaData(
            $objectFieldName,
            $csvValue,
            $className,
            'Code',
            self::FIELD_VALUE_SEPARATOR
        );
    }

    /**
     * @param string $objectFieldName
     * @param string $csvValue
     *
     * @return bool
     * @throws \Exception
     */
    protected function setStoreViews(
        string $objectFieldName,
        string $csvValue
    )
    {
        $className = 'StoreView';
        /** @var DataObject\StoreView $objectClassName */
        $objectClassName = sprintf('\\Pimcore\\Model\\DataObject\\%s', ucfirst($className));

        $storeViews = explode(self::FIELD_VALUE_SEPARATOR, $csvValue);

        foreach ($storeViews as $key => $storeView) {
            /** @var DataObject\StoreView $obj */
            $obj = $objectClassName::getByCode($storeView, 1);
            if ($obj == null) {
                $this->logWarning(self::WARN_PRODUCT_STOREVIEW_NOT_FOUND,
                    'Code: ' . $storeView,
                    $this->line
                );
                unset($storeViews[$key]);
            }
            if ($obj != null) {
                if ($this->csvRow['store_views'] == 'default' && $this->csvRow['websites'] == '') {
                    $this->csvRow['websites'] = 'base';
                }
                $find = false;
                $websitesList = [];
                if (count($obj->getWebsite()) > 0) {
                    /** @var DataObject\Data\ObjectMetadata $relation */
                    $relation = $obj->getWebsite()[0];
                    /** @var DataObject\Website $storeViewWebsite */
                    $storeViewWebsite = $relation->getObject();
                    foreach (explode(self::FIELD_VALUE_SEPARATOR, $this->csvRow['websites']) as $websiteCode) {
                        /** @var DataObject\Website $website */
                        $website = DataObject\Website::getByCode($websiteCode, 1);
                        if ($website != null) {
                            $websitesList[] = $website->getCode();
                            if ($website->getCode() == $storeViewWebsite->getCode()) {
                                $find = true;
                            }
                        }
                    }
                }
                if (!$find) {
                    $this->logWarning(self::WARN_PRODUCT_STOREVIEW_NOT_FOUND_IN_WEBSITE,
                        'StoreView: ' . $storeView . ' Website(s): ' . implode(', ', $websitesList),
                        $this->line
                    );
                    unset($storeViews[$key]);
                }
            }
        }

        $csvValue = implode(self::FIELD_VALUE_SEPARATOR, $storeViews);

        return self::objectsMetaData(
            $objectFieldName,
            $csvValue,
            $className,
            'Code',
            self::FIELD_VALUE_SEPARATOR
        );
    }

    /**
     * Get reset_category from csv.
     * reset_category is not persisted in product.
     *
     * @return bool
     */
    protected function getResetCategory()
    {
        $resetCategory = false;
        if (isset($this->csvRow['reset_category']) && (bool)$this->csvRow['reset_category']) {
            $resetCategory = true;
        }
        return $resetCategory;
    }

    protected function getCategoriesValueSeparator()
    {
        return self::FIELD_VALUE_SEPARATOR;
    }

    protected function handleCategoriesByPath($csvValue)
    {
        $categories = [];
        $categoriesPath = explode($this->getCategoriesValueSeparator(), $csvValue);
        foreach ($categoriesPath as $categoryPath) {
            $parentCategory = DataObject\Service::createFolderByPath(Category::PARENT_FOLDER_KEY);
            $categoriesName = explode('/', $categoryPath);
            foreach ($categoriesName as $categoryIndex => $categoryName) {
                $children = $parentCategory->getChildren();
                $found = false;
                /** @var DataObject\Category $child */
                foreach ($children as $child) {
                    if ($child->getName() == $categoryName) {
                        $parentCategory = $child;
                        $found = true;
                        if (!isset($categoriesName[$categoryIndex + 1])) {
                            $categories[] = $child;
                        }
                        break;
                    }
                }
                if (!$found) {
                    $this->logWarning(self::WARN_PRODUCT_CATEGORY_NAME_NOT_FOUND_IN_PARENT,
                        'Catégorie : ' . $categoryName . ' - Parent : ' . $parentCategory->getFullPath(),
                        $this->line
                    );
                }
            }
        }
        return $categories;
    }

    protected function handleCategoriesByCode($csvValue)
    {
        $categories = [];
        $categoriesCode = explode($this->getCategoriesValueSeparator(), $csvValue);
        foreach ($categoriesCode as $categoryCode) {
            $category = DataObject\Category::getByCodeCategory($categoryCode, 1);
            if ($category) {
                $categories[] = $category;
            } else {
                $this->logWarning(self::WARN_PRODUCT_CATEGORY_CODE_NOT_FOUND, $categoryCode, $this->line);
            }
        }
        if (count($categories) > 0) {
            $this->isCategoriesProcessed = true;
        }
        return $categories;
    }

    /**
     * @param array $csvRow
     *
     * @return bool
     */
    protected function checkEiffage(array $csvRow)
    {
        if ((!isset($csvRow['new_item_unit']) || empty($csvRow['new_item_unit'])) xor (!isset($csvRow['new_item_ext_category_id']) || empty($csvRow['new_item_ext_category_id']))) {
            $this->logWarning(self::WARN_PRODUCT_EIFFAGE_ATTRIBUTE_BOTH_MUST_BE_SET,
                'Si au moins un des champs new_item_unit et new_item_ext_category_id est renseigné alors les 2 champs doivent l\'être.',
                $this->line
            );
            return false;
        }
        if ((isset($csvRow['new_item_unit']) && !empty($csvRow['new_item_unit'])) && (isset($csvRow['new_item_ext_category_id']) && !empty($csvRow['new_item_ext_category_id']))) {
            $newItemUnitParentFolderKey = 'unité de vente EIFFAGE';
            $newItemUnitClassName = 'NewItemUnit';
            $newItemUnitObjectClassName = sprintf('\\Pimcore\\Model\\DataObject\\%s', ucfirst($newItemUnitClassName));
            $newItemUnit = $newItemUnitObjectClassName::getByPath('/' . $newItemUnitParentFolderKey . '/' . $csvRow['new_item_unit']);

            $newItemExtCategoryIdParentFolderKey = 'catégories EIFFAGE';
            $newItemExtCategoryIdClassName = 'NewItemExtCategoryId';
            $newItemExtCategoryIdObjectClassName = sprintf('\\Pimcore\\Model\\DataObject\\%s',
                ucfirst($newItemExtCategoryIdClassName));
            $newItemExtCategoryId = $newItemExtCategoryIdObjectClassName::getByPath('/' . $newItemExtCategoryIdParentFolderKey . '/' . $csvRow['new_item_ext_category_id']);

            if (!$newItemUnit) {
                $this->logWarning(self::WARN_PRODUCT_EIFFAGE_ATTRIBUTE_NEWITEMUNIT_NOT_FOUND,
                    $csvRow['new_item_unit'] . ' (' . $newItemUnitClassName . ')',
                    $this->line
                );
                return false;
            }
            if (!$newItemExtCategoryId) {
                $this->logWarning(self::WARN_PRODUCT_EIFFAGE_ATTRIBUTE_NEWITEMEXTCATEGORYID_NOT_FOUND,
                    $csvRow['new_item_ext_category_id'] . ' (' . $newItemExtCategoryIdClassName . ')',
                    $this->line
                );
                return false;
            }
        }

        if (isset($csvRow['packaging_unit']) && !empty($csvRow['packaging_unit'])) {
            $packagingUnitParentFolderKey = 'unité de conditionnement';
            $packagingUnitClassName = 'PackagingUnit';
            $packagingUnitObjectClassName = sprintf('\\Pimcore\\Model\\DataObject\\%s',
                ucfirst($packagingUnitClassName));
            $packagingUnit = $packagingUnitObjectClassName::getByPath('/' . $packagingUnitParentFolderKey . '/' . $csvRow['packaging_unit']);

            if (!$packagingUnit) {
                $this->logWarning(self::WARN_PRODUCT_EIFFAGE_ATTRIBUTE_PACKAGINGUNIT_NOT_FOUND,
                    $csvRow['packaging_unit'] . ' (' . $packagingUnitClassName . ')',
                    $this->line
                );
                return false;
            }
        }
        return true;
    }

    /**
     * @param array $csvRow
     *
     * @return bool
     */
    protected function checkWebsites(array $csvRow)
    {
        if ((!isset($csvRow['websites']) || empty($csvRow['websites'])) xor (!isset($csvRow['store_views']) || empty($csvRow['store_views']))) {
            $this->logWarning(self::WARN_PRODUCT_WEBSITE_STOREVIEW_BOTH_MUST_BE_SET,
                'Si au moins un des champs websites et store_views est renseigné alors les 2 champs sont obligatoires.',
                $this->line
            );
            return false;
        }
        return true;
    }

    /**
     * @param string $objectFieldName
     * @param string $csvValue
     *
     * @return bool
     * @throws \Exception
     */
    protected function setNewItemUnit(
        string $objectFieldName,
        string $csvValue
    )
    {
        $parentFolderKey = 'unité de vente EIFFAGE';
        $className = 'NewItemUnit';
        $objectClassName = sprintf('\\Pimcore\\Model\\DataObject\\%s', ucfirst($className));
        $obj = $objectClassName::getByPath('/' . $parentFolderKey . '/' . $csvValue);

        return $this->objectsMetaData(
            $objectFieldName,
            $csvValue,
            $className,
            'Label',
            self::FIELD_VALUE_SEPARATOR
        );
    }

    /**
     * @param string $objectFieldName
     * @param string $csvValue
     *
     * @return bool
     * @throws \Exception
     */
    protected function setNewItemExtCategoryId(
        string $objectFieldName,
        string $csvValue
    )
    {
        $parentFolderKey = 'catégories EIFFAGE';
        $className = 'NewItemExtCategoryId';
        $objectClassName = sprintf('\\Pimcore\\Model\\DataObject\\%s', ucfirst($className));
        $obj = $objectClassName::getByPath('/' . $parentFolderKey . '/' . $csvValue);

        return $this->objectsMetaData(
            $objectFieldName,
            $csvValue,
            $className,
            'Label',
            self::FIELD_VALUE_SEPARATOR
        );
    }

    /**
     * @param string $objectFieldName
     * @param string $csvValue
     *
     * @return bool
     * @throws \Exception
     */
    protected function setSpecialFromDate(
        string $objectFieldName,
        string $csvValue
    )
    {
        if (isset($csvValue) && is_string($csvValue)) {
            $csvValue = strtotime($csvValue);
            $carbon = new Carbon();
            $this->inProcessObject->setSpecialFromDate($carbon->setTimestamp($csvValue));
            return true;
        }
        return false;
    }

    /**
     * @param string $objectFieldName
     * @param string $csvValue
     *
     * @return bool
     * @throws \Exception
     */
    protected function setSpecialToDate(
        string $objectFieldName,
        string $csvValue
    )
    {
        if (isset($csvValue) && is_string($csvValue)) {
            $csvValue = strtotime($csvValue);
            $carbon = new Carbon();
            $this->inProcessObject->setSpecialToDate($carbon->setTimestamp($csvValue));
            return true;
        }
        return false;
    }

    protected function checkSpecialFromDate($csvRow)
    {
        $format = "Y-m-d";
        $csvValue = $csvRow['special_from_date'];
        if (!isset($csvValue) || $csvValue == '' || (isset($csvValue) && is_string($csvValue) && date($format,
                    strtotime($csvValue)) == date($csvValue))) {
            return true;
        } else {
            $this->logWarning(self::WARN_PRODUCT_SPECIAL_FROM_DATE_INVALID,
                $csvValue,
                $this->line
            );
            return false;
        }
    }

    protected function checkSpecialToDate($csvRow)
    {
        $format = "Y-m-d";
        $csvValue = $csvRow['special_to_date'];
        if (!isset($csvValue) || $csvValue == '' || (isset($csvValue) && is_string($csvValue) && date($format,
                    strtotime($csvValue)) == date($csvValue))) {
            return true;
        } else {
            $this->logWarning(self::WARN_PRODUCT_SPECIAL_TO_DATE_INVALID,
                $csvValue,
                $this->line
            );
            return false;
        }
    }

    protected function checkIdSocodaIsUnique($csvRow)
    {
        $attrCode = 'id_socoda';
        if ($csvRow[$attrCode] !== "" && isset($csvRow[$attrCode])) {
            if ($this->socodaIds === null) {
                $db = \Pimcore\Db::get();
                $sql = sprintf('SELECT sku, idSocoda FROM object_%s WHERE idSocoda IS NOT NULL AND idSocoda != ""',
                    DataObject\Product::classId());
                $socodaSkuIds = $db->fetchAll($sql);
                foreach ($socodaSkuIds as $socodaSkuId) {
                    $this->socodaIds[$socodaSkuId['sku']] = $socodaSkuId['idSocoda'];
                }
                if (empty($socodaSkuIds)) {
                    $this->socodaIds = [];
                }
            }

            if ((isset($this->uniqueAttributes[$attrCode][$csvRow[$attrCode]])
                    && ($this->uniqueAttributes[$attrCode][$csvRow[$attrCode]] != $csvRow['sku'])) ||
                (
                    in_array($csvRow[$attrCode], $this->socodaIds, true) &&
                    (!isset($this->socodaIds[$csvRow['sku']]) || $this->socodaIds[$csvRow['sku']] != $csvRow[$attrCode])
                )
            ) {
                $this->logWarning(self::WARN_PRODUCT_ID_SOCODA_IS_NOT_UNIQUE,
                    'La valeur de l\'attribut "id_socoda" n\'est pas unique. Rentrez une valeur unique et réessayez.',
                    $this->line
                );
                return false;
            }
            $this->uniqueAttributes[$attrCode][$csvRow[$attrCode]] = $csvRow['sku'];
            return true;
        }
        return true;
    }

    protected function checkParentAttributeSet($csvRow)
    {
        if (isset($csvRow['parent']) && $csvRow['parent'] !== "") {
            $parent = ObjectHelper::getProductWithVariantBy('sku', $csvRow['parent']);
            if ($parent) {
                /** @var DataObject\Objectbrick $item */
                foreach ($parent->getAttributes()->getItems() as $item) {
                    $parentFamily = str_replace('_set', '', $item->getType());
                    if ($csvRow['family'] == $parentFamily) {
                        return true;
                    }
                }
                $this->logWarning(self::WARN_PRODUCT_PARENT_ATTRIBUTE_SET_NOT_IDENTICAL,
                    'La famille (jeu d\'attribut) du produit enfant ne correspond pas à celle du produit parent.',
                    $this->line
                );
                return false;
            }
        }

        return true;
    }

    /**
     * To override if needed
     *
     * @param $objectFieldName
     *
     * @return bool
     */
    protected function isMultiHrefAssetInReset($objectFieldName)
    {
        $return = false;
        switch ($objectFieldName) {
            case 'images':
                $return = $this->isResetImages;
                break;
            default:
                break;
        }
        return $return;
    }

    /**
     * if no image to import, in case of reset_images we need to delete old ones
     *
     * @param array $csvRow
     *
     * @return bool
     */
    protected function hasImagesToImport(array $csvRow)
    {
        return isset($csvRow['images']) && !empty($csvRow['images']);
    }

    /**
     * @param string $objectFieldName
     * @param string $csvValue
     *
     * @return bool
     * @throws \Exception
     */
    protected function setPackagingUnit(
        string $objectFieldName,
        string $csvValue
    )
    {
        $parentFolderKey = 'unité de conditionnement';
        $className = 'PackagingUnit';
        $objectClassName = sprintf('\\Pimcore\\Model\\DataObject\\%s', ucfirst($className));
        $obj = $objectClassName::getByPath('/' . $parentFolderKey . '/' . $csvValue);

        return $this->objectsMetaData(
            $objectFieldName,
            $csvValue,
            $className,
            'Label',
            self::FIELD_VALUE_SEPARATOR
        );
    }

    /**
     * force as bool to prevent default null on checkbox from pimcore
     * we skip inherited value to disable this feature on this field
     * @param string $objectFieldName
     * @param string $csvValue
     * @return bool
     */
    protected function handlePcre(
        string $objectFieldName,
        string $csvValue
    )
    {
        $pcre=null;

        if($csvValue === '1'){
            $pcre = self::SELECT_TRUE;
        } else if ($csvValue === '0') {
            $pcre = self::SELECT_FALSE;
        }

        if(!is_null($pcre)) {
            $this->inProcessObject->setPcre($pcre);
        }

        return true;
    }

}
