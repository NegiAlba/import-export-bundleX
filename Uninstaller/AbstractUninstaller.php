<?php

namespace Galilee\ImportExportBundle\Uninstaller;

use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractUninstaller
{
    /** @var  OutputInterface */
    protected $output;

    /**
     * @param OutputInterface $output
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    abstract public function uninstall();
}