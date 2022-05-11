<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2017 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Template\Exporter;

use Galilee\ImportExportBundle\Helper\CsvWriter;
use Pimcore\Db;
use Pimcore\Model\DataObject;
use Galilee\ImportExportBundle\Processor\Exporter\Category as BaseCategoryExporter;

class Category extends BaseCategoryExporter
{
    const DEFAULT_TRUE = 1;
    const DEFAULT_FALSE = 0;

    /** @var DataObject\Category */
    public $inProgressCategory;


    /**
     * will reorder by arborescence level then by id ( if A > B > C and A > D > E  => render as A > B > D > C > E  (if B.id > D.id and C.id > E.id))
     * level based on number of "/" in category.o_path , therefore pim.query builder was no longer available as a solution
     * @return string
     */
    protected function getQuery()
    {
        $exportDate = strtotime($this->exportDate);
        $categoryTable = sprintf('object_%d', DataObject\Category::classId());
        return sprintf(
            'SELECT' .
            ' oo_id, CHAR_LENGTH(o_path) - CHAR_LENGTH(REPLACE(o_path, "/", SPACE(LENGTH("/")-1))) AS `countSlashes`' .
            ' FROM %s' .
            ' WHERE o_modificationDate >= %d ' .
            ' ORDER BY countSlashes ASC, oo_id ASC ',
            $categoryTable, $exportDate);
    }


    /**
     * @throws \Exception
     */
    public function process()
    {
        $this->writeInfo('Export des catégories modifiées après le : ' . $this->exportDate);
        $db = Db::get();
        $sql = $this->getQuery();
        $categoriesArray = $db->fetchAll($sql);

        $totalCount = count($categoriesArray);
        $this->writeInfo('Nombre de catégorie(s) : ' . $totalCount);

        if ($totalCount) {

            $csvWriter = new CsvWriter($this->exportPath . $this->exportFileName);
            $csvWriter->addRow($this->baseColumns);

            foreach ($categoriesArray as $categoryArray) {
                $category = DataObject\Category::getById($categoryArray['oo_id']);
                if ($category) {
                    $this->inProgressCategory = $category;
                    if (self::canExport($category)) {
                        $row = $this->getRow($category);
                        if ($row) {
                            $csvWriter->addRow($row);
                        }
                    }
                } else {
                    $this->vMessage('Error load category ' . $categoryArray['oo_id']);
                }
            }
            $csvWriter->close();
            $this->writeInfo('Fichier d\'export  : ' . $this->exportPath . $this->exportFileName);

        }
    }

    /**
     * @param $category
     *
     * @return array
     */
    public function getRow($category): array
    {
        $image = (!empty($this->inProgressCategory->getImage()))
            ? $this->inProgressCategory->getImage()->getFilename() : null;

        $parentId = null;
        $parent = $this->inProgressCategory->getParent();

        if ($parent instanceof DataObject\Category) {
            $parentId = $parent->getCodeCategory();
        }
        $code = $this->inProgressCategory->getCodeCategory();
        $urlKey = str_replace(' ', '-', strtolower($this->inProgressCategory->getKey()));

        $row['id'] = $code;
        $row['name'] = $this->inProgressCategory->getName();
        $row['parent_id'] = $parentId;
        $row['is_active'] = self::DEFAULT_TRUE;
        $row['is_anchor'] = self::DEFAULT_TRUE;
        $row['include_in_menu'] = $this->inProgressCategory->getVisibleInMenu() ? self::DEFAULT_TRUE : self::DEFAULT_FALSE;
        $row['custom_use_parent_settings'] = self::DEFAULT_FALSE;
        $row['description'] = $this->inProgressCategory->getDescription();
        $row['url_key'] = $urlKey;
        $row['image'] = $image;
        $row['display_logo'] = $this->inProgressCategory->getDisplayLogo() ? self::DEFAULT_TRUE : self::DEFAULT_FALSE;
        $row['display_description'] = $this->inProgressCategory->getDisplayDescription() ? self::DEFAULT_TRUE : self::DEFAULT_FALSE;


        return $row;
    }

    public function preProcess()
    {
        $this->exportFileName = date("Y-m-d-H-i-s") . '_' . $this->exportFileName;
    }
}
