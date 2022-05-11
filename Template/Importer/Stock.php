<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2017 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Template\Importer;

use Exception;
use Galilee\ImportExportBundle\Helper\ConfigHelper;
use Galilee\ImportExportBundle\Helper\CsvReader;
use Galilee\ImportExportBundle\Helper\DbHelper;
use Galilee\ImportExportBundle\Helper\SplitHelper;
use Galilee\ImportExportBundle\Processor\Importer\AbstractImporter;
use Pimcore;
use Pimcore\Cache;
use Pimcore\Db;
use Pimcore\Db\Connection;
use Pimcore\File;
use Pimcore\Model\DataObject;
use Symfony\Component\Process\Process;

class Stock extends AbstractImporter
{

    const DEFAULT_SUB_FOLDER = 'stock';
    const CSV_FIELD_SKU = 'sku';
    const CSV_FIELD_QUANTITY = 'stock';

    const WARN_STOCK_PRODUCT_NOT_FOUND = 'WARN_STOCK_PRODUCT_NOT_FOUND';
    const WARN_STOCK_INVALID_STOCK = 'WARN_STOCK_INVALID_STOCK';

    protected $warningMessages = [
        self::WARN_STOCK_PRODUCT_NOT_FOUND => 'Produit non trouvé',
        self::WARN_STOCK_INVALID_STOCK => 'Stock incorrect',
    ];

    /** @var string Product quantity attribute name */
    const QUANTITY_ATTR = 'qty';

    /** @var string Product stock modified attribute name */
    const STOCK_MODIFIED_ATTR = "stockModified";

    /** @var Connection */
    protected $db;

    /** @var CsvReader */
    protected $csvReader;

    /** @var DbHelper */
    protected $stockDbHelper;

    /** @var DbHelper */
    protected $productDbHelper;

    /** @var SplitHelper */
    protected $splitHelper;

    public $mandatoryFields = [
        self::CSV_FIELD_SKU,
        self::CSV_FIELD_QUANTITY
    ];

    public $loggerComponent = 'Import des stocks';
    protected $countCreate;
    protected $countUpdate;


    /**
     * 1 - Execution de la commande galilee:import -t stock
     * 2 - Génération des fichiers splittés dans le dossier processing (splitCsv)
     * 3 - Import de chaque fichiers splittés en éxécutant la commande avec from et to (runBackgroundImport)
     *
     * @return bool
     * @throws Exception
     */
    public function process()
    {
        if (!$this->csvPath) {
            return false;
        }
        $t1 = microtime(true);
        $this->stockDbHelper = new DbHelper(DataObject\Stock::class, 'Stock');
        $this->productDbHelper = new DbHelper(DataObject\Product::class, 'Product');
        $this->splitHelper = new SplitHelper($this->csvPath, $this->getProcessingPath());

        $this->db = Db::get();

        if (!is_null($this->getFrom()) && !is_null($this->getTo())) {
            // Pas d'envoi d'email après chaque import partiel
            $this->sendReport = false;
            // Le fichier csv principal n'est pas déplacé après chaque import partiel
            $this->moveFileAfterImport = false;
            $this->processSplittedFiles();
        } else {
            $this->logInfo(self::LOG_START,
                $this->loggerComponent . '. ' . date(ConfigHelper::DATE_FORMAT, strtotime($this->startedAt)));
            $countFiles = count($this->splitHelper->getSplitFileList());
            if (file_exists($this->csvPath) && $countFiles == 0) {

                try {
                    $csvReader = $this->getCsvReader();
                } catch (Exception $e) {
                    return false;
                }
                if ($csvReader === false) {
                    return false;
                }
                $this->splitHelper->csvReader = $csvReader;
                $countFiles = $this->splitHelper->splitCsv();
            }

            if (!file_exists($this->csvPath)) {
                $this->vMessage('Aucun fichier à importer (' . $this->csvPath . ')');
            }

            if ($countFiles) {
                $this->runBackgroundImport();
                Cache::clearAll();
            }
            $timestampImportStart = strtotime($this->startedAt);
            $this->countCreate = $this->stockDbHelper->getCountCreatedFromDate($timestampImportStart);
            $this->countUpdate = $this->stockDbHelper->getCountUpdatedFromDate($timestampImportStart);
            $this->writeInfo('[TOTAL CREATION] - ' . $this->countCreate);
            $this->writeInfo('[TOTAL MISE A JOUR] - ' . $this->countUpdate);

            $this->logInfo(self::LOG_END, $this->loggerComponent . '. ' . date(ConfigHelper::DATE_FORMAT));
            $duration = microtime(true) - $t1;
            $this->vMessage(sprintf("%f secondes", $duration));
        }
    }

    protected function reportLogResume()
    {
        $resumeCount[] = 'Création : ' . $this->countCreate;
        $resumeCount[] = 'Mise à jour : ' . $this->countUpdate;
        $html = '<p>';
        $html .= '<h3>Résumé</h3>';
        $html .= '<ul><li>';
        $html .= implode('</li><li>', $resumeCount);
        $html .= '</li></ul>';
        $html .= '</p>';
        return $html;
    }


    protected function processSplittedFiles()
    {
        for ($idx = $this->getFrom(); $idx <= $this->getTo(); $idx++) {
            $fileName = $this->splitHelper->getSplitFullFileName($idx);
            if (file_exists($fileName)) {
                $this->output->writeln('Import file: ' . $fileName);
                $this->import($fileName);
                Pimcore::collectGarbage();
                unlink($fileName);
            }
        }
    }

    protected function runBackgroundImport()
    {
        $this->vMessage('RUN PROCESSES...');
        // Lance la commande pour importer les fichiers splittés
        $cmd = 'bash import-stock-splitted.sh ' . $this->getProcessingPath();
        $this->vMessage($cmd);
        $process = new Process($cmd);
        $process->setTimeout(60 * 60 * 24 * 4);

        $process->run(function ($type, $buffer) {
            if (Process::ERR === $type) {
                $this->vMessage('ERR > ' . $buffer);
                $this->writeError($buffer);
            } else {
                $this->vMessage('OUT > ' . $buffer);
            }
        });

        if (!$process->isSuccessful()) {
            $this->writeError($process->getErrorOutput());
        }
    }

    protected function import($fileName = null)
    {
        $fileName = $fileName ?: $this->csvPath;
        $csvReader = $this->getCsvReader($fileName);
        if (!$csvReader) {
            return false;
        }
        $header = $csvReader->getHeader();
        $cpt = 0;
        $t2 = microtime(true);

        while ($row = fgetcsv($csvReader->fp, $csvReader->length, $csvReader->delimiter)) {
            $productArray = $this->getProductOptimized($row[0]);
            if ($productArray) {
                // update stock
                $this->insertDb(
                    $productArray,
                    $row,
                    $header
                );
                if ($cpt++ % 1000 == 0) {
                    Pimcore::collectGarbage();
                }
                $this->vMessage('#' . $cpt);
            }
        }
        $duration = microtime(true) - $t2;
        $this->vMessage(sprintf("%f secondes", $duration));
    }

    protected function getProductOptimized($sku)
    {
        $product = $this->productDbHelper->findBy('sku', $sku, 'oo_id as id, CONCAT(o_path, o_key) as fullPath');
        if (!$product) {
            $this->logWarning(self::WARN_STOCK_PRODUCT_NOT_FOUND, 'SKU : ' . $sku);
        }
        return $product;
    }

    protected function initObject($row, $csvLineNumber)
    {
    }


    /**
     * @param $productArray
     * @param $csvRow
     * @param $header
     * @throws Exception
     */
    protected function insertDb($productArray, $csvRow, $header)
    {
        // Update
        if ($productArray) {
            $qty = $csvRow[1];
            $this->vMessage('UPDATE ' . $productArray['id']);
            $data = [
                self::QUANTITY_ATTR => $qty,
                self::STOCK_MODIFIED_ATTR => 1
            ];
            $this->productDbHelper->update($productArray['id'], $data, false);
            $folderId = $this->productDbHelper->findOrCreateFolder(
                'stock', $productArray['fullPath'] . '/', $productArray['id']
            );
            $agenciesCode = array_slice($header, 2);
            $agenciesStock = array_slice($csvRow, 2);
            foreach ($agenciesCode as $key => $code) {
                if ($code && isset($agenciesStock[$key])) {
                    $this->saveAgencyStock(
                        $code,
                        $agenciesStock[$key],
                        $productArray['id'],
                        $productArray['fullPath'],
                        $folderId
                    );
                }
            }

        } else {
            $this->vMessage('NO PRODUCT ID');
        }
    }

    public function saveAgencyStock($agencyCode, $stock, $productId, $productFullPath, $parentId)
    {
        $key = File::getValidFilename($agencyCode);

        if (!is_numeric($stock) || ($stock + 0) < 0) {
            if (!empty($stock)) {
                $this->logWarning(self::WARN_STOCK_INVALID_STOCK,
                    'Produit : ' . basename($productFullPath) . ' - Agence : ' . $agencyCode . ' - Stock : ' . $stock);
            }
            $stock = null;
        } else {
            $stock = self::getFloatVal($stock, 0);
        }

        $creationDate = $modificationDate = time();
        $path = $productFullPath . '/' . self::DEFAULT_SUB_FOLDER . '/';
        $oldStock = $this->stockDbHelper->findByPath('oo_id as id, agencyCode, qty', $key, $path);
        $data = [
            'qty' => $stock,
            'agencyCode' => $agencyCode
        ];

        if ($oldStock) {
            // Update
            if ($oldStock['qty'] != $stock) {
                $this->vMessage('UPDATE');
                $data = [
                    'qty' => $stock
                ];
                $this->stockDbHelper->update($oldStock['id'], $data, true);
            }

        } else {
            $this->vMessage('CREATION DU STOCK EN BDD.... : ' . $path . $key);
            $this->db->beginTransaction();
            try {

                $id = $this->stockDbHelper->insertObjectsTable(
                    $parentId,
                    'object',
                    $key,
                    $path,
                    time(),
                    time(),
                    DataObject\Stock::classId(),
                    'Stock'
                );

                $this->stockDbHelper->insertStoreTable($id, $data);

                $dataQuery = $data;
                $dataQuery['product__id'] = $productId;
                $dataQuery['product__type'] = 'object';
                $this->stockDbHelper->insertQueryTable(
                    $id,
                    $dataQuery
                );

                $this->stockDbHelper->insertRelationsTable(
                    $id,
                    $productId,
                    'product'
                );

                $this->db->commit();

            } catch (Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
            $this->vMessage('   - OK : ID=' . $id);
        }
    }
}
