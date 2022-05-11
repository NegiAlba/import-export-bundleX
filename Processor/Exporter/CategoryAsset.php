<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2017 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Processor\Exporter;

use Pimcore\Model\DataObject;
use Pimcore\Model\Asset as ModelAsset;


class CategoryAsset extends AbstractExporter
{

    public $exportFileName = 'images.zip';
    public $loggerComponent = 'Export des assets catégories';

    /**
     * @throws \Exception
     */
    public function process()
    {
        $modifiedAfter = strtotime($this->exportDate);
        $categories = DataObject\Category::getList();
        $categories->setObjectTypes(self::OBJECT_TYPES);
        $categories->setUnpublished(true);
        $categories->setCondition("o_modificationDate >= ?", [$modifiedAfter]);
        $categories->load();
        $this->writeInfo('Export des assets des catégories modifiées après le : ' . $this->exportDate);

        if (count($categories)) {

            $cpt = 0;
            $filesCount = 0;

            // Create directory
            $exportPath = $this->exportPath;
            $exportImagesPath = $exportPath . 'images_tmp' . DIRECTORY_SEPARATOR;

            if (!is_dir($exportImagesPath)) {
                mkdir($exportImagesPath, 0755, true);
            }

            /** @var DataObject\Category $category */
            foreach ($categories as $category) {

                $assets = array();
                if ($category->getImage()) {
                    array_push($assets, $category->getImage());
                }
                if ($assets) {
                    $filesCount += $this->copyAssetFiles($exportImagesPath, $assets);
                }
                if ($cpt % 1000 === 0) {
                    \Pimcore::collectGarbage();
                }
            }

            $this->writeInfo('Nombre d\'assets exportés : ' . $filesCount);

            if ($filesCount) {
                $zip = new \ZipArchive();
                $filename = $exportPath . $this->exportFileName;
                if ($zip->open($filename, \ZipArchive::CREATE)) {
                    $options = array('add_path' => 'images/', 'remove_all_path' => true);
                    $zip->addGlob($exportImagesPath . '*', GLOB_BRACE, $options);
                    $zip->close();
                }
                $this->writeInfo('Création de l\'archive ' . $exportPath . $this->exportFileName);
            }
            recursiveDelete($exportImagesPath);
        }
    }

    public function getAssets($category)
    {
        if ($category->getDocuments()) {
            $assets = array_merge($category->getImages(), $category->getDocuments());
        } else {
            $assets = $category->getImages();
        }
        return $assets;
    }

    /**
     * @param string $exportPath
     * @param $assets
     * @return int
     */
    protected function copyAssetFiles($exportPath, $assets)
    {
        $count = 0;
        /** @var ModelAsset $asset */
        foreach ($assets as $asset) {
            if ($asset->getType() != 'folder') {
                if (@copy($asset->getFileSystemPath(), $exportPath . $asset->getFilename())) {
                    $count++;
                }
            }
        }
        return $count;
    }

}
