<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2017 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Processor\Exporter;

use Galilee\ImportExportBundle\Helper\ConfigHelper;
use Galilee\ImportExportBundle\Processor\AbstractProcessor;
use Pimcore\Model\DataObject;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

abstract class AbstractExporter extends AbstractProcessor
{
    const OBJECT_TYPES = [
        DataObject\AbstractObject::OBJECT_TYPE_VARIANT,
        DataObject\AbstractObject::OBJECT_TYPE_OBJECT
    ];

    const DEFAULT_EXPORT_MULTI_VALUE_SEPARATOR = '|';

    const WARN_GLOBAL_ASSET_PREVIEW_ERROR = 'WARN_GLOBAL_ASSET_PREVIEW_ERROR';

    protected $warningGlobalMessages = [
        self::WARN_GLOBAL_ASSET_PREVIEW_ERROR => 'Erreur sur génération de preview',
    ];

    protected $exportFileName;

    /** @var string */
    protected $exportCommand;

    /** @var  string Full path */
    public $exportPath;

    /** @var string sub folder */
    public $exportSubFolderPath;

    /** @var  string Format: Y-m-d H:i:s */
    public $exportDate;

    /** @var boolean $exportAll */
    public $exportAll;

    public $defaultMultiValueSeparator;

    /** @var  string */
    protected $loggerComponent = 'Export';

    /**
     * @return string
     */
    public function getDefaultMultiValueSeparator(): string
    {
        return $this->defaultMultiValueSeparator
            ? $this->defaultMultiValueSeparator
            : self::DEFAULT_EXPORT_MULTI_VALUE_SEPARATOR;
    }

    /**
     *
     * @param $path
     * @return $this
     */
    public function setExportPath($path)
    {
        $this->exportPath = $path;
        return $this;
    }

    /**
     *
     * @param $date
     * @return $this
     */
    public function setExportDate($date)
    {
        $this->exportDate = $date;
        return $this;
    }

    /**
     *
     * @param $all
     * @return $this
     */
    public function setExportAll($all)
    {
        $this->exportAll = $all;
        return $this;
    }

    public function postProcess()
    {
        $this->transferExportedFile();
    }

    public function preProcess()
    {
        $this->exportFileName = date("Y-m-d-H-i-s") . '_' . $this->exportFileName;
    }

    /**
     * @return bool
     */
    protected function transferExportedFile()
    {
        $result = true;
        if (ConfigHelper::isServerConfigValid($this->serverConfig)) {
            $source = $this->getCsvFileFullPath();
            $destination = $this->serverConfig['exportPath']
                . $this->getExportSubFolderPath()
                . $this->getExportFileName();

            // Création du dossier destination
            $cmd = sprintf("ssh -p %d %s@%s 'mkdir -p %s'",
                $this->serverConfig['port'],
                $this->serverConfig['user'],
                $this->serverConfig['host'],
                $this->serverConfig['exportPath'] . $this->getExportSubFolderPath()
            );
            $process = new Process($cmd);
            $process->run();

            $cmd = sprintf('scp -pr -P %d %s %s@%s:%s',
                $this->serverConfig['port'],
                $source,
                $this->serverConfig['user'],
                $this->serverConfig['host'],
                $destination
            );

            $process = new Process($cmd);
            $process->run();
            $result = $process->isSuccessful();

            if (!$result) {
                $this->writeError('Problème lors du transfert du fichier. ' . $process->getErrorOutput());
            } else {
                $this->writeInfo('Fichier d\'export transferé avec succès.' . $this->serverConfig['host'] . ':' . $destination);
            }
        }

        return $result;
    }

    public function getCsvFileFullPath()
    {
        return $this->exportPath . $this->exportFileName;
    }

    /**
     * @return string
     */
    public function getExportSubFolderPath(): string
    {
        return $this->exportSubFolderPath;
    }

    /**
     * @param string $exportSubFolderPath
     * @return AbstractExporter
     */
    public function setExportSubFolderPath(string $exportSubFolderPath): AbstractExporter
    {
        $this->exportSubFolderPath = $exportSubFolderPath;
        return $this;
    }


    public function getExportFileName()
    {
        return $this->exportFileName;
    }

}
