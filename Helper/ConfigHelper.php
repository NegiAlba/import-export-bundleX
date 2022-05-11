<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2017 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Helper;

use Galilee\ImportExportBundle\GalileeImportExportBundle;
use Pimcore\Log\Simple as PimcoreLog;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Serializer\Encoder\XmlEncoder;


class ConfigHelper
{
    const PROCESSOR_UPDATE_FIELDS_NODE = 'update';
    const DATE_FORMAT = 'Y-m-d H:i:s';

    protected $configCrawler;
    protected $configProcessorCrawler;

    /**
     * @return Crawler
     * @throws \Exception
     */
    public function getConfigCrawler()
    {
        if (!$this->configCrawler) {
            $this->configCrawler = $this->getCrawler(GalileeImportExportBundle::CONFIG_FILE);
        }
        return $this->configCrawler;
    }

    /**
     * @return Crawler
     * @throws \Exception
     */
    public function getConfigProcessorCrawler()
    {
        if (!$this->configProcessorCrawler) {
            $this->configProcessorCrawler = $this->getCrawler(GalileeImportExportBundle::CONFIG_PROCESSOR_FILE);
        }
        return $this->configProcessorCrawler;
    }

    /**
     * @param $xmlPath
     *
     * @return Crawler
     * @throws \Exception
     */
    protected function getCrawler($xmlPath)
    {
        return new Crawler($this->getXml($xmlPath));
    }

    /**
     * @param $xmlPath
     *
     * @return bool|string
     * @throws \Exception
     */
    protected function getXml($xmlPath)
    {
        if (!file_exists($xmlPath)) {
            return null;
        }
        return file_get_contents($xmlPath);
    }

    /**
     * @param null $section
     *
     * @return array
     * @throws \Exception
     */
    public function getConfig()
    {
        if (!file_exists(GalileeImportExportBundle::CONFIG_FILE)) {
            throw new \Exception('Configuration file does not exists:' . GalileeImportExportBundle::CONFIG_FILE);
        }
        return xmlToArray(GalileeImportExportBundle::CONFIG_FILE);
    }

    /**
     * Load var/plugins/PluginImportExport/config/configProcessor.xml
     *
     * @return array
     * @throws \Exception7
     */
    public function getConfigProcessor()
    {
        if (!file_exists(GalileeImportExportBundle::CONFIG_PROCESSOR_FILE)) {
            throw new \Exception(
                'Configuration file does not exists: '
                . GalileeImportExportBundle::CONFIG_PROCESSOR_FILE
            );
        }
        return xmlToArray(GalileeImportExportBundle::CONFIG_PROCESSOR_FILE);
    }


    /**
     * Set last import date in config.xml
     * Format Y-m-d H:i:s
     *
     * @param $date
     * @param null $type
     *
     * @return bool
     * @throws \Exception
     */
    public function setLastExportDate($date, $type = null)
    {
        $configArray = $this->getConfig();
        if ($type) {
            $configArray['lastExportDate_' . $type] = $date;
        } else {
            $configArray['lastExportDate'] = $date;
        }
        return $this->_writeConfigFromArray($configArray);
    }


    /**
     * @param $email
     *
     * @return bool
     * @throws \Exception
     */
    public function addReportRecipientEmails($email)
    {

        $emails = $this->getReportRecipientEmails();
        if (is_array($emails) && in_array($email, $emails)) {
            return false;
        }
        $configArray = $this->getConfig();
        if (!is_array($configArray['report'])) {
            $configArray['report'] = [];
        }
        if (!is_array($configArray['report']['emails'])) {
            $configArray['report']['emails'] = [];
        }
        if (!isset($configArray['report']['emails']['email'])) {
            $configArray['report']['emails']['email'] = '';
        }

        if (!is_array($configArray['report']['emails']['email'])) {
            if ($configArray['report']['emails']['email']) {
                $configArray['report']['emails']['email'] = [$configArray['report']['emails']['email']];
                $configArray['report']['emails']['email'][] = $email;
            } else {
                $configArray['report']['emails']['email'] = $email;
            }
        } else {
            $configArray['report']['emails']['email'][] = $email;
        }
        return $this->_writeConfigFromArray($configArray);
    }

    /**
     * @param $index
     *
     * @return bool
     * @throws \Exception
     */
    public function removeReportRecipientEmails($index)
    {
        $configArray = $this->getConfig();
        if (is_array($configArray['report']['emails']['email'])) {
            unset($configArray['report']['emails']['email'][$index]);
        } else {
            $configArray['report']['emails']['email'] = [];
        }
        return $this->_writeConfigFromArray($configArray);
    }


    /**
     * @param array $configArray
     *
     * @return bool
     */
    protected function _writeConfigFromArray($configArray)
    {
        $result = true;
        try {
            $encoder = new XmlEncoder();
            $encoded = $encoder->encode($configArray, "xml");
            if (file_exists(GalileeImportExportBundle::CONFIG_FILE)) {
                file_put_contents(GalileeImportExportBundle::CONFIG_FILE, $encoded);
            }

        } catch (\Exception $e) {
            PimcoreLog::log('pluginImportExport', $e->getMessage());
            $result = false;
        }
        return $result;
    }



