<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2017 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Processor\Exporter;

use Doctrine\DBAL\DBALException;
use Exception;
use Galilee\ImportExportBundle\Helper\AssetHelper;
use Galilee\ImportExportBundle\Helper\DbHelper;
use Pimcore\Db;
use Pimcore\Model\DataObject;


class ProductAsset extends AbstractExporter
{
    public $exportFileName = 'images.zip';
    protected $filesCount = 0;
    protected $exportImagesPath;

    /**
     * @var Db\Connection
     */
    protected $db;

    public $loggerComponent = 'Export des assets produits';


    /**
     * @throws Exception
     */
    public function process()
    {
        $t1 = microtime(true);
        $this->writeInfo('Export des assets des produits modifiés après le : ' . $this->exportDate);

        $this->exportImagesPath = $this->exportPath;
        if (!is_dir($this->exportImagesPath)) {
            mkdir($this->exportImagesPath, 0755, true);
        }

        $productDbHelper = new DbHelper(DataObject\Product::class, 'Product');

        $this->db = Db::get();
        $sql = $this->getQuery();
        $products = $this->db->fetchAll($sql);
        foreach ($products as $product) {
            $this->copyProductAssets($product);
            $this->postProcessProduct($product, $productDbHelper);
        }

        $this->vMessage(printf("%f secondes", microtime(true) - $t1));
    }

    /**
     * @param array $product
     *
     * @throws DBALException
     */
    protected function copyProductAssets(array $product)
    {
        $this->copyTmpAsset($product['images'], $product['resetImages']);
    }

    /**
     * @param $assetDbString ,asset:294,
     * @param bool $isResetImages
     *
     * @throws DBALException
     */
    protected function copyTmpAsset($assetDbString, $isResetImages = false)
    {
        $assets = explode(',', $assetDbString);
        foreach ($assets as $asset) {
            if ($asset) {
                list($type, $id) = explode('|', $asset);
                if ($type != 'folder' && $id) {
                    $sql = $this->getQueryAsset($id, $isResetImages);
                    $result = $this->db->fetchAssoc($sql);

                    if (AssetHelper::PreviewExist($id, $result['path'], $result['filename'], AssetHelper::MAGENTO_THUMB_PREVIEW_PROFILE_NAME)) {
                        $fileSystemPath = AssetHelper::getPreviewFileSystemPath($id, $result['path'], $result['filename'], AssetHelper::MAGENTO_THUMB_PREVIEW_PROFILE_NAME);
                    } else {
                        $fileSystemPath = PIMCORE_ASSET_DIRECTORY . $result['fullPath'];
                    }
                    if (isset($result['filename'])) {
                        $to = $this->exportImagesPath . AssetHelper::getCleanFilename(basename($fileSystemPath));
                        if (!file_exists($to) && @copy($fileSystemPath, $to)) {
                            $this->vMessage('Copy ' . $fileSystemPath . ' TO ' . $to);
                            $this->filesCount++;
                        }
                    }
                }
            }
        }
    }

    protected function createZip()
    {
        if ($this->filesCount) {
            $this->writeInfo('Nombre d\'assets exportés : ' . $this->filesCount);
            $zip = new \ZipArchive();
            $filename = $this->exportPath . $this->exportFileName;
            if ($zip->open($filename, \ZipArchive::CREATE)) {
                $options = array('add_path' => 'images/', 'remove_all_path' => true);
                $zip->addGlob($this->exportImagesPath . '*', GLOB_BRACE, $options);
                $zip->close();
            }
            $this->writeInfo('Création de l\'archive ' . $this->exportPath . $this->exportFileName);
            recursiveDelete($this->exportImagesPath);
        }
    }

    /**
     * @return string
     */
    public function getQuery()
    {
        $exportDate = strtotime($this->exportDate);
        $productTable = sprintf('object_%d', DataObject\Product::classId());
        $sql = sprintf(
            'SELECT' .
            ' oo_id,' .
            ' resetImages,' .
            ' images' .
            ' FROM %s' .
            ' WHERE o_modificationDate >= %d ',
            $productTable, $exportDate);
        return $sql;
    }

    /**
     * @param $id
     * @param bool | null $isResetImages
     *
     * @return string
     */
    protected function getQueryAsset($id, $isResetImages)
    {

        $sql = sprintf(
            'SELECT' .
            ' filename,' .
            ' path,' .
            ' CONCAT(path, filename) AS fullPath' .
            ' FROM assets' .
            ' WHERE id = %d AND type <> \'folder\' '
            , $id);

        // if no reset image  (NULL || 0), only export new ones
        if (!$isResetImages) {
            $exportDate = strtotime($this->exportDate);
            // no o_ on this one
            $sql .= sprintf('AND modificationDate >= %d', $exportDate);
        }
        $this->vvMessage($sql . PHP_EOL);
        return $sql;
    }

    /**
     * @param $product
     * @param DbHelper $productDbHelper
     */
    protected function postProcessProduct($product, DbHelper $productDbHelper)
    {
        if ($product['resetImages']) {
            $productDbHelper->update($product['oo_id'], ['resetImages' => 0], false);
        }
    }


}
