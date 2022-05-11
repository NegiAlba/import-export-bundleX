<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2017 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Template\Exporter;

use Carbon\Carbon;
use Galilee\ImportExportBundle\Helper\AssetHelper;
use Galilee\ImportExportBundle\Helper\ConfigHelper;
use Galilee\ImportExportBundle\Helper\CsvWriter;
use Galilee\ImportExportBundle\Helper\DbHelper;
use Galilee\ImportExportBundle\Processor\Exporter\Product as BaseProductExporter;
use Pimcore\Db;
use Pimcore\File;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\Data\ObjectMetadata;
use Pimcore\Model\WebsiteSetting;

class Product extends BaseProductExporter
{

    /** @var DataObject\Product */
    public $inProgressProduct;

    public $columnNames;
    /**
     * @var DbHelper
     */
    protected $productDbHelper;

    /**
     * @var bool
     */
    protected $isMonoStore = null;

    public function __construct()
    {
        $this->initColumnNames();
        return parent::__construct();
    }

    public function preProcess()
    {
        $this->exportFileName = date("Y-m-d-H-i-s") . '_' . $this->exportFileName;
    }

    protected function getQuery()
    {
        $exportDate = strtotime($this->exportDate);
        $productTable = sprintf('object_%d', DataObject\Product::classId());
        $sql = sprintf(
            'SELECT' .
            ' *' .
            ' FROM %s' .
            ' WHERE o_modificationDate >= %d ',
            $productTable, $exportDate);
        return $sql;
    }

    /**
     * @throws \Exception
     */
    public function process()
    {
        $this->writeInfo('Export des produits modifiés après le : ' . $this->exportDate);

        $this->productDbHelper = new DbHelper(DataObject\Product::class, 'Product');

        $db = Db::get();
        $sql = $this->getQuery();
        $productsArray = $db->fetchAll($sql);
        $totalCount = count($productsArray);
        $this->writeInfo('Nombre de produit(s) : ' . $totalCount);

        if ($totalCount) {

            $timeBatchStart = microtime(true);

            $csvWriter = new CsvWriter($this->exportPath . $this->exportFileName);
            $csvWriter->addRow($this->columnNames);

            $count = 1;
            foreach ($productsArray as $productArray) {

                $timeRowStart = microtime(true);

                $product = DataObject\Product::getById($productArray['oo_id']);
                if ($product) {
                    $this->vMessage($count . '/' . $totalCount . ' > ' . $product->getSku());
                    $this->inProgressProduct = $product;
                    if (self::canExport($product)) {
                        $storeViewRelations = $product->getStoreViews();
                        $websiteRelations = $product->getWebsites();
                        if ($storeViewRelations != null || $websiteRelations != null) {
                            /** @var ObjectMetadata $storeViewRelation */
                            foreach ($storeViewRelations as $storeViewRelation) {
                                /** @var DataObject\StoreView $storeView */
                                $storeView = $storeViewRelation->getObject();
                                if (count($storeView->getWebsite()) == 1) {
                                    /** @var ObjectMetadata $websiteRelation */
                                    $websiteRelation = $storeView->getWebsite()[0];
                                    /** @var DataObject\Website $website */
                                    $website = $websiteRelation->getObject();
                                    $row = $this->getRowWebsite($product, $website, $storeView);
                                    if ($row) {
                                        $csvWriter->addRow($row);
                                    }
                                }
                            }

                            $this->postProcessRow();

                        } else {
                            if ($websiteRelations == null) {
                                $this->writeWarning('Pas de Website attribué pour le produit: ' . $product->getSku());
                            }
                            if ($storeViewRelations == null) {
                                $this->writeWarning('Pas de Store View attribué pour le produit: ' . $product->getSku());
                            }
                        }
                    }
                    $count++;
                    $timeRowEnd = microtime(true);
                    $this->vMessage(' - Row treated in ' . ($timeRowEnd - $timeRowStart) . ' sec : ' . $product->getFullPath());
                } else {
                    $this->vMessage('Error load product ' . $productArray['oo_id']);
                    $timeRowEnd = microtime(true);
                    $this->vMessage(' - Row treated in ' . ($timeRowEnd - $timeRowStart) . ' sec');
                }

            }

            $timeBatchEnd = microtime(true);
            $this->vMessage('Product Collection exported in ' . ($timeBatchEnd - $timeBatchStart) . ' sec');

            $csvWriter->close();
            $this->writeInfo('Fichier d\'export  : ' . $this->exportPath . $this->exportFileName);

        }
    }

