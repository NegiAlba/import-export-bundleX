<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2017 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Installer;

use Pimcore\Console\AbstractCommand;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractInstaller implements InstallerInterface
{

    /** @var  OutputInterface */
    protected $output;

    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }


}