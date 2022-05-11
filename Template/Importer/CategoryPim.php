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

class CategoryPim extends Category
{

    const COL_CODE = 'id';
    const COL_PARENT = 'parent_id';

    public $loggerComponent = 'Import des catégories PIM Socoda';

    public $mandatoryFields = [
        'id',
        'name'
    ];

    /**
     * {csv column} => {object field}
     * {csv column} => array({object field}, {method}, array {arguments})
     * @var array
     */
    public $mapping = [
        'id' => 'codeCategory',
        'name' => 'name',
        'include_in_menu' => 'visibleInMenu',
        'description' => 'description',
        'image' => array('image', 'image'),
        'display_logo' => 'displayLogo',
        'display_description' => 'displayDescription'
    ];

}
