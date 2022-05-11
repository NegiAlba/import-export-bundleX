<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2017 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Processor\Exporter;


class Category extends AbstractExporter
{

    public $exportFileName = 'category.csv';

    public $inProgressCategory;

    public $baseColumns = array(
        'id',
        'name',
        'parent_id',
        'is_active',
        'is_anchor',
        'include_in_menu',
        'custom_use_parent_settings',
        'description',
        'url_key',
        'image',
        'display_logo',
        'display_description'
    );

    public $loggerComponent = 'Export des catégories';


    /**
     * @throws \Exception
     */
    public function process()
    {
    }

    public static function canExport($category): bool
    {
        return true;
    }

    /**
     * @return array
     */
    public function getRow($category): array
    {
        return [];
    }

}