    /**
     * @param $product
     *
     * @return array
     */
    public function getRowWebsite(
        DataObject\Product $product,
        DataObject\Website $website,
        DataObject\StoreView $storeView
    ): array {
        $row = array_fill_keys($this->columnNames, '');

        $productImages = $this->getProductImages();
        $hasParent = $this->inProgressProduct->getParent() instanceof DataObject\Product ? true : false;
        $hasChildren = $this->inProgressProduct->hasChildren(self::OBJECT_TYPES);
        $childProductVisibility = WebsiteSetting::getByName('child_product_visibility');
        $visibility = $hasParent
            ? (isset(self::CHILD_PRODUCT_VISIBILITY[$childProductVisibility->getData()]) ? self::CHILD_PRODUCT_VISIBILITY[$childProductVisibility->getData()] : self::CHILD_PRODUCT_VISIBILITY[3])
            : self::CHILD_PRODUCT_VISIBILITY[4];

        $type = $hasChildren
            ? self::PRODUCT_TYPE_GROUPED
            : self::PRODUCT_TYPE_SIMPLE;

        $sku = $this->inProgressProduct->getSku();

        $associatedProducts = '';
        $associated_product = '';
        if ($type == self::PRODUCT_TYPE_SIMPLE) {
            $associated_product = $this->getAssociatedProduct();
        }

        $up_selling = '';
        if ($type == self::PRODUCT_TYPE_SIMPLE) {
            $up_selling = $this->getUpSelling();
        }

        $cross_selling = '';
        if ($type == self::PRODUCT_TYPE_SIMPLE) {
            $cross_selling = $this->getCrossSelling();
        }

        //Because pimcore getter is bugged with checkboxes
        $isInStock = ($this->inProgressProduct->inStock === null)
            ? $this->inProgressProduct->getInStock() : $this->inProgressProduct->inStock;
        $isInStock = ($isInStock === true) ? '1' : '0';

        $availability = ($this->inProgressProduct->availability === null)
            ? $this->inProgressProduct->getAvailability() : $this->inProgressProduct->availability;
        $availability = ($availability === '1') ? '1' : '0';

        // URL_KEY
        if ($this->inProgressProduct->getUrlKey()) {
            $urlKey = $this->inProgressProduct->getUrlKey();
        } else {
            $urlKey = $this->inProgressProduct->getParent()->getKey() . '-' . $this->inProgressProduct->getKey();
            if ($type == self::PRODUCT_TYPE_GROUPED) {
                $urlKey .= '_grouped';
            }
        }

        if ($type == self::PRODUCT_TYPE_GROUPED) {
            $sku .= '-grouped';
            $associatedProducts = $this->getAssociatedSkus();
            $isInStock = '1';
        }

        $manufacturer = '';
        $manufacturerObjects = $this->inProgressProduct->getSupplier();
        /** @var ObjectMetadata $manufacturerObject */
        $manufacturerObject = $manufacturerObjects[0];
        if ($manufacturerObject) {
            $manufacturer = $manufacturerObject->getObject()->getName();
        }

        $brandName = '';
        $brandNameObjects = $this->inProgressProduct->getBrand();
        /** @var ObjectMetadata $brandNameObject */
        $brandNameObject = $brandNameObjects[0];
        if ($brandNameObject) {
            $brandName = $brandNameObject->getObject()->getName();
        }

        $quantityPriceType = '';
        $quantityPriceTypeObjects = $this->inProgressProduct->getQuantityPriceType();
        /** @var ObjectMetadata $quantityPriceTypeObject */
        $quantityPriceTypeObject = $quantityPriceTypeObjects[0];
        if ($quantityPriceTypeObject) {
            $quantityPriceType = $quantityPriceTypeObject->getObject()->getLabel();
        }

        $newItemUnit = '';
        $newItemUnitObjects = $this->inProgressProduct->getNewItemUnit();
        /** @var ObjectMetadata $newItemUnitObject */
        $newItemUnitObject = $newItemUnitObjects[0];
        if ($newItemUnitObject) {
            $newItemUnit = $newItemUnitObject->getObject()->getLabel();
        }

        $newItemExtCategoryId = '';
        $newItemExtCategoryIdObjects = $this->inProgressProduct->getNewItemExtCategoryId();
        /** @var ObjectMetadata $newItemExtCategoryIdObject */
        $newItemExtCategoryIdObject = $newItemExtCategoryIdObjects[0];
        if ($newItemExtCategoryIdObject) {
            $newItemExtCategoryId = $newItemExtCategoryIdObject->getObject()->getLabel();
        }

        $packagingUnit = '';
        $packagingUnitObjects = $this->inProgressProduct->getPackagingUnit();
        /** @var ObjectMetadata $packagingUnitObject */
        $packagingUnitObject = $packagingUnitObjects[0];
        if ($packagingUnitObject) {
            $packagingUnit = $packagingUnitObject->getObject()->getLabel();
        }

        $conditioning = $this->getConditioning();
        $row['sku'] = $sku;
        $row['attribute_set_code'] = 'Default';
        $row['product_type'] = $type;
        $row['categories'] = $this->getCategories();
        $row['product_websites'] = $website->getCode();
        if (!$this->isMonoStore()) {
            $row['store_view_code'] = $storeView->getCode();
        }
        $row['name'] = $this->inProgressProduct->getName();
        $row['description'] = $this->inProgressProduct->getDescription();
        $row['short_description'] = $this->inProgressProduct->getShort_description();
        $row['product_online'] = $availability;
        $row['tax_class_name'] = 'Taxable Goods';
        $row['visibility'] = $visibility;
        $row['price'] = $this->inProgressProduct->getPrice() ? $this->inProgressProduct->getPrice() : 0;
        $row['meta_title'] = $this->inProgressProduct->getMetaTitle();
        $row['meta_keywords'] = $this->inProgressProduct->getMetaKeyword();
        $row['meta_description'] = $this->inProgressProduct->getMetaDescription();
        $row['base_image'] = $productImages['base'];
        $row['thumbnail_image'] = $productImages['thumbnail'];
        $row['small_image'] = $productImages['small'];
        $row['additional_images'] = $productImages['additional'];
        $row['qty'] = $this->inProgressProduct->getQty();
        $row['url_key'] = $urlKey;
        $row['is_in_stock'] = $isInStock;
        $row['associated_skus'] = $associatedProducts;
        $row['manufacturer'] = $manufacturer;
        $row['brand_name'] = $brandName;
        $row['quantity_price'] = $this->inProgressProduct->getQuantity_price() ?? 0;
        $row['quantity_price_type'] = $quantityPriceType;
        $row['new_item_unit'] = $newItemUnit;
        $row['new_item_ext_category_id'] = $newItemExtCategoryId;
        $row['use_config_enable_qty_inc'] = $conditioning['use_config'];
        $row['enable_qty_increments'] = $conditioning['enable'];
        $row['qty_increments'] = $conditioning['conditioning'];
        $row['id_socoda'] = $this->inProgressProduct->getIdSocoda();
        $row['wait_import_socoda'] = $this->inProgressProduct->getWaitImportSocoda();
        $row['status_import_socoda'] = (int)$this->inProgressProduct->getStatusImportSocoda() != 0 ? (int)$this->inProgressProduct->getStatusImportSocoda() : 4;
        $row['category_import_socoda'] = $this->inProgressProduct->getCategoryImportSocoda();
        $row['special_price'] = $this->inProgressProduct->getSpecialPrice();
        $row['special_from_date'] = ($this->inProgressProduct->getSpecialFromDate() instanceof Carbon ? $this->inProgressProduct->getSpecialFromDate()->format('Y-m-d') : '');
        $row['special_to_date'] = ($this->inProgressProduct->getSpecialToDate() instanceof Carbon ? $this->inProgressProduct->getSpecialToDate()->format('Y-m-d') : '');

        $row['related_skus'] = $associated_product;
        $row['crosssell_skus'] = $up_selling;
        $row['upsell_skus'] = $cross_selling;

        // Les catégories doivent être écrasées dans Magento car elles ont été déjà gérées dans l'import Pimcore.
        $row['reset_category'] = 1;

        $row['reset_images'] = $this->inProgressProduct->getResetImages() ? 1 : 0;

        $row['number_pieces_packaging'] = $this->inProgressProduct->getNumberPiecesPackaging();
        $row['packaging_unit'] = $packagingUnit;
        $row['pcre'] = $this->inProgressProduct->getPcre() === '1' ? 1 : 0;

        $bricks = $this->inProgressProduct->getAttributes()->getItems();
        foreach ($bricks as $brick) {
            $row = $this->addAttributeDataRow($brick, $row);
        }

        $configHelper = new ConfigHelper();
        $attributesToClean = $configHelper->getAttributesToClean();
        foreach ($attributesToClean as $attribute) {
            if (isset($row[$attribute])) {
                $row[$attribute] = $this->cleanHtml($row[$attribute]);
            }
        }

        return $row;
    }

