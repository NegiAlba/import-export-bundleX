<?php

namespace Galilee\ImportExportBundle\Service;

use Galilee\ImportExportBundle\GalileeImportExportBundle;
use Pimcore\Extension\Bundle\Installer\AbstractInstaller;
use Pimcore\File;
use Pimcore\Model\User;

class Installer extends AbstractInstaller
{

    /**
     * @return bool
     * @throws \Exception
     */
    public function install()
    {
        if (!GalileeImportExportBundle::getUserImporter()) {
            $importUser = new User();
            $importUser
                ->setAdmin(true)
                ->setActive(true)
                ->setParentId(0)
                ->setName(GalileeImportExportBundle::USER_NAME_IMPORTER)
                ->save();
        }
        if (!GalileeImportExportBundle::getUserImporter()) {
            $importPimUser = new User();
            $importPimUser
                ->setAdmin(true)
                ->setActive(true)
                ->setParentId(0)
                ->setName('importer-pim')
                ->save();
        }

        // Création du fichier de configuration
        $configPath = dirname(GalileeImportExportBundle::CONFIG_FILE);
        if (!is_dir($configPath)) {
            mkdir($configPath, 0755, true);
        }
        if (!file_exists(GalileeImportExportBundle::CONFIG_FILE)) {
            copy($this->getInstallDir() . 'config.xml',
                GalileeImportExportBundle::CONFIG_FILE);
        }
        if (!file_exists(GalileeImportExportBundle::CONFIG_PROCESSOR_FILE)) {
            copy($this->getInstallDir() . 'configProcessor.xml',
                GalileeImportExportBundle::CONFIG_PROCESSOR_FILE);
        }

        // Création du dossier source des fichiers d'import
        if (!is_dir(GalileeImportExportBundle::IMPORT_FILE_PATH)) {
            File::mkdir(GalileeImportExportBundle::IMPORT_FILE_PATH, 0755, true);
        }

        // Création du dossier d'export
        if (!is_dir(GalileeImportExportBundle::EXPORT_FILE_PATH)) {
            File::mkdir(GalileeImportExportBundle::EXPORT_FILE_PATH, 0755, true);
        }

        // Création du dossier des rapports d'import/export
        if (!is_dir(GalileeImportExportBundle::REPORT_FILE_PATH)) {
            File::mkdir(GalileeImportExportBundle::REPORT_FILE_PATH, 0755, true);
        }

        return true;
    }

    protected function getInstallDir()
    {
        return __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Resources'
            . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR;
    }


    /**
     * @return bool
     */
    public function isInstalled()
    {
        $isInstalled = (bool)GalileeImportExportBundle::getUserImporter()
            && file_exists(GalileeImportExportBundle::CONFIG_FILE)
            && file_exists(GalileeImportExportBundle::CONFIG_PROCESSOR_FILE);
        return $isInstalled;
    }

    public function canBeInstalled()
    {
        return true;
    }

    public function needsReloadAfterInstall()
    {
        return true;
    }


}
