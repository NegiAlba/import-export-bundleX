<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2016 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Template\Importer;

use Doctrine\DBAL\DBALException;
use Galilee\ImportExportBundle\Helper\DbHelper;
use Galilee\ImportExportBundle\Helper\ReportEmail;
use Pimcore\Cache;
use Pimcore\Db\Connection;
use Symfony\Component\Process\Process;

use Galilee\ImportExportBundle\Helper\ConfigHelper;
use Galilee\ImportExportBundle\Helper\CsvWriter;
use Pimcore\Model\DataObject;
use Pimcore\File;
use Galilee\ImportExportBundle\Processor\Importer\AbstractImporter;
use Pimcore;
use Pimcore\Db;

class Price extends AbstractImporter
{

    const DEFAULT_SUB_FOLDER = 'customer-price';

    const FIELD_SKU = 'sku';
    const FIELD_CUSTOMER_ID = 'code_client';

    const WARN_PRICE_PRODUCT_NOT_FOUND = 'WARN_PRICE_PRODUCT_NOT_FOUND';
    const WARN_PRICE_INVALID_BASE_PRICE = 'WARN_PRICE_INVALID_BASE_PRICE';
    const WARN_PRICE_INVALID_NET_PRICE = 'WARN_PRICE_INVALID_NET_PRICE';

    protected $warningMessages = [
        self::WARN_PRICE_PRODUCT_NOT_FOUND => 'Produit non trouvé',
        self::WARN_PRICE_INVALID_BASE_PRICE => 'Prix de base incorrect',
        self::WARN_PRICE_INVALID_NET_PRICE => 'Prix net incorrect',
    ];

    const INFO_PRICE_DELETE_BASE_PRICE = 'INFO_PRICE_DELETE_BASE_PRICE';
    const INFO_PRICE_DELETE_NET_PRICE = 'INFO_PRICE_DELETE_NET_PRICE';
    const INFO_PRICE_CREATE_BASE_PRICE_NO_VALUE = 'INFO_PRICE_CREATE_BASE_PRICE_NO_VALUE';
    const INFO_PRICE_CREATE_NET_PRICE_NO_VALUE = 'INFO_PRICE_CREATE_NET_PRICE_NO_VALUE';

    protected $infoMessages = [
        self::INFO_PRICE_DELETE_BASE_PRICE => 'Prix de base supprimé',
        self::INFO_PRICE_DELETE_NET_PRICE => 'Prix net supprimé',
        self::INFO_PRICE_CREATE_BASE_PRICE_NO_VALUE => 'Prix de base créé sans value',
        self::INFO_PRICE_CREATE_NET_PRICE_NO_VALUE => 'Prix net créé sans value',
    ];

    /** @var Connection */
    protected $db;


    public $mandatoryFields = [
        self::FIELD_SKU,
        self::FIELD_CUSTOMER_ID
    ];

    public $loggerComponent = 'Import des prix';
    protected $countCreate;
    protected $countUpdate;
    /**
     * @var DbHelper
     */
    protected $priceDbHelper;