    protected function addAttributeDataRow($brick, $row)
    {
        $definition = $brick->getDefinition();
        if ($definition->getTitle() != 'Default') {
            $row['attribute_set_code'] = $definition->getTitle();
        }
        foreach ($definition->getFieldDefinitions() as $attrName => $attrObj) {
            $getter = 'get' . ucfirst($attrName);
            if (method_exists($brick, $getter)) {
                switch ($attrObj->getFieldtype()) {
                    case 'multiselect':
                        $values = is_array($brick->$getter()) ? $brick->$getter() : [];
                        $value = implode('|', $values);
                        break;

                    case 'date':
                        $value = $brick->$getter() ? $brick->$getter()->format('Y/m/d') : '';
                        break;

                    case 'checkbox':
                        $value = '';
                        if ($brick->$getter() == 1) {
                            $value = 'Yes';
                        } elseif ($brick->$getter() == 0) {
                            $value = 'No';
                        }
                        break;

                    default:
                        $value = $brick->$getter();
                }
                $key = File::getValidFilename($attrName);
                if (array_key_exists($key, $row)) {
                    $row[$key] = $value;
                }
            }
        }
        return $row;
    }

    protected function getCategories()
    {
        $productCategories = [];
        $categoryIds = $this->getProductVariable('metadata', 'Categories', 'id');
        foreach ($categoryIds as $id) {
            $category = DataObject\Category::getById($id);
            $categoryParent = $category->getParent();
            $tree = [];
            while ($categoryParent instanceof DataObject\Category) {
                $tree[] = $categoryParent->getName();
                $categoryParent = $categoryParent->getParent();
            }
            if (count($tree)) {
                $productCategory = implode('/', array_reverse($tree)) . '/' . $category->getName();
            } else {
                $productCategory = $category->getName();
            }
            $productCategories[] = self::DEFAULT_CATEGORY . '/' . $productCategory;
        }
        return implode(self::DEFAULT_EXPORT_MULTI_VALUE_SEPARATOR, $productCategories);
    }

    protected function getProductVariable($type, $objectName, $objectField)
    {
        $results = [];
        $objGetter = 'get' . ucfirst($objectName);
        $fieldGetter = 'get' . ucfirst($objectField);
        if ($this->inProgressProduct->$objGetter()) {
            foreach ($this->inProgressProduct->$objGetter() as $obj) {
                if ($type == 'metadata') {
                    $results[] = $obj->getObject()->$fieldGetter();
                }
                if ($type == 'object') {
                    $results[] = $obj->$fieldGetter();
                }
            }
        }
        return $results;
    }


    protected function getAssociatedSkus()
    {
        $associatedProductsWithPosition = array();
        $associatedProductsNoPosition = array();
        $associatedSkusWithPosition = array();
        $associatedSkusNoPosition = array();
        foreach ($this->inProgressProduct->getChildren(self::OBJECT_TYPES) as $child) {
            if ($child instanceof DataObject\Product) {
                if (!is_null($child->getPosition())) {
                    $position = $this->findPositionInArray($associatedSkusWithPosition, $child);
                    $associatedSkusWithPosition = $this->insertIntoPosition($associatedSkusWithPosition,
                        $position, $child);
                } else {
                    $position = $this->findPositionInArrayWithNames($associatedSkusNoPosition, $child);
                    $associatedSkusNoPosition = $this->insertIntoPosition($associatedSkusNoPosition,
                        $position, $child);
                }
            }
        }

        $associatedSkus = $this->inProgressProduct->getSku();

        foreach ($associatedSkusWithPosition as $child) {
            $associatedSkus .= $this->getDefaultMultiValueSeparator() . $child->getSku();
        }

        foreach ($associatedSkusNoPosition as $child) {
            $associatedSkus .= $this->getDefaultMultiValueSeparator() . $child->getSku();
        }

        return $associatedSkus;
    }

