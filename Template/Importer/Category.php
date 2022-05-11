<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2016 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Template\Importer;

use Galilee\ImportExportBundle\Helper\Tools;
use Pimcore\Model\DataObject;
use Pimcore\File;
use Galilee\ImportExportBundle\Helper\ObjectHelper;
use Galilee\ImportExportBundle\Processor\Importer\AbstractImporter;

class Category extends AbstractImporter
{

    const COL_CODE = 'codeCategory';
    const COL_PARENT = 'parentCategoryCode';

    const PARENT_FOLDER_KEY = 'categories';

    /** @var DataObject\AbstractObject */
    public $parentFolder;

    public $mandatoryFields = [
        'codeCategory',
        'name'
    ];

    /**
     * {csv column} => {object field}
     * {csv column} => array({object field}, {method}, array {arguments})
     * @var array
     */
    public $mapping = [
        'codeCategory' => 'codeCategory',
        'name' => 'name',
        'description' => 'description',
        'image' => array('image', 'image'),
        'picto' => array('picto', 'image'),
        'displayLogo' => 'displayLogo',
        'displayDescription' => 'displayDescription'
    ];

    public $loggerComponent = 'Import des catégories';


    /**
     * @param array $row
     * @param integer $csvLineNumber
     *
     * @return bool
     * @throws \Exception
     */
    protected function initObject($row, $csvLineNumber)
    {
        $code = $row[static::COL_CODE];
        $parentCode = $row[static::COL_PARENT];
        $key = File::getValidFilename($row['name'] . '-' . $code);
        $this->inProcessObject = DataObject\Category::getByCodeCategory($code, 1);
        if (!$this->inProcessObject != null) {
            try {
                $this->inProcessObject = new DataObject\Category();
                $this->inProcessObject->setKey($key);
                $this->inProcessObject->setParent($this->parentFolder);
                $this->inProcessObject->setDisplayDescription(true);
                $this->inProcessObject->setDisplayLogo(true);
                $this->inProcessObject->setVisibleInMenu(true);
            } catch (\Exception $e) {
                // Une catégorie avec le même path mais pas le même code.
                $this->logError(self::ERR_GLOBAL_SAVE_OBJECT, '. Code catégorie : ' . $code . '. Message : ' . $e->getMessage());
                return false;
            }
        } else {
            $this->setMode(self::UPDATE_MODE);
        }

        /** @var DataObject\Category $parentCategory */
        $parentCategory = DataObject\Category::getByCodeCategory($parentCode, 1);
        if ($parentCategory != null) {
            if ($parentCategory->getId() != $this->inProcessObject->getParent()->getId()) {
                $this->inProcessObject->setParent($parentCategory);
                $this->setIsUpdated(true);
            }
        }
        $this->initObjectMessage = 'Catégorie : ' . $this->inProcessObject->getFullPath();
        return true;
    }

    /**
     * @throws \Exception
     */
    public function preProcess()
    {
        $this->parentFolder = DataObject\Service::createFolderByPath(self::PARENT_FOLDER_KEY);
        return parent::preProcess();
    }

}
