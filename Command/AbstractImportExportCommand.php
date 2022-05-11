<?php

namespace Galilee\ImportExportBundle\Command;

use Pimcore\Console\AbstractCommand;
use Galilee\ImportExportBundle\Helper\ConfigHelper;
use Pimcore\Log\ApplicationLogger;

abstract class AbstractImportExportCommand extends AbstractCommand
{

    /** @var ConfigHelper  */
    protected $configHelper;

    /** @var ApplicationLogger  */
    protected $logger;

    public function __construct(ConfigHelper $configHelper, ApplicationLogger $logger)
    {
        $this->configHelper = $configHelper;
        $this->logger = $logger;
        return parent::__construct();
    }
}