    protected function getAssociatedProduct()
    {
        $first = true;
        $associated_product = '';

        if ($this->inProgressProduct->getAssociatedProduct()) {
            foreach ($this->inProgressProduct->getAssociatedProduct() as $child) {
                if ($first) {
                    $associated_product .= $child->getSku();
                    $first = false;
                } else {
                    $associated_product .= $this->getDefaultMultiValueSeparator() . $child->getSku();
                }
            }
        }

        return $associated_product;
    }

    protected function getUpSelling()
    {
        $first = true;
        $up_selling = '';
        if ($this->inProgressProduct->getUpSelling()) {
            foreach ($this->inProgressProduct->getUpSelling() as $child) {
                if ($first) {
                    $up_selling .= $child->getSku();
                    $first = false;
                } else {
                    $up_selling .= $this->getDefaultMultiValueSeparator() . $child->getSku();
                }
            }
        }

        return $up_selling;
    }

    protected function getCrossSelling()
    {
        $first = true;
        $cross_selling = '';
        if ($this->inProgressProduct->getCrossSelling()) {
            foreach ($this->inProgressProduct->getCrossSelling() as $child) {
                if ($first) {
                    $cross_selling .= $child->getSku();
                    $first = false;
                } else {
                    $cross_selling .= $this->getDefaultMultiValueSeparator() . $child->getSku();
                }
            }
        }

        return $cross_selling;
    }


    /**
     * Insert the product into the array at the given position
     *
     * @param array $array
     * @param int $position
     * @param $product
     *
     * @return array
     */
    protected function insertIntoPosition(array $array, int $position, $product)
    {
        if ($position === -1) {
            array_push($array, $product);
        } else {
            if ($position === 0) {
                array_unshift($array, $product);
            } else {
                array_splice($array, $position, 0, [$product]);
            }
        }

        return $array;
    }

    /**
     * Find the position to insert the product in the given array.
     * Based on the names of the products to sort alphabetically.
     *
     * @param array $associatedSkus
     * @param DataObject\Product $child
     *
     * @return int
     */
    protected function findPositionInArrayWithNames(array $associatedSkus, DataObject\Product $child)
    {
        if (count($associatedSkus) > 0) {
            $i = count($associatedSkus);
            $position = -1;
            while ($i > 0) {
                $i--;
                $currentChild = $associatedSkus[$i];

                if ($currentChild->getName() > $child->getName()) {
                    $position = $i;
                    continue;
                }
                break;
            }
            return $position;
        } else {
            return -1;
        }
    }

