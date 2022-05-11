<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2017 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Installer;

use Galilee\ImportExportBundle\GalileeImportExportBundle;

class ConfigProcessor extends AbstractInstaller
{

    const INSTALL_XML_PATH = InstallerInterface::INSTALL_PATH . 'configProcessor.xml';
    const TEMPLATE_INSTALL_XML_PATH = InstallerInterface::TEMPLATE_INSTALL_PATH . 'configProcessor.xml';

    public function install()
    {
        $configProcessorFile = file_exists(self::INSTALL_XML_PATH)
            ? self::INSTALL_XML_PATH
            : self::TEMPLATE_INSTALL_XML_PATH;

        if (!file_exists(GalileeImportExportBundle::CONFIG_PROCESSOR_FILE)) {
            copy($configProcessorFile, GalileeImportExportBundle::CONFIG_PROCESSOR_FILE);
            $this->output->writeln('Copy ' . $configProcessorFile . ' to ' . GalileeImportExportBundle::CONFIG_PROCESSOR_FILE . ' successfully.');
        } else {
            $this->output->writeln(GalileeImportExportBundle::CONFIG_PROCESSOR_FILE . ' already exists.');
        }
    }

}