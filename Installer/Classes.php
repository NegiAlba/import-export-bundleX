<?php
/**
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2017 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Installer;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;

class Classes extends AbstractInstaller
{
    public const INSTALL_CLASSES_PATH = InstallerInterface::INSTALL_PATH.'classes'.DIRECTORY_SEPARATOR;
    public const TEMPLATE_INSTALL_CLASSES_PATH = InstallerInterface::TEMPLATE_INSTALL_PATH.'classes-template'.DIRECTORY_SEPARATOR;

    /** @var Command Pimcore Import command definition:import:class */
    public $importClassCommand;

    public function getImportClassCommand(): Command
    {
        return $this->importClassCommand;
    }

    public function setImportClassCommand(Command $importClassCommand): Classes
    {
        $this->importClassCommand = $importClassCommand;

        return $this;
    }

    /**
     * @throws \Exception
     */
    public function install()
    {
        foreach ($this->_getClasses() as $className) {
            $this->_createClass($className);
        }
    }

    /**
     * @param $filename
     *
     * @throws \Exception
     */
    protected function _createClass($filename)
    {
        $arguments = [
            'path' => $filename,
            '--force' => true,
        ];
        $input = new ArrayInput($arguments);
        $this->importClassCommand->run($input, $this->output);
    }

    protected function _getClasses()
    {
        $classes = [];
        $classFilesGlobal = glob(self::INSTALL_CLASSES_PATH.'*.json');
        $classFilesLocal = glob(self::TEMPLATE_INSTALL_CLASSES_PATH.'*.json');
        $classFiles = array_merge($classFilesGlobal, $classFilesLocal);
        foreach ($classFiles as $filename) {
            $classes[] = $filename;
        }

        return $classes;
    }

    /**
     * Les json ne doivent pas contenir de valeur "null" au premier niveau pour Zend_Config_Json.
     *
     * @param $jsonFileName
     *
     * @return string
     */
    protected static function _cleanJson($jsonFileName)
    {
        $json = json_decode(file_get_contents($jsonFileName), true);
        foreach ($json as $key => $value) {
            if (is_null($value)) {
                unset($json[$key]);
            }
        }

        return json_encode($json);
    }
}