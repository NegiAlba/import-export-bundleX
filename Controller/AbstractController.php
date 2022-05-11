<?php

namespace Galilee\ImportExportBundle\Controller;

use Pimcore\Controller\FrontendController;
use Galilee\ImportExportBundle\Helper\ConfigHelper;

abstract class AbstractController extends FrontendController
{

    protected $configHelper;

    public function __construct(ConfigHelper $configHelper)
    {
        $this->configHelper = $configHelper;
    }
}