    //


    /**
     * Load var/plugins/PluginImportExport/config/config.xml
     *
     * @return Crawler
     */
    public function getCrawlerConfig()
    {
        $configProcessor = file_get_contents(GalileeImportExportBundle::CONFIG_FILE);
        return new Crawler($configProcessor);
    }

    /**
     * Load var/plugins/PluginImportExport/config/configProcessor.xml
     *
     * @return Crawler
     */
    public function getCrawlerConfigProcessor()
    {
        $configProcessor = file_get_contents(GalileeImportExportBundle::CONFIG_PROCESSOR_FILE);
        return new Crawler($configProcessor);
    }

    /**
     *
     * @return array
     * @throws \Exception
     */
    public function getExporterTypes()
    {
        $path = 'exporters exporter type';
        return $this->getConfigProcessorCrawler()->filter($path)
            ? $this->getConfigProcessorCrawler()->filter($path)->extract(array('_text'))
            : [];
    }

    /**
     *
     * @return array
     * @throws \Exception
     */
    public function getImporterTypes()
    {
        $path = 'importers importer type';
        return $this->getConfigProcessorCrawler()->filter($path)
            ? $this->getConfigProcessorCrawler()->filter($path)->extract(array('_text'))
            : [];
    }

    /**
     * @param string $type
     *
     * @return Crawler
     */
    public function getExporterByType($type)
    {
        $crawler = $this->getCrawlerConfigProcessor();
        return $crawler
            ->filter('exporters exporter')
            ->reduce(function (Crawler $node) use ($type) {
                $nodeType = $node->filter('type');
                $nodeTypeText = $nodeType && $nodeType->count() ? $nodeType->text() : '';
                return $type == $nodeTypeText;
            });
    }

    /**
     *
     * @param $type
     *
     * @return Crawler
     */
    public function getImporterByType($type)
    {
        $crawler = $this->getCrawlerConfigProcessor();
        return $crawler
            ->filter('importers importer')
            ->reduce(function (Crawler $node) use ($type) {
                return $type == $node->filter('type')->text();
            });
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getExportPath()
    {
        $path = $this->getConfigCrawler()->filter('exportFolder');
        return $path && $path->count()
            ? $path->text()
            : null;
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getImportPath()
    {

        $path = $this->getConfigCrawler()->filter('importFolder');
        return $path && $path->count()
            ? $path->text()
            : null;
    }


    /**
     * @param null $type
     *
     * @return array|mixed
     * @throws \Exception
     */
    public function getLastExportDate($type = null)
    {
        $result = null;
        $node = null;
        $nodeName = 'lastExportDate';
        $typeNodeName = $nodeName . '_' . $type;
        if ($type) {
            $node = $this->getConfigCrawler()->filter($typeNodeName);
        }
        if (!$node) {
            $node = $this->getConfigCrawler()->filter($nodeName);
        }
        if ($node && $node->count()) {
            $result = $node->text();
        }
        return $result ? $result : date(self::DATE_FORMAT, 0);
    }


    /**
     * @return array
     * @throws \Exception
     */
    public function getReportRecipientEmails()
    {
        $result = [];
        $node = $this->getConfigCrawler()->filter('report emails email');
        if ($node) {
            $emails = $node->extract(array('_text'));
            $result = (count($emails) == 1 && $emails[0] == '') ? [] : $emails;
        }
        return $result;
    }


    /**
     * @return null|array
     * @throws \Exception
     */
    public function getServer()
    {
        $nodeNames = ['host', 'user', 'port', 'exportPath', 'importPath'];
        $server = [];
        foreach ($nodeNames as $nodeName) {
            $node = $this->getConfigCrawler()->filter('server ' . $nodeName);
            if ($node && $node->count()) {
                $value = $node->text();
                if ($nodeName == 'exportPath' || $nodeName == 'importPath') {
                    $value = Tools::pathSlash($node->text());
                }
                $server[$nodeName] = $value;
            }
        }
        return $server;
    }

    public static function isServerConfigValid($serverConfig)
    {
        return is_array($serverConfig)
            && isset($serverConfig['port']) && $serverConfig['port'] != ''
            && isset($serverConfig['user']) && $serverConfig['user'] != ''
            && isset($serverConfig['host']) && $serverConfig['host'] != ''
            && isset($serverConfig['exportPath']) && $serverConfig['exportPath'] != ''
            && isset($serverConfig['importPath']) && $serverConfig['importPath'] != '';
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getAttributesToClean()
    {
        $path = $this->getConfigCrawler()->filter('attributesToClean');
        return $path && $path->children()->count()
            ? array_map(function (\Symfony\Component\DomCrawler\Crawler $node) {
                return $node->text();
            }, $path->children()->each(function (\Symfony\Component\DomCrawler\Crawler $node) {
                return $node;
            }))
            : [];
    }

}
