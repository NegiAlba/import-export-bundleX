<?php

namespace Galilee\ImportExportBundle\Command;

use Galilee\ImportExportBundle\Helper\DbHelper;
use Pimcore\Cache;
use Pimcore\Console\AbstractCommand;
use Pimcore\Db;
use Pimcore\Model\DataObject\Brand;
use Pimcore\Model\DataObject\Category;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\Supplier;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InitGridFilterFieldsCommand extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('socoda:product:init-grid-filter-fields')
            ->setDescription('Init product grid filter field values');
        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $productDbHelper  = new DbHelper(Product::class, 'Product');
        $categoryDbHelper = new DbHelper(Category::class, 'Category');
        $brandDbHelper    = new DbHelper(Brand::class, 'Brand');
        $supplierDbHelper = new DbHelper(Supplier::class, 'Supplier');

        $db = Db::get();

        $queryAllCategories = 'SELECT oo_id, codeCategory, name FROM ' . $categoryDbHelper->getQueryTableName();
        $queryAllBrands     = 'SELECT oo_id, name FROM ' . $brandDbHelper->getQueryTableName();
        $queryAllSuppliers  = 'SELECT oo_id, name FROM ' . $supplierDbHelper->getQueryTableName();
        $queryAllProducts   = 'SELECT oo_id, sku, categories, brand, supplier, categoryGridFilter, brandGridFilter, supplierGridFilter FROM ' . $productDbHelper->getQueryTableName();

        $categoriesById = [];
        foreach ($db->fetchAll($queryAllCategories) as $categoryRow) {
            $categoriesById[$categoryRow['oo_id']] = $categoryRow;
        }

        $brandsById = [];
        foreach ($db->fetchAll($queryAllBrands) as $brandRow) {
            $brandsById[$brandRow['oo_id']] = $brandRow;
        }

        $suppliersById = [];
        foreach ($db->fetchAll($queryAllSuppliers) as $supplierRow) {
            $suppliersById[$supplierRow['oo_id']] = $supplierRow;
        }

        foreach ($db->fetchAll($queryAllProducts) as $productRow) {
            $categoryGridFilterArray = [];
            $categoryIds             = explode(',', trim($productRow['categories'], ','));
            foreach ($categoryIds as $categoryId) {
                if (isset($categoriesById[$categoryId])) {
                    $categoryGridFilterArray[] = $categoriesById[$categoryId]['codeCategory'] . ' - ' . $categoriesById[$categoryId]['name'];
                }
            }
            $categoryGridFilter = (!empty($categoryGridFilterArray)) ? implode('|', $categoryGridFilterArray) : '';

            $brandId         = trim($productRow['brand'], ',');
            $brandGridFilter = $brandsById[$brandId]['name'];

            $supplierId         = trim($productRow['supplier'], ',');
            $supplierGridFilter = $suppliersById[$supplierId]['name'];

            $data = [
                'categoryGridFilter' => $categoryGridFilter,
                'brandGridFilter'    => $brandGridFilter,
                'supplierGridFilter' => $supplierGridFilter,
            ];

            $db->update($productDbHelper->getQueryTableName(), $data, ['oo_id' => $productRow['oo_id']]);
            $db->update($productDbHelper->getStoreTableName(), $data, ['oo_id' => $productRow['oo_id']]);
        }

        Cache::clearAll();
    }
}
