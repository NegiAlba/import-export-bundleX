<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2017 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Helper;

use Pimcore\File;
use Pimcore\Model\DataObject;

class ObjectHelper
{

    /**
     * @deprecated Use DataObject\Service::createFolderByPath
     *
     * @param $fullPath
     * @return null|DataObject\AbstractObject|DataObject\Folder
     * @throws \Exception
     */
    public static function createObjectFolder($fullPath)
    {
        $folder = null;
        if (DataObject\Service::pathExists($fullPath)) {
            $folder = DataObject\Folder::getByPath($fullPath);
        }
        if (!$folder) {
            $parts = explode(DIRECTORY_SEPARATOR, trim($fullPath));
            $path = DIRECTORY_SEPARATOR;
            foreach ($parts as $part) {
                $part = File::getValidFilename($part);

                if (DataObject\Service::pathExists($path . $part)) {
                    $folder = DataObject::getByPath($path . $part);
                } else {
                    $parentFolder = DataObject::getByPath($path);
                    $folder = new DataObject\Folder();
                    $folder->setType('folder');
                    $folder->setParentId($parentFolder->getId());
                    $folder->setUserOwner(1);
                    $folder->setUserModification(1);
                    $folder->setKey($part);
                    $folder->save();
                }
                $path .= $part . DIRECTORY_SEPARATOR;
            }
        }
        return $folder;
    }

    public static function getProductWithVariantBy($field, $value)
    {
        $list = new DataObject\Product\Listing();
        $list->setObjectTypes([DataObject\AbstractObject::OBJECT_TYPE_VARIANT, DataObject\AbstractObject::OBJECT_TYPE_OBJECT])
            ->setCondition($field . ' = ? ', [$value])
            ->setLimit(1)
            ->setUnpublished(true)
            ->load();

        return $list->current();
    }
}