    /**
     * Find the position in the given array where the associated product should be inserted.
     * Based on the attribute "position" first, and products with the same position are sorted alphabetically
     *
     * @param array $associatedSkus
     * @param DataObject\Product $child
     *
     * @return int
     */
    protected function findPositionInArray(array $associatedSkus, DataObject\Product $child)
    {
        if (count($associatedSkus) > 0) {
            $i = count($associatedSkus);
            $position = -1;
            while ($i > 0) {
                $i--;
                $currentChild = $associatedSkus[$i];

                if ($currentChild->getPosition() > $child->getPosition()) {
                    $position = $i;
                    continue;
                } else {
                    if ($currentChild->getPosition() === $child->getPosition()) {
                        if ($currentChild->getName() > $child->getName()) {
                            $position = $i;
                            continue;
                        }
                    }
                }
                break;
            }
            return $position;
        } else {
            return -1;
        }
    }

    public function initColumnNames()
    {
        $attributes = $this->getUniqueAttributes();
        $this->columnNames = array_unique(array_merge($this->baseColumns, $attributes));
        if (!$this->isMonoStore()) {
            $this->columnNames = array_unique(array_merge($this->columnNames, ['store_view_code']));
        }
        return $this;
    }

    protected function getUniqueAttributes()
    {
        $list = new DataObject\Objectbrick\Definition\Listing();
        $list = $list->load();
        $attributes = [];
        foreach ($list as $brick) {
            foreach ($brick->getFieldDefinitions() as $fieldDefinition) {
                $attributes[] = File::getValidFilename($fieldDefinition->getName());
            }
        }
        return array_unique($attributes);
    }


    protected function getProductImages()
    {
        $exportDate = strtotime($this->exportDate);
        $result = array(
            'base' => '',
            'thumbnail' => '',
            'small' => '',
            'additional' => ''
        );
        $images = $this->inProgressProduct->getImages();
        if ($images) {
            /** @var \Pimcore\Model\Asset\Image $image */
            $i = 1;
            $additionalImages = [];
            $baseImages = $thumbnailImage = $smallImage = '';
            foreach ($images as $image) {

                // unless reset_images was set, we only send those not yet exported to magento
                if (!$this->inProgressProduct->getResetImages() && $image->getModificationDate() < $exportDate) {
                    $i++;
                    continue;
                }
                try {

                    if (AssetHelper::IsPreviewNeeded($image)) {
                        $timeThumbGenerationStart = microtime(true);
                        $imageUrl = AssetHelper::getThumbnailUrl($image,
                            AssetHelper::MAGENTO_THUMB_PREVIEW_PROFILE_NAME);
                        $timeThumbGenerationEnd = microtime(true);
                        $this->vMessage('Preview generated in ' . ($timeThumbGenerationEnd - $timeThumbGenerationStart) . ' sec : ' . $imageUrl);
                    } else {
                        $imageUrl = AssetHelper::getImageUrl($image);
                        $this->vMessage('original has right size, no preview, instead : ' . $imageUrl);
                    }

                    if ($i == 1) {
                        $baseImages = $thumbnailImage = $smallImage = basename($imageUrl);
                    } else {
                        $additionalImages[] = basename($imageUrl);
                    }
                } catch (\Exception $e) {

                    $errorMessage = PHP_EOL . ' | Produit : ' . $this->inProgressProduct->getFullPath() . PHP_EOL;
                    if ($image) {
                        $errorMessage .= ' | Image : ' . $image->getFullPath() . PHP_EOL;
                    }
                    $errorMessage .= ' | Message : ' . $e->getMessage();
                    $this->logWarning(self::WARN_GLOBAL_ASSET_PREVIEW_ERROR, $errorMessage);
                }
                $i++;
            }
            $result = array(
                'base' => AssetHelper::getCleanFilename($baseImages),
                'thumbnail' => AssetHelper::getCleanFilename($thumbnailImage),
                'small' => AssetHelper::getCleanFilename($smallImage),
                'additional' => implode($this->getDefaultMultiValueSeparator(), array_map(function ($additionalImage) {
                    return AssetHelper::getCleanFilename($additionalImage);
                }, $additionalImages))
            );
        }
        return $result;
    }

