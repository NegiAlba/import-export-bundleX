<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2017 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Processor\Exporter;

use Galilee\ImportExportBundle\Helper\CsvWriter;
use Pimcore\Cache;
use Pimcore\Db;
use Pimcore\Db\Connection;
use Pimcore\Model\DataObject;

class Stock extends AbstractExporter
{

    const STOCK_MODIFIED_ATTR = 'stockModified';
    const NAME_ATTR = 'name';
    const QUANTITY_ATTR = 'qty';

    protected $exportFileName = 'stock.csv';

    public $product;

    /** @var Connection */
    protected $db;

    public $loggerComponent = 'Export des stocks';


    public function preProcess()
    {
        $this->exportFileName = date("Y-m-d-H-i-s") . '_' . $this->exportFileName;
    }


    public function getAgencies()
    {
        $stockTable = sprintf('object_%d', DataObject\Stock::classId());
        $sql = 'SELECT DISTINCT(agencyCode) FROM `%s`';
        return $this->db->fetchCol(sprintf($sql, $stockTable));
    }

    public function getQuery($count = false)
    {
        $productTable = sprintf('object_%d', DataObject\Product::classId());
        $stockTable = sprintf('object_%d', DataObject\Stock::classId());
        if ($count) {
            $sql = 'SELECT COUNT(p.oo_id)'
                . ' FROM `%s` as p'
                . ' WHERE p.' . static::NAME_ATTR . ' != \'\''
                . ' AND p.categories != \'\'';
            $sql = sprintf($sql, $productTable);
        } else {
            $sql = 'SELECT p.oo_id as id, p.sku, p.qty as qty,'
                . ' (SELECT GROUP_CONCAT(s.qty, \':\', s.agencyCode SEPARATOR \'|\') FROM %s s WHERE s.product__id = p.oo_id) as agenciesQty'
                . ' FROM `%s` as p'
                . ' WHERE p.' . static::NAME_ATTR . ' != \'\''
                . ' AND p.categories != \'\'';
            $sql = sprintf($sql, $stockTable, $productTable);
        }

        if (!$this->exportAll) {
            $sql .= ' AND ' . static::STOCK_MODIFIED_ATTR . ' = 1';
        }

        return $sql;
    }

    public function process()
    {
        if (!$this->exportAll) {
            $this->writeInfo('Export des stocks des produits modifiés depuis le dernier export.');
        } else {
            $this->writeInfo('Export des stocks de tous les produits.');
        }

        $this->db = Db::get();
        $sql = $this->getQuery();
        $totalCount = $this->db->fetchColumn($this->getQuery(true));
        $this->writeInfo('Nombre de stock(s) produit : ' . $totalCount);
        $agencies = $this->getAgencies();

        if ($totalCount) {
            $ids = array();
            $csvWriter = new CsvWriter($this->getCsvFileFullPath());
            $header = $this->getColumnNames($agencies);
            $csvWriter->addRow($header);
            $stmt = $this->db->query($sql);
            while ($product = $stmt->fetch()) {
                $row = $this->getRow($product, $header);
                if ($row) {
                    $csvWriter->addRow($row);
                    $ids[] = $product['id'];
                }
            }
            $csvWriter->close();
            $this->vMessage('Mise à jour du flag "exporté".');
            $this->setStockModified($ids);
            $this->writeInfo('Fichier d\'export  : ' . $this->getCsvFileFullPath());
            Cache::clearAll();
        }
    }

    /**
     * @param $productSku
     */
    protected function setStockModified($ids)
    {
        $chunks = array_chunk($ids, 100);
        foreach ($chunks as $chunk) {
            $this->updateTable($chunk, sprintf('object_query_%d', DataObject\Product::classId()));
            $this->updateTable($chunk, sprintf('object_store_%d', DataObject\Product::classId()));
        }
    }

    /**
     * Set the stockModified flag to false in the given table for the given product
     *
     * @param $chunk ids array
     * @param $tableName
     * @return int
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function updateTable($chunk, $tableName)
    {
        $inids = "'" . implode('\', \'', $chunk) . "'";
        $query = sprintf(
            'UPDATE `%s` SET `%s` = \'0\' WHERE `oo_id` IN (%s)',
            $tableName,
            static::STOCK_MODIFIED_ATTR,
            $inids
        );
        return $this->db->exec($query);
    }

    /**
     * Get CSV Header columns.
     *
     * @return array
     */
    public function getColumnNames(array $agencies): array
    {
        return array_merge(['sku', 'qty'], $agencies);
    }

    public function getRow($product, $header)
    {
        $result = null;
        $product = $this->formatProductRow($product);
        //Don't export product stock if it doesn't exist, otherwise the Magento import will not work
        if ($product[static::QUANTITY_ATTR] != null && $product[static::QUANTITY_ATTR] != '') {
            foreach ($header as $col) {
                $result[$col] = $product[$col];
            }
        }
        return $result;
    }

    /**
     * 'agenciesQty => '119:1_62110|52:2_59160|30:3_80450|413:4_59309|96:5_59640|332:6_59100|138:8_59280|489:9_60740'
     *
     * @param $product
     */
    public function formatProductRow($product)
    {
        $quantities = explode('|', $product['agenciesQty']);
        unset($product['agenciesQty']);
        foreach ($quantities as $qtyString) {
            $tmp = explode(':', $qtyString);
            $product[$tmp[1]] = $tmp[0];
        }
        return $product;
    }


}
