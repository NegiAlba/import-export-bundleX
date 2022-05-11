<?php

namespace Galilee\ImportExportBundle;

use Pimcore\Extension\Bundle\AbstractPimcoreBundle;
use Pimcore\Model\User;

class GalileeImportExportBundle extends AbstractPimcoreBundle
{
    const PLUGIN_NAME = 'PluginImportExport';

    const PLUGIN_VAR_PATH = PIMCORE_PROJECT_ROOT . DIRECTORY_SEPARATOR
    . 'var' . DIRECTORY_SEPARATOR
    . 'plugins' . DIRECTORY_SEPARATOR
    . 'PluginImportExport' . DIRECTORY_SEPARATOR;

    const CONFIG_FILE = self::PLUGIN_VAR_PATH
    . 'config' . DIRECTORY_SEPARATOR . 'config.xml';

    const CONFIG_PROCESSOR_FILE = self::PLUGIN_VAR_PATH
    . 'config' . DIRECTORY_SEPARATOR . 'configProcessor.xml';

    const IMPORT_FILE_PATH = self::PLUGIN_VAR_PATH . 'import' . DIRECTORY_SEPARATOR;

    const EXPORT_FILE_PATH = self::PLUGIN_VAR_PATH . 'export' . DIRECTORY_SEPARATOR;

    const REPORT_FILE_PATH = self::PLUGIN_VAR_PATH . 'report' . DIRECTORY_SEPARATOR;

    const USER_NAME_IMPORTER = 'importer';

    public function getVersion()
    {
        return '2.0.0';
    }

    public function getInstaller()
    {
        return $this->container->get(Service\Installer::class);
    }

    public function getJsPaths()
    {
        return [
            '/bundles/galileeimportexport/js/pimcore/startup.js'
        ];
    }

    public function getCssPaths()
    {
        return [
            '/bundles/galileeimportexport/css/style.css'
        ];
    }


    public static function getUserImporter()
    {
        return User::getByName(self::USER_NAME_IMPORTER);
    }

    public static function getUserIdImporter()
    {
        $user = User::getByName(self::USER_NAME_IMPORTER);
        return $user ? $user->getId() : 1;
    }
}