    /**
     * @return void
     * @throws DBALException
     */
    public function process()
    {
        $t1 = microtime(true);
        $this->db = Db::get();
        $this->priceDbHelper = new DbHelper(DataObject\Price::class, 'Price');

        if (!is_null($this->getFrom()) && !is_null($this->getTo())) {
            // Pas d'envoi d'email après chaque import partiel
            $this->sendReport = false;
            // Le fichier csv principal n'est pas déplacé après chaque import partiel
            $this->moveFileAfterImport = false;
            $this->processSplitFiles();
        } else {
            $this->logInfo(self::LOG_START, $this->loggerComponent . '. ' . date(ConfigHelper::DATE_FORMAT, strtotime($this->startedAt)));

            // import start for log info
            $dateImportStart = new \DateTime();
            $timestampImportStart = $dateImportStart->getTimestamp();

            $countFiles = count($this->getSplitFileList());

            if (file_exists($this->csvPath) && $countFiles == 0) {
                $countFiles = $this->splitCsv();
            }
            if ($countFiles) {
                $this->runBackgroundImport($countFiles);
                Cache::clearAll();
            }

            $this->countCreate = $this->priceDbHelper->getCountCreatedFromDate($timestampImportStart);
            $this->countUpdate = $this->priceDbHelper->getCountUpdatedFromDate($timestampImportStart);
            $this->writeInfo('Création : ' . $this->countCreate);
            $this->writeInfo('Mise à jour : ' . $this->countUpdate);
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

    protected function processSplitFiles()
    {
        for ($idx = $this->getFrom(); $idx <= $this->getTo(); $idx++) {
            $fileName = $this->getSplitFullFileName($idx);
            if (file_exists($fileName)) {
                $this->output->writeln('Import file: ' . $fileName);
                $this->import($fileName);
                Pimcore::collectGarbage();
                unlink($fileName);
            }
        }
    }

    protected function getSplitFileList()
    {
        $splitFiles = [];
        $pattern = $this->getProcessingPath() . '*.csv';
        foreach (glob($pattern) as $filename) {
            $splitFiles[] = $filename;
        }
        return $splitFiles;
    }

    protected function runBackgroundImport($countFiles)
    {
        $this->vMessage('RUN PROCESSES...');
        // Lance la commande pour importer les fichiers splittés
        $cmd = 'bash import-price-splitted.sh ' . $this->getProcessingPath();
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


    protected function splitCsv()
    {
        $this->vMessage('Split...');

        try {
            $csvReader = $this->getCsvReader();
        } catch (\Exception $e) {
            return false;
        }

        if (!$csvReader) {
            return false;
        }

        $this->vMessage('Tri par produit...');
        $byProducts = [];

        while ($row = fgetcsv($csvReader->fp, $csvReader->length, $csvReader->delimiter)) {
            $arrayTemp = [$row[1], $row[2]];
            $arrayTemp[] = isset($row[3]) ? $row[3] : null;
            $byProducts[$row[0]][] = $arrayTemp;
        }

        $idx = 1;
        $lineInFile = 1;
        $minInFile = 1000;
        /** @var CsvWriter $csvWriter */
        $csvWriter = $this->newCsvWriter($idx, $csvReader->getHeader());

        foreach ($byProducts as $sku => $byProduct) {

            foreach ($byProduct as $prices) {
                $csvWriter->addRow([$sku, $prices[0], $prices[1], $prices[2]]);
                $lineInFile++;
            }

            // Nouveau fichier ?
            if ($lineInFile > $minInFile) {
                $csvWriter->close();
                $this->vMessage(' SplitCsv: - ' . $idx);
                $lineInFile = 1;
                $idx++;
                $csvWriter = $this->newCsvWriter($idx, $csvReader->getHeader());
            }
        }
        if ($csvWriter) {
            $csvWriter->close();
        }

        return $idx;
    }

    /**
     * @param $idx
     * @param $header
     *
     * @return CsvWriter
     */
    protected function newCsvWriter($idx, $header)
    {
        $splitName = $this->getSplitFullFileName($idx);
        $csvWriter = new CsvWriter($splitName);
        $csvWriter->separator = ';';
        $csvWriter->addRow($header);
        return $csvWriter;
    }


    protected function getSplitFullFileName($idx)
    {
        $filename = basename($this->csvPath);
        return $this->getProcessingPath() . $filename . '.' . str_pad($idx, 11, 0, STR_PAD_LEFT) . '.csv';
    }

    protected function import($fileName = null)
    {
        $fileName = $fileName ?: $this->csvPath;
        try {
            $csvReader = $this->getCsvReader($fileName);
        } catch (\Exception $e) {
            return false;
        }

        if (!$csvReader) {
            return false;
        }

        $byProducts = [];
        $total = 0;

        while ($row = fgetcsv($csvReader->fp, $csvReader->length, $csvReader->delimiter)) {
            $byProducts[$row[0]][] = [$row[1], $row[2], $row[3]];
            $total++;
        }

        $cpt = 0;
        $t2 = microtime(true);
        foreach ($byProducts as $sku => $byProduct) {
            //load product one. If product OK :
            $productArray = $this->getProductOptimized($sku);
            if ($productArray) {
                foreach ($byProduct as $prices) {
                    // create or update price.

                    $this->insertDb(
                        $productArray['id'],
                        $productArray['fullPath'],
                        $prices[0], // Customer id
                        $prices[1], // Base price
                        $prices[2] // net price
                    );
                    if ($cpt++ % 1000 == 0) {
                        Pimcore::collectGarbage();
                    }
                    $this->vMessage('#' . $cpt . ' / ' . $total);
                }
            }
        }
        $duration = microtime(true) - $t2;
        $this->vMessage(sprintf("%f secondes", $duration));
    }

    /**
     * @param $sku
     *
     * @return mixed
     */
    protected function getProductOptimized($sku)
    {
        $viewName = sprintf('object_%d', DataObject\Product::classId());
        $product = null;
        $productObject = null;
        $sql = sprintf('SELECT oo_id as id, CONCAT(o_path, o_key) as fullPath  FROM %s product WHERE sku = \'%s\' LIMIT 1',
            $viewName, $sku);
        try {
            $product = $this->db->fetchRow($sql);
        } catch (DBALException $DBALException) {
            $this->writeError('Une erreur technique est survenue sur le SKU ' . $sku . ' : ' . $DBALException->getMessage());
        }
        if (!$product) {
            $this->logWarning(self::WARN_PRICE_PRODUCT_NOT_FOUND, 'SKU : ' . $sku);
        }
        return $product;
    }

    protected function initObject($row, $csvLineNumber)
    {

    }


    /**
     * @param $productId
     * @param $productFullPath
     * @param $customerId
     * @param $basePrice
     * @param $netPrice
     *
     * @throws \Doctrine\DBAL\ConnectionException
     */
    protected function insertDb($productId, $productFullPath, $customerId, $basePrice, $netPrice)
    {
        $key = File::getValidFilename($customerId);

        // if base price has uncorrect values, deletion of field
        if (!is_numeric($basePrice) || ($basePrice + 0) < 0) {
            if (!empty($basePrice)) {
                $this->logWarning(
                    self::WARN_PRICE_INVALID_BASE_PRICE,
                    'Produit : ' . basename($productFullPath) .
                    ' - Client : ' . $customerId .
                    ' - Prix de base : ' . $basePrice
                );
            }
            $basePrice = null;
        } else {
            $basePrice = self::getFloatVal($basePrice, 0);
        }

        // if net price has uncorrect values, deletion of field
        if (!is_numeric($netPrice) || ($netPrice + 0) < 0) {
            if (!empty($netPrice)) {
                $this->logWarning(
                    self::WARN_PRICE_INVALID_NET_PRICE,
                    'Produit : ' . basename($productFullPath) .
                    ' - Client : ' . $customerId .
                    ' - Prix net : ' . $netPrice
                );
            }
            $netPrice = null;
        } else {
            $netPrice = self::getFloatVal($netPrice, 0);
        }

        $creationDate = $modificationDate = time();
        $path = $productFullPath . '/' . self::DEFAULT_SUB_FOLDER . '/';
        $price = $this->findPriceDb($key, $path);

        if ($price) {

            // Update
            if ($price['basePrice'] != $basePrice || $price['netPrice'] != $netPrice) {
                $this->vMessage('UPDATE');
                $this->updateObjectsTable($price['id'], $modificationDate);
                $this->updateQueryTable($price['id'], $basePrice, $netPrice);
                $this->updateStoreTable($price['id'], $basePrice, $netPrice);
                $this->vMessage('PRIX MAJ');

                if (is_null($basePrice)) {
                    $this->logInfo(self::INFO_PRICE_DELETE_BASE_PRICE,
                        'Produit : ' . basename($productFullPath) . ' - Client : ' . $customerId);
                }
                if (is_null($netPrice)) {
                    $this->logInfo(self::INFO_PRICE_DELETE_NET_PRICE,
                        'Produit : ' . basename($productFullPath) . ' - Client : ' . $customerId);
                }
            }

        } else {
            $this->vMessage('CREATION DU PRIX EN BDD.... : ' . $path . $key);
            $this->db->beginTransaction();
            try {
                $parentId = $this->findOrCreateFolder(self::DEFAULT_SUB_FOLDER, $productFullPath . '/', $productId);
                $id = $this->insertPriceObjectsTable($parentId, $key, $path, $creationDate, $modificationDate);
                $this->insertQueryTable($id, $customerId, $basePrice, $netPrice, $productId);
                $this->insertStoreTable($id, $customerId, $basePrice, $netPrice);
                $this->insertRelationsTable($id, $productId);
                $this->db->commit();

                if (is_null($basePrice)) {
                    $this->logInfo(self::INFO_PRICE_CREATE_BASE_PRICE_NO_VALUE,
                        'Prix de base : ' . basename($productFullPath) . ' - Client : ' . $customerId);
                }
                if (is_null($netPrice)) {
                    $this->logInfo(self::INFO_PRICE_CREATE_NET_PRICE_NO_VALUE,
                        'Prix net : ' . basename($productFullPath) . ' - Client : ' . $customerId);
                }
            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
            $this->vMessage('   - OK : ID=' . $id);
        }
    }


    protected function findOrCreateFolder($key, $path, $parentId)
    {
        $id = null;
        $sql = sprintf('SELECT o_id as id FROM %s WHERE o_type = \'folder\' AND o_key = \'%s\' AND o_path = \'%s\' LIMIT 1',
            'objects', $key, $path);
        $row = $this->db->fetchRow($sql);
        if ($row) {
            $id = $row['id'];
        } else {
            $id = $this->insertObjectsTable(
                $parentId,
                'folder',
                $key,
                $path,
                time(),
                time(),
                NULL,
                NULL
            );
            $this->vMessage('CREATION DOSSIER : ' . $path . $key . ' ( ID=)' . $id);
        }
        return $id;
    }

    protected function findPriceDb($key, $path)
    {
        $viewName = sprintf('object_%d', DataObject\Price::classId());
        $sql = sprintf('SELECT oo_id as id, customerId, basePrice, netPrice FROM %s WHERE o_key = \'%s\' AND o_path = \'%s\' LIMIT 1',
            $viewName, $key, $path);
        $row = $this->db->fetchRow($sql);
        return $row;
    }


    protected function insertPriceObjectsTable($parentId, $key, $path, $creationDate, $modificationDate)
    {
        return $this->insertObjectsTable(
            $parentId,
            'object',
            $key,
            $path,
            $creationDate,
            $modificationDate,
            DataObject\Price::classId(),
            'Price'
        );
    }

    /**
     * @param $parentId
     * @param $type
     * @param $key
     * @param $path
     * @param $creationDate
     * @param $modificationDate
     * @param $classId
     * @param $className
     *
     * @return string
     */
    protected function insertObjectsTable(
        $parentId,
        $type,
        $key,
        $path,
        $creationDate,
        $modificationDate,
        $classId,
        $className
    )
    {
        $tableName = 'objects';

        $data = [
            'o_id' => NULL,
            'o_parentId' => $parentId,
            'o_type' => $type,
            'o_key' => $key,
            'o_path' => $path,
            'o_index' => '0',
            'o_published' => '1',
            'o_creationDate' => $creationDate,
            'o_modificationDate' => $modificationDate,
            'o_userOwner' => 0,
            'o_userModification' => 0,
            'o_classId' => $classId,
            'o_className' => $className,
            'o_childrenSortBy' => NULL
        ];
        $this->db->insert($tableName, $data);
        return $this->db->lastInsertId();
    }

    protected function updateObjectsTable($id, $modificationDate)
    {
        $tableName = 'objects';

        $data = [
            'o_modificationDate' => $modificationDate
        ];
        return $this->db->update($tableName, $data, ['o_id' => $id]);
    }

    protected function insertQueryTable($id, $customerId, $basePrice, $netPrice, $productId)
    {
        $tableName = sprintf('object_query_%d', DataObject\Price::classId());

        $data = [
            'oo_classId' => DataObject\Price::classId(),
            'oo_className' => 'Price',
            'oo_id' => $id,
            'customerId' => $customerId,
            'basePrice' => $basePrice,
            'netPrice' => $netPrice,
            'product__id' => $productId,
            'product__type' => 'object'
        ];
        $this->db->insert($tableName, $data);

    }

    protected function updateQueryTable($id, $basePrice, $netPrice)
    {
        $tableName = sprintf('object_query_%d', DataObject\Price::classId());
        $data = [
            'basePrice' => $basePrice,
            'netPrice' => $netPrice
        ];
        return $this->db->update($tableName, $data, ['oo_id' => $id]);
    }


    protected function insertStoreTable($id, $customerId, $basePrice, $netPrice)
    {
        $tableName = sprintf('object_store_%d', DataObject\Price::classId());

        $data = [
            'oo_id' => $id,
            'customerId' => $customerId,
            'basePrice' => $basePrice,
            'netPrice' => $netPrice,
        ];
        $this->db->insert($tableName, $data);
    }

    protected function updateStoreTable($id, $basePrice, $netPrice)
    {
        $tableName = sprintf('object_store_%d', DataObject\Price::classId());
        $data = [
            'basePrice' => $basePrice,
            'netPrice' => $netPrice,
        ];
        $this->db->update($tableName, $data, ['oo_id' => $id]);
    }


    protected function insertRelationsTable($id, $productId)
    {
        $tableName = sprintf('object_relations_%d', DataObject\Price::classId());

        $data = [
            'src_id' => $id,
            'dest_id' => $productId,
            'type' => 'object',
            'fieldname' => 'product',
            'index' => 0,
            'ownertype' => 'object',
            'ownername' => '',
            'position' => 0
        ];
        $this->db->insert($tableName, $data);
    }
}
