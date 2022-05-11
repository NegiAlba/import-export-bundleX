<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2017 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Processor\Exporter;


class Product extends AbstractExporter
{

    const DEFAULT_CATEGORY = 'Default Category';
    const PRODUCT_TYPE_GROUPED = 'grouped';
    const PRODUCT_TYPE_SIMPLE = 'simple';
    const CHILD_PRODUCT_VISIBILITY = [
        '1' => 'Not Visible Individually',
        '2' => 'Catalog',
        '3' => 'Search',
        '4' => 'Catalog, Search'
    ];

    public $exportFileName = 'product.csv';

    public $inProgressProduct;

    public $baseColumns = array(
        'sku',
        // Correspond au pimcore
        'attribute_set_code',
        // corresponds à la family pimcore (A valider)
        'product_type',
        // Les produits simples sont requis car ils sont référencés par le produit groupé. Les products types sont donc , // - simple - grouped
        'categories',
        // L'arborescence des catégories (catégorie, sous-catégorie) est séparée par des "/". Dans le même champ, différentes catégories (pour un produit multi-positionnée) sont séparées par ","
        'product_websites',
        // Code du website du produit
        'name',
        // corresponds au champ name pimcore
        'description',
        // corresponds au champ description pimcore
        'short_description',
        // corresponds au champ short_description pimcore
        'weight',
        // ne corresponds à rien dans pimcore. null doit être accepté
        'product_online',
        // 1 par défaut pour tout les produits, 2 pour les produits désactivés
        'tax_class_name',
        // toujours égal à "Taxable Goods" pour l'instant
        'visibility',
        // "Catalog, Search" par défaut. "Not Visible Individually" pour les produits simples faisant parti d'au moins un produit groupé
        'price',
        // prix du produit (?)
        'special_price',
        'special_from_date',
        'special_to_date',
        'related_skus',
        'crosssell_skus',
        'upsell_skus',
        'meta_title',
        // corresponds au champ metaTitle de pimcore
        'meta_keywords',
        // corresponds au champ metaKeyword de pimcore
        'meta_description',
        // corresponds au champ metaDescription de pimcore
        'base_image',
        // image du produit
        'thumbnail_image',
        // image du produit
        'small_image',
        // image du produit
        'additional_images',
        // image du produit
        'qty',
        // correspond à la quantité du produit en stock
        'url_key',
        'is_in_stock',
        // Boolean, 1 si le produit est toujours en stock. O si le produit n'est plus en stock
        'associated_skus',
        // liste des SKUs associés (seulement dans le cas des produits groupés), séparés par des virgules
        'manufacturer',
        'brand_name',
        'quantity_price',
        'quantity_price_type',
        'new_item_unit',
        'new_item_ext_category_id',
        'use_config_enable_qty_inc',
        // Boolean, 0 si le produit a un conditionnement particulier, 1 sinon
        'enable_qty_increments',
        // Boolean, 1 si le produit a un conditionnement particulier, 0 sinon
        'qty_increments',
        // correspond au conditionnement du produit
        'id_socoda',
        'wait_import_socoda',
        'status_import_socoda',
        'category_import_socoda',
        'reset_category',
        'reset_images',
        'number_pieces_packaging',
        'packaging_unit',
        'pcre'
    );

    public $loggerComponent = 'Export des produits';


    /**
     * @throws \Exception
     */
    public function process()
    {
    }

    public static function canExport($product): bool
    {
        return true;
    }

    /**
     * @return array
     */
    public function getRow($product): array
    {
        return [];
    }

}
