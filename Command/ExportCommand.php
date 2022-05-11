<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2017 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Command;

use Galilee\ImportExportBundle\GalileeImportExportBundle;
use Galilee\ImportExportBundle\Helper\ConfigHelper;
use Galilee\ImportExportBundle\Helper\Tools;
use Galilee\ImportExportBundle\Processor\Exporter\AbstractExporter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;


class ExportCommand extends AbstractImportExportCommand
{

    public $exportDate;
    public $exportAll;
    public $currentExportDate;

    /**
     * @throws \Exception
     */
    protected function configure()
    {
        $defaultPath = $this->getExportPath();
        $exportTypes = implode(' | ', $this->configHelper->getExporterTypes());
        $this
            ->setName('galilee:export')
            ->setDescription('Export object')
            ->addOption(
                'type', 't',
                InputOption::VALUE_OPTIONAL,
                'Export type (defined in var/plugins/PluginImportExport/config/configProcessor.xml)
                ' . $exportTypes . '
                If not defined, run all export'
            )
            ->addOption(
                'export_date', 'd',
                InputOption::VALUE_OPTIONAL,
                'Export only objects modified since this date. Format YYYY-MM-DD HH:MM:SS 
            If not defined, get last import date'
            )
            ->addOption(
                'export_all', 'A',
                InputOption::VALUE_NONE,
                'Export every objects whether they have been modified or not.
                If this option is present but isn\'t supported by the chosen import type, then it will do nothing.'
            )
            ->addArgument('export_path', InputArgument::OPTIONAL,
                '[Optional] Absolute path. 
                 Default: ' . $defaultPath
            );

    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->currentExportDate = date(ConfigHelper::DATE_FORMAT);
        $types = [];
        $exporters = [];
        if (!$exportType = $input->getOption('type')) {
            // all types defined in config.xml
            $types = $this->configHelper->getExporterTypes();
        } else {
            // only the type given in argument
            $types[] = $exportType;
        }
        if (count($types)) {
            foreach ($types as $exportType) {
                $exportPath = $this->getExportPath($input->getArgument('export_path'));
                $this->exportDate = $this->getExportDate($input->getOption('export_date'), $exportType);
                $this->exportAll = $input->getOption('export_all');

                $exporter = $this->getExporter($exportType, $exportPath, $this->exportDate, $this->exportAll);
                if ($exporter) {
                    $exporters[$exportType] = $exporter;
                }
            }
        }

        if ($exporters) {
            $this->runExport($exporters);
            // On modifie la date du dernier export dans le fichier de config
            // seulement lors du lancement de tous les exports (sans l'option -t)
            if (!$input->getOption('type') && !$input->getOption('export_date')) {
                $this->configHelper->setLastExportDate($this->currentExportDate);
            }
        } else {
            $this->writeError(
                'No exporter found in var/plugins/PluginImportExport/config/configProcessor.xml for type "'
                . $exportType . '"'
            );
        }
    }

    /**
     * @param array $exporters
     * @throws \Exception
     */
    protected function runExport($exporters = [])
    {
        /** @var AbstractExporter $exporter */
        foreach ($exporters as $type => $exporter) {
            $exporter->preProcess();
            $exporter->process();
            $exporter->postProcess();
            $this->configHelper->setLastExportDate($this->currentExportDate, $type);
        }
    }


    /**
     * Get exporter
     *
     * @param $exportType
     * @param $exportPath string Root path
     * @param $exportDate
     * @param $exportAll
     * @return null
     * @throws \Exception
     */
    protected function getExporter($exportType, $exportPath, $exportDate, $exportAll = false)
    {
        $processor = $this->configHelper->getExporterByType($exportType);
        $className = '\\' . $processor->filter('class')->text();
        $exportSubFolder = Tools::pathSlash($processor->filter('export-sub-folder')->text());

        if (!class_exists($className)) {
            $this->writeError('Class ' . $className . ' doesn\'t exists');
            return null;
        }

        $processorInstance = new $className();
        if (!$processorInstance instanceof AbstractExporter) {
            $this->writeError('Class ' . $className . ' must extends AbstractExporter');
            return null;
        }

        if ($exportSubFolder) {
            $exportPath .= $exportSubFolder;
        }

        $processorInstance
            ->setExportSubFolderPath($exportSubFolder)
            ->setExportPath($exportPath)
            ->setExportDate($exportDate)
            ->setExportAll($exportAll)
            ->setOutput($this->output)
            ->setServerConfig($this->configHelper->getServer())
            ->setLogger($this->logger);
        return $processorInstance;
    }

    /**
     * Get root export destination path.
     *
     * @param string $inputArgument
     * @return string
     * @throws \Exception
     */
    protected function getExportPath($inputArgument = null)
    {
        $path = $inputArgument
            ?? $this->configHelper->getExportPath()
            ?? GalileeImportExportBundle::EXPORT_FILE_PATH;
        return Tools::pathSlash($path);
    }

    /**
     * Get export date.
     * Si l'option -d n'est pas fourni, on prend la date du fichier config.
     * website/var/plugins/PluginImportExport/config/config.xml
     *
     * @param null $optionExportDate
     * @param null $exportType
     * @return array|mixed|null
     * @throws \Exception
     */
    protected function getExportDate($optionExportDate = null, $exportType = null)
    {
        $date = $optionExportDate;
        if (!$date) {
            $date = $this->configHelper->getLastExportDate($exportType);
        }
        return $date;
    }
}