    /**
     * @return array
     */
    public function getConditioning()
    {
        $conditioning = intval($this->inProgressProduct->getPackaging());

        $conditioningValues = array("use_config" => 1, "enable" => 0, "conditioning" => 1);
        if ($conditioning > 1) {
            $conditioningValues['use_config'] = 0;
            $conditioningValues['enable'] = 1;
            $conditioningValues['conditioning'] = $conditioning;
        }

        return $conditioningValues;
    }


    protected function postProcessRow()
    {
        // reset_images will go back to off if was on, but product-asset exporter will be in charge of that
    }

    /**
     * @return bool
     */
    protected function isMonoStore()
    {
        if ($this->isMonoStore === null) {
            $list = new DataObject\StoreView\Listing();
            if ($list->getCount() == 1) {
                $this->isMonoStore = true;
            } else {
                $this->isMonoStore = false;
            }
        }
        return $this->isMonoStore;
    }

    /**
     * @param string|null $text
     *
     * @return string|null
     */
    protected function cleanHtml(?string $text)
    {

        $acceptedMarkup = [
            "!doctype",
            "a",
            "abbr",
            "address",
            "area",
            "article",
            "aside",
            "audio",
            "b",
            "base",
            "bdi",
            "bdo",
            "blockquote",
            "body",
            "br",
            "button",
            "canvas",
            "caption",
            "cite",
            "code",
            "col",
            "colgroup",
            "command",
            "datalist",
            "dd",
            "del",
            "details",
            "dfn",
            "div",
            "dl",
            "dt",
            "em",
            "embed",
            "fieldset",
            "figcaption",
            "figure",
            "footer",
            "form",
            "h1",
            "h2",
            "h3",
            "h4",
            "h5",
            "h6",
            "head",
            "header",
            "hgroup",
            "hr",
            "html",
            "i",
            "iframe",
            "img",
            "input",
            "ins",
            "keygen",
            "kbd",
            "label",
            "legend",
            "li",
            "link",
            "map",
            "mark",
            "math",
            "menu",
            "meta",
            "meter",
            "nav",
            "noscript",
            "object",
            "ol",
            "optgroup",
            "option",
            "output",
            "p",
            "param",
            "pre",
            "progress",
            "q",
            "rp",
            "rt",
            "ruby",
            "s",
            "samp",
            "script",
            "section",
            "select",
            "small",
            "source",
            "span",
            "strong",
            "style",
            "sub",
            "summary",
            "sup",
            "svg",
            "table",
            "tbody",
            "td",
            "textarea",
            "tfoot",
            "th",
            "thead",
            "time",
            "title",
            "tr",
            "track",
            "u",
            "ul",
            "var",
            "video",
            "wbr"
        ];

        $acceptedAttributes = [
            "class",
            "id",
            "href",
            "src",
            "target",
            "width",
            "height"
        ];

        if (isset($text)) {
            if (WebsiteSetting::getByName('clean_html_on_export')->getData() == true) {
                $acceptedMarkupString = implode("|", $acceptedMarkup);
                $acceptedAttributesString = implode("|", $acceptedAttributes);
                $regex = "(<(" . $acceptedMarkupString . ")\s+)(((?!((" . $acceptedAttributesString . ")=[\"'][^\"']*[\"'])).\s*)*)(((" . $acceptedAttributesString . ")=[\"'][^\"']*[\"']\s*)*)(((?!((" . $acceptedAttributesString . ")=[\"'][^\"']*[\"'])).\s*)*)(((" . $acceptedAttributesString . ")=[\"'][^\"']*[\"']\s*)*)(((?!((" . $acceptedAttributesString . ")=[\"'][^\"']*[\"'])).\s*)*)(\s*\/?>)|(<\/(" . $acceptedMarkupString . ")>)|(.*)";
                $text = preg_replace("/" . $regex . "/U",
                    "$1$7$11$14$21$24$22", $text);
            }
            $text = preg_replace("/\r|\n/", "", $text);
        }
        return $text;
    }
}
