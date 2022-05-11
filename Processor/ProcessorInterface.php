<?php

namespace Galilee\ImportExportBundle\Processor;

interface ProcessorInterface {

    public function preProcess();

    public function process();

    public function postProcess();

}
