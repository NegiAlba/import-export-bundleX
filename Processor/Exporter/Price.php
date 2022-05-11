<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2017 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Processor\Exporter;

use Pimcore\Db;
use Galilee\ImportExportBundle\Helper\CsvWriter;
use Pimcore\Model\DataObject;

class Price extends AbstractExporter
{

    public $exportFileName = 'customer_price.csv';
    public $loggerComponent = 'Export des prix par clients';

    public function getQuery($nextId = 0)
    {
        $exportDate = strtotime($this->exportDate);
        $priceTable = sprintf('object_%d', DataObject\Price::classId());
        $productTable = sprintf('object_%d', DataObject\Product::classId());
        $sql = sprintf(
            'SELECT ' .
            'product.sku, ' .
            'price.customerId as `code_client`, ' .
            'price.basePrice as `prix_base`, ' .
            'price.netPrice as `prix_net` ,' .
            'price.oo_id as id ' .
            'FROM %s price ' .
            'LEFT JOIN %s product ON price.product__id = product.oo_id ' .
            'WHERE price.o_modificationDate >= %d AND price.oo_id > %d ' .
            'ORDER BY price.oo_id ASC ' .
            'LIMIT 1000000',
            $priceTable, $productTable, $exportDate, $nextId);
        return $sql;
    }

    public function process()
    {
        $this->writeInfo(
            '[Début] Export des prix par client modifiés depuis le '
            . date('d/m/Y h:i:s', strtotime($this->exportDate))
        );
        $db = Db::get();
        $moreResult = true;
        $csvSize = 1000000;
        $i = 0;
        $initNewFile = true;
        $now = date('Y-m-d-H-i-s');
        $exportedCount = 0;
        $lastId = 0;
        while ($moreResult) {
            $query = $this->getQuery($lastId);
            $stmt = $db->executeQuery($query);
            $moreResult = false;
            if ($i >= $csvSize) {
                $initNewFile = true;
                $i = 0;
            }

            while ($priceArray = $stmt->fetch()) {

                if ($initNewFile) {
                    $initNewFile = false;
                    if (isset($csvWriter) && isset($splitFilename)) {
                        $csvWriter->close();
                        $this->vMessage('Fichier d\'export  : ' . $splitFilename);
                    }
                    $splitFilename = $this->exportPath
                        . $now
                        . '_' . str_pad($exportedCount, 11, '_', STR_PAD_LEFT)
                        . '_' . $this->exportFileName;
                    $csvWriter = new CsvWriter($splitFilename);
                    $csvWriter->addRow($this->getColumnNames());
                }

                $row = $this->getRow($priceArray);
                if ($row) {
                    $csvWriter->addRow($row);
                    $lastId = $priceArray['id'];
                    $exportedCount++;
                }
                $moreResult = true;
                $i++;
            }
        }
        if (isset($csvWriter) && isset($splitFilename)) {
            $csvWriter->close();
            $this->vMessage('Fichier d\'export  : ' . $splitFilename);
        }
        $this->writeInfo('[Fin] Nombre de prix exportés : ' . $exportedCount);
    }

    public function getColumnNames()
    {
        return array(
            'sku',
            'code_client',
            'prix_base',
            'prix_net',
        );
    }

    public function getRow($priceArray)
    {
        return array(
            $priceArray['sku'],
            $priceArray['code_client'],
            $priceArray['prix_base'],
            $priceArray['prix_net'],
        );
    }
}
