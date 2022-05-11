<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2017 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Template\Exporter;

use Pimcore\Model\DataObject;
use Galilee\ImportExportBundle\Processor\Exporter\CategoryAsset as BaseCategoryAsset;


class CategoryAsset extends BaseCategoryAsset
{
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
                $filename = $exportPath . 'images.zip';
                if ($zip->open($filename, \ZipArchive::CREATE)) {
                    $options = array('add_path' => 'images/', 'remove_all_path' => true);
                    $zip->addGlob($exportImagesPath . '*', GLOB_BRACE, $options);
                    $zip->close();
                }
                $this->writeInfo('Création de l\'archive ' . $exportPath . 'images.zip');
            }
            recursiveDelete($exportImagesPath);
        }
    }
}