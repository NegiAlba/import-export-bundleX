<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2017 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Processor;

use Pimcore\Log\ApplicationLogger;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\Version;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\ConsoleOutput;

abstract class AbstractProcessor implements ProcessorInterface
{

    const DEFAULT_CSV_SEPARATOR = ';';

    /** @var string */
    protected $csvSeparator;

    /** @var  ConsoleOutput */
    protected $output;

    /** @var ApplicationLogger */
    protected $logger;

    /** @var  string */
    protected $loggerComponent;

    /** @var array */
    protected $serverConfig;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var string
     */
    protected $baseFileName;

    protected $warningGlobalMessages = [];
    protected $errorGlobalMessages = [];
    protected $infoGlobalMessages = [];
    protected $warningMessages = [];
    protected $errorMessages = [];
    protected $infoMessages = [];


    public function __construct()
    {
        Version::disable();
        AbstractObject::setHideUnpublished(false);
    }


    /**
     * get csv separator.
     *
     * @return string
     */
    public function getCsvSeparator()
    {
        return $this->csvSeparator;
    }

    /**
     * @param $csvSeparator
     * @return $this
     */
    public function setCsvSeparator($csvSeparator)
    {
        $this->csvSeparator = $csvSeparator;
        return $this;
    }

    /**
     * @return array
     */
    public function getServerConfig(): array
    {
        return $this->serverConfig;
    }

    /**
     * @param array $serverConfig
     * @return AbstractProcessor
     */
    public function setServerConfig(array $serverConfig): AbstractProcessor
    {
        $this->serverConfig = $serverConfig;
        return $this;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     * @return AbstractProcessor
     */
    public function setType(string $type): AbstractProcessor
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return string
     */
    public function getBaseFileName(): string
    {
        return $this->baseFileName;
    }

    /**
     * @param string $baseFileName
     * @return AbstractProcessor
     */
    public function setBaseFileName(string $baseFileName): AbstractProcessor
    {
        $this->baseFileName = $baseFileName;
        return $this;
    }


    /**
     * Get ApplicationLogger instance.
     * @param ApplicationLogger $logger
     * @return AbstractProcessor
     */
    public function setLogger(ApplicationLogger $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * @param ConsoleOutput $output
     * @return $this
     */
    public function setOutput(ConsoleOutput $output)
    {
        $this->output = $output;
        $warningStyle = new OutputFormatterStyle('magenta');
        $this->output->getFormatter()->setStyle('warning', $warningStyle);
        return $this;
    }

    /**
     * Logger methods
     */

    /**
     * @param string $message
     */
    protected function vMessage($message)
    {
        if ($this->output->isVerbose()) {
            $this->output->writeln($message);
        }
    }

    /**
     * @param string $message
     */
    protected function vvMessage($message)
    {
        if ($this->output->isVeryVerbose()) {
            $this->output->writeln($message);
        }
    }

    /**
     * @param string $message
     */
    protected function vvvMessage($message)
    {
        if ($this->output->isDebug()) {
            $this->output->writeln($message);
        }
    }

    /**
     * @param string $message
     * @param array $options
     */
    protected function writeDebug($message, $options = [])
    {
        if ($this->output->isDebug()) {
            $options = $this->addLoggerComponentOption($options);
            $this->output->writeln(sprintf('<info>[DEBUG] %s</info>', $message));
            $this->logger->debug($message, $options);
        }
    }

    /**
     * @param string $message
     * @param array $options
     */
    protected function writeInfo($message, $options = [])
    {
        $options = $this->addLoggerComponentOption($options);
        $this->output->writeln(sprintf('<info>%s</info>', $message));
        $this->logger->info($message, $options);
    }

    /**
     * @param string $message
     * @param array $options
     */
    protected function writeWarning($message, $options = [])
    {
        $options = $this->addLoggerComponentOption($options);
        $this->output->writeln(sprintf('<warning>%s</warning>', $message));
        $this->logger->warning($message, $options);
    }


    /**
     * @param string $message
     * @param array $options
     */
    protected function writeError($message, $options = [])
    {
        $options = $this->addLoggerComponentOption($options);
        $this->output->writeln(sprintf('<error>ERROR: %s</error>', $message));
        $this->logger->error($message, $options);
    }

    protected function addLoggerComponentOption($options)
    {
        if (!isset($options['component'])) {
            $options['component'] = $this->loggerComponent;
        }
        return $options;
    }


    protected function logInfo($key, $info = '', $line = null)
    {
        $this->writeInfo($this->getLogMessage($key, $info, $line));
    }

    protected function logWarning($key, $info = '', $line = null)
    {
        $this->writeWarning($this->getLogMessage($key, $info, $line));
    }

    protected function logError($key, $info = '', $line = null)
    {
        $this->writeError($this->getLogMessage($key, $info, $line));
    }

    protected function getLogMessage($key, $info = '', $line = null)
    {
        $messages = array_merge(
            $this->errorGlobalMessages,
            $this->warningGlobalMessages,
            $this->infoGlobalMessages,
            $this->errorMessages,
            $this->warningMessages,
            $this->infoMessages
        );
        $parts = isset($messages[$key]) ? $messages[$key] : $key;
        if ($info) {
            $parts .= ' - ' . $info;
        }
        if ($line) {
            $parts.= ' [#' . $line . ']';
        }
        return $parts;
    }

}
