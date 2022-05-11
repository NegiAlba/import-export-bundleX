<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2017 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Installer;

use Pimcore\Console\AbstractCommand;
use Symfony\Component\Console\Output\OutputInterface;

interface InstallerInterface
{
    const INSTALL_PATH =
        PIMCORE_PROJECT_ROOT . DIRECTORY_SEPARATOR
        . 'src' . DIRECTORY_SEPARATOR
        . 'AppBundle' . DIRECTORY_SEPARATOR
        . 'Resources' . DIRECTORY_SEPARATOR
        . 'install' . DIRECTORY_SEPARATOR;

    const TEMPLATE_INSTALL_PATH =
        __DIR__ . DIRECTORY_SEPARATOR
        . '..' . DIRECTORY_SEPARATOR
        . 'Resources' . DIRECTORY_SEPARATOR
        . 'install' . DIRECTORY_SEPARATOR;

    public function install();

    public function setOutput(OutputInterface $output);


}