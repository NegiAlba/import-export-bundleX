<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2017 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Command;

use Galilee\ImportExportBundle\GalileeImportExportBundle;
use Galilee\ImportExportBundle\Helper\Tools;
use Galilee\ImportExportBundle\Processor\AbstractProcessor;
use Galilee\ImportExportBundle\Processor\Importer\AbstractImporter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;


class ImportCommand extends AbstractImportExportCommand
{

    protected $from;

    protected $to;

    /**
     * @throws \Exception
     */
    protected function configure()
    {
        $defaultPath = $this->getImportPath();
        $availableTypes = $this->configHelper ? $this->configHelper->getImporterTypes() : [];
        $importTypes = implode(' | ', $availableTypes);
        $fileNames = '';
        foreach ($availableTypes as $type) {
            $fileNames .= '      - ' . $type . '.csv' . PHP_EOL;
        }
        $this
            ->setName('galilee:import')
            ->setDescription('Import' .
                '   Emplacement des fichiers d\'import : ' . $defaultPath . PHP_EOL .
                '   Nom des fichiers d\'imports : ' . PHP_EOL .
                $fileNames
            )
            ->addOption(
                'type', 't',
                InputOption::VALUE_OPTIONAL,
                'Import type (défini dans var/plugins/PluginImportExport/config/configProcessor.xml)
                ' . $importTypes . '
                Exécute tous les imports si non défini.'
            )
            ->addOption(
                'from', null,
                InputOption::VALUE_OPTIONAL,
                'Import splitted files from [FROM]'
            )
            ->addOption(
                'to', null,
                InputOption::VALUE_OPTIONAL,
                'Import splitted files to [TO]'
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
        $types = [];
        $importers = [];
        if (!$importType = $input->getOption('type')) {
            $types = $this->configHelper->getImporterTypes();
        } else {
            $types[] = $importType;
        }

        $this->from = $input->getOption('from');
        $this->to = $input->getOption('to');

        if (count($types)) {
            foreach ($types as $importType) {
                $importer = $this->getImporter($importType);
                if ($importer) {
                    $importers[] = $importer;
                }
            }
        }

        if ($importers) {
            $this->runImport($importers);
        } else {
            $this->writeError('No importer found in var/plugins/PluginImportExport/config/configProcessor.xml for type "' . $importType . '"');
        }
    }

    protected function runImport($importers = [])
    {

        /** @var AbstractProcessor $importer */
        foreach ($importers as $importer) {
            $result = $importer->preProcess();
            if ($result !== false) {
                $importer->process();
                $importer->postProcess();
            } else {
                $this->output->writeln('No file to import (' . $importer->getType() . ')');
            }
        }
    }

    /**
     * Get importer from var/plugins/PluginImportExport/config/config.xml
     *
     * @param $importType
     * @return AbstractImporter
     * @throws \Exception
     */
    protected function getImporter($importType)
    {
        $processor = $this->configHelper->getImporterByType($importType);
        if ($processor->count() == 0) {
            throw new \Exception('L\'import "' . $importType . '" n\'est pas défini dans var/plugins/PluginImportExport/config/configProcessor.xml');
        }
        $className = $processor->filter('class')->text();
        if (!class_exists($className)) {
            $this->writeError('Class ' . $className . ' doesn\'t exists');
            return null;
        }

        $processorInstance = new $className();
        if (!$processorInstance instanceof AbstractImporter) {
            $this->writeError('Class ' . $className . ' must extends AbstractImporter');
            return null;
        }

        $updateFieldsConfig = [
            'authorized' => $processor->filter('update')->attr('authorized'),
            'protected' => $processor->filter('update')->attr('protected'),
        ];

        /** @var Crawler $baseFileNameNode */
        $baseFileNameNode = $processor->filter('baseFileName');
        $baseFileName = $baseFileNameNode->count() == 1
            ? $baseFileNameNode->text()
            : $importType;

        /** @var Crawler $csvSeparatorNode */
        $csvSeparatorNode = $processor->filter('csvSeparator');
        $csvSeparator = $csvSeparatorNode->count() == 1
            ? $csvSeparatorNode->text()
            : AbstractProcessor::DEFAULT_CSV_SEPARATOR;


        $processorInstance
            ->setFrom($this->from)
            ->setTo($this->to)
            ->setUserModificationId(GalileeImportExportBundle::getUserImporter()->getId())
            ->setUpdateFields($updateFieldsConfig)
            ->setOutput($this->output)
            ->setLogger($this->logger)
            ->setImportBasePath($this->getImportPath($processor))
            ->setServerConfig($this->configHelper->getServer())
            ->setType($importType)
            ->setBaseFileName($baseFileName)
            ->setCsvSeparator($csvSeparator);

        return $processorInstance;
    }


    /**
     * Get import destination path.
     *
     * @param $processor
     * @return null|string
     * @throws \Exception
     */
    protected function getImportPath($processor = null)
    {
        $globalPath = Tools::pathSlash(
            $this->configHelper->getImportPath()
            ?? GalileeImportExportBundle::IMPORT_FILE_PATH
        );

        $path = null;
        if ($processor && $processor->filter('folder')->count() === 1) {
            $path = $processor->filter('folder')->text();
            if ($path && $path[0] !== '/') {
                $path = $globalPath . $path;
            }
        }
        if (!$path) {
            $path = $globalPath;
        }
        return Tools::pathSlash($path);
    }
}
