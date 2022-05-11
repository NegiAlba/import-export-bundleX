<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2017 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Helper;

use Exception;
use Pimcore\Model\DataObject;

class FamilyHelper
{

    const PARENT_FOLDER_FAMILY_KEY = 'products';

    const PARENT_FOLDER_BRAND_KEY = 'brands';

    public static function getByCode($code)
    {
        return DataObject\Family::getByPath(
            '/' . FamilyHelper::PARENT_FOLDER_FAMILY_KEY . '/' . $code
        );
    }

}
