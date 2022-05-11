<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2016 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Processor\Importer;

use Galilee\ImportExportBundle\Exception\InvalidCsvException;
use Galilee\ImportExportBundle\Helper\AssetHelper;
use Galilee\ImportExportBundle\Helper\ConfigHelper;
use Galilee\ImportExportBundle\Helper\CsvReader;
use Galilee\ImportExportBundle\Helper\FileHelper;
use Galilee\ImportExportBundle\Helper\ReportEmail;
use Galilee\ImportExportBundle\Processor\AbstractProcessor;
use Pimcore;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\Data\ObjectMetadata;
use Symfony\Component\Process\Process;


abstract class AbstractImporter extends AbstractProcessor implements ImporterInterface
{

    const PROCESSING_SUB_FOLDER = 'processing';
    const PROCESSED_FOLDER_NAME = 'processed';
    const FAILED_FOLDER_NAME = 'error';

    const UPDATE_MODE = 'update';
    const CREATE_MODE = 'create';
    /**
     * Update fields configuration
     */
    const ALL = '__all__';
    const AUTHORIZED_KEY = 'authorized';
    const PROTECTED_KEY = 'protected';
    const IMPORT_ASSET_ROOT_FOLDER = 'import';

    const LOG_UPDATED = '[MISE A JOUR]';
    const LOG_CREATED = '[CREATION]';
    const LOG_UNCHANGED = '[NON MODIFIE]';
    const LOG_START = '[DEBUT]';
    const LOG_END = '[FIN]';
    const LOG_ERROR = '[ERREUR]'; // Erreurs bloquant l'import

    const ERR_GLOBAL_SAVE_OBJECT = 'ERR_GLOBAL_SAVE_OBJECT';
    const ERR_GLOBAL_ZIP_ASSETS_INVALID = 'ERR_GLOBAL_ZIP_ASSETS_INVALID';
    const WARN_ROW_ITEM_ASSET_ORDER_CHANGED = 'No asset deleted or added but changed asset order for rowItem.';

    protected $errorGlobalMessages = [
        self::ERR_GLOBAL_SAVE_OBJECT => 'Erreur d\'enregistrement',
        self::ERR_GLOBAL_ZIP_ASSETS_INVALID => 'Fichier d\'asset zip non valide',
    ];

    const WARN_GLOBAL_ASSET_NOT_FOUND = 'WARN_GLOBAL_ASSET_NOT_FOUND';
    const WARN_GLOBAL_FIELD_NOT_EXISTS = 'WARN_GLOBAL_FIELD_NOT_EXISTS'; // wrong mapping!
    const WARN_GLOBAL_MANDATORY_FIELD = 'WARN_GLOBAL_MANDATORY_FIELD';
    const WARN_GLOBAL_OBJECT_NOT_FOUND = 'WARN_GLOBAL_OBJECT_NOT_FOUND';
    const WARN_PRODUCT_ASSET_COULD_NOT_BE_DELETED = 'WARN_PRODUCT_ASSET_COULD_NOT_BE_DELETED';
    const WARN_NO_REPLACEMENT_ASSET_ALREADY_EXIST = 'WARN_NO_REPLACEMENT_ASSET_ALREADY_EXIST';
    const WARN_NO_REPLACEMENT_ASSET_ALREADY_EXIST_ON_ANOTHER_OBJECT = 'WARN_NO_REPLACEMENT_ASSET_ALREADY_EXIST_ON_ANOTHER_OBJECT';
    const WARN_NO_ADDITION_ASSET_ALREADY_EXIST = 'WARN_NO_ADDITION_ASSET_ALREADY_EXIST';
    const WARN_NO_ADDITION_ASSET_ALREADY_EXIST_ON_ANOTHER_OBJECT = 'WARN_NO_ADDITION_ASSET_ALREADY_EXIST_ON_ANOTHER_OBJECT';


    protected $warningGlobalMessages = [
        self::WARN_GLOBAL_ASSET_NOT_FOUND => 'Asset non trouvé',
        self::WARN_GLOBAL_FIELD_NOT_EXISTS => 'Champ non trouvé',
        self::WARN_GLOBAL_MANDATORY_FIELD => 'Champ obligatoire manquant',
        self::WARN_GLOBAL_OBJECT_NOT_FOUND => 'Objet non trouvé',
        self::WARN_NO_REPLACEMENT_ASSET_ALREADY_EXIST => 'Un asset n\'a pas été remplacé durant l\'import car est déjà present et lié à l\'objet concerné',
        self::WARN_NO_REPLACEMENT_ASSET_ALREADY_EXIST_ON_ANOTHER_OBJECT => 'Un asset n\'a pas été remplacé durant l\'import car est déjà present et lié à un autre objet',
        self::WARN_NO_ADDITION_ASSET_ALREADY_EXIST => 'Un asset n\'a pas été ajouté à la collection durant l\'import car est déjà present et lié à l\'objet concerné',
        self::WARN_NO_ADDITION_ASSET_ALREADY_EXIST_ON_ANOTHER_OBJECT => 'Un asset n\'a pas été ajouté à la collection durant l\'import car est déjà present et lié à un autre objet',
        self::WARN_PRODUCT_ASSET_COULD_NOT_BE_DELETED => 'Un asset n\'a pas pu être supprimé',
    ];

    /** @var int */
    protected $from;

    /** @var int */
    protected $to;

    protected $updateFields = [];

    /**
     * @var update or create mode
     */
    protected $mode = self::CREATE_MODE;

    protected $isUpdated = false;

    /** @var Object */
    protected $inProcessObject;

    /** @var array */
    protected $csvRow = [];

    /** @var  string csv source full path (path + filename) */
    protected $csvPath;

    /** @var  string assets source folder */
    protected $assetsPath;

    /** @var  integer UserModification object */
    protected $userId;

    /** @var  string Format : Y-m-d H:i:s */
    protected $startedAt;

    /** @var  string Format : Y-m-d H:i:s */
    protected $finishedAt;

    /** @var  array Mandatory field in csv header */
    public $mandatoryFields = array();

    /** @var bool */
    public $error = false;

    /** @var string */
    public $initObjectMessage = '';

    /** @var string */
    public $importBasePath;

    public $sendReport = true;

    public $moveFileAfterImport = true;

    /** @var  string */
    protected $loggerComponent = 'Import';


    /**
     * Mapping between csv columns and object fields.
     * Defined in concrete importer.
     * Format:
     * [{csv column} => {object field}]
     * or
     * [{csv column} => array({object field}, {method}, array {arguments})]
     *
     * @var array
     */
    public $mapping;

    /**
     * Csv data content.
     */
    protected $csvData = [];

    /** @var \ZipArchive */
    protected $zipAsset;

    /**
     * @var array|false|null
     */
    protected $csvHeader;

    /**
     * @var int
     */
    protected $line;

    /**
     * @return int
     */
    public function getFrom(): ?int
    {
        return $this->from;
    }

    /**
     * @param int $from
     *
     * @return AbstractImporter
     */
    public function setFrom(?int $from): AbstractImporter
    {
        $this->from = $from;
        return $this;
    }

    /**
     * @return int
     */
    public function getTo(): ?int
    {
        return $this->to;
    }

    /**
     * @param int $to
     *
     * @return AbstractImporter
     */
    public function setTo(?int $to): AbstractImporter
    {
        $this->to = $to;
        return $this;
    }


    public function setUserModificationId($userId)
    {
        $this->userId = $userId;
        return $this;
    }

    /**
     * @return string
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * @param string update $mode
     *
     * @return AbstractImporter
     */
    public function setMode($mode)
    {
        $this->mode = $mode;
        return $this;
    }

    /**
     * @return bool
     */
    public function isUpdated()
    {
        return $this->isUpdated;
    }

    /**
     * @param bool $isUpdated
     *
     * @return AbstractImporter
     */
    public function setIsUpdated($isUpdated)
    {
        $this->isUpdated = $isUpdated;
        return $this;
    }

    public function preProcess()
    {
        $this->startedAt = date(ConfigHelper::DATE_FORMAT);

        $wrongFiles = FileHelper::getWrongTimeStampedFiles($this->importBasePath, $this->getBaseFileName() . '.csv');
        foreach ($wrongFiles as $file) {
            $this->moveFile($file, false);
            $this->logError(self::LOG_ERROR, 'Nom de fichier invalide : ' . basename($file));
        }

        $localCsv = FileHelper::getCurrentTimeStampedFile($this->importBasePath, $this->getBaseFileName() . '.csv');
        $localAssetsZip = FileHelper::getCurrentTimeStampedZipAssetFile($this->getBaseFileName(), $localCsv);
        $localAssetsFolder = $this->importBasePath . 'assets';

        if (ConfigHelper::isServerConfigValid($this->serverConfig)) {
            $baseRemotePath = $this->serverConfig['importPath'];
            if (substr($baseRemotePath, -1, 1) !== DIRECTORY_SEPARATOR) {
                $baseRemotePath .= DIRECTORY_SEPARATOR;
            }
            $remoteCsv = $baseRemotePath . $this->type . '.csv';
            $remoteAssetsZip = $baseRemotePath . $this->getType() . '.zip';
            $remoteAssetsFolder = $baseRemotePath . 'assets';

            $resultCsv = $this->downloadRemoteFile($remoteCsv, $localCsv);
            if ($resultCsv) {
                $this->writeInfo('Fichier d\'import transferé avec succès : ' . $localCsv);

                // Assets ZIP
                $resultZip = $this->downloadRemoteFile($remoteAssetsZip, $localAssetsZip);
                if ($resultZip) {
                    $this->writeInfo('Fichier d\'asset zip transferé avec succès : ' . $localAssetsZip);
                }

                // Assets folder
                $resultAssets = $this->rsyncRemoteFiles($remoteAssetsFolder, $this->importBasePath);
                if ($resultAssets) {
                    $this->writeInfo('Dossier assets transferé avec succès : ' . $localAssetsFolder);
                }
            }
        }

        if (file_exists($localAssetsZip)) {
            $this->setAssetsPath($localAssetsZip);
            $zipAsset = new \ZipArchive();
            if ($zipAsset->open($localAssetsZip) === true) {
                $this->zipAsset = $zipAsset;
            } else {
                $this->logError(self::ERR_GLOBAL_ZIP_ASSETS_INVALID, $localAssetsZip);
            }
        } else {
            $this->setAssetsPath($localAssetsFolder . DIRECTORY_SEPARATOR);
        }
        if ($localCsv) {
            $this->setCsvPath($localCsv);
        } else {
            return false;
        }
    }

    protected function rsyncRemoteFiles($source, $destination)
    {
        $result = false;
        if (ConfigHelper::isServerConfigValid($this->serverConfig)) {
            $shell = "rsync --ignore-existing -raz --progress -e 'ssh -p %d' %s@%s:%s %s";
            $cmd = sprintf($shell,
                $this->serverConfig['port'],
                $this->serverConfig['user'],
                $this->serverConfig['host'],
                $source,
                $destination
            );

            $process = new Process($cmd);
            $process->setTimeout(3600);
            $process->setIdleTimeout(3600);
            $process->run(function ($type, $buffer) {
                if (Process::ERR === $type) {
                    $this->output->writeln('Rsync ERR > ' . $buffer);
                } else {
                    $this->output->writeln('Rsync file > ' . $buffer);
                }
            });

            $result = $process->isSuccessful();
        }
        return $result;

    }

    protected function downloadRemoteFile($source, $destination, $option = '')
    {
        $result = false;
        if (ConfigHelper::isServerConfigValid($this->serverConfig)) {
            $cmd = sprintf('scp %s -P %d %s@%s:%s %s',
                $option,
                $this->serverConfig['port'],
                $this->serverConfig['user'],
                $this->serverConfig['host'],
                $source,
                $destination
            );

            $process = new Process($cmd);
            $process->setTimeout(3600);
            $process->setIdleTimeout(3600);
            $process->run();
            $result = $process->isSuccessful();
        }
        return $result;
    }

    /**
     * Start import.
     */
    public function process()
    {
        try {
            $csvReader = $this->getCsvReader();
            if ($csvReader) {
                $count = $csvReader->getCount();
                $generator = $csvReader->getGenerator();
            } else {
                // No file to import
                return false;
            }
        } catch (\Exception $e) {
            $this->error = true;
            return false;
        }

        if ($this->isChunkImport()) {
            $this->moveFileAfterImport = false;
            $this->sendReport = false;
        } else {
            $this->logInfo(self::LOG_START, $this->loggerComponent . '. ' . date(ConfigHelper::DATE_FORMAT, strtotime($this->startedAt)));
        }

        $line = 1;
        $processingCount = 0;
        $to = !is_null($this->getTo()) ? (int)$this->getTo() : PHP_INT_MAX;
        $from = !is_null($this->getFrom()) ? (int)$this->getFrom() : 1;

        foreach ($generator as $row) {
            if ($from <= $line && $to >= $line) {
                $this->csvRow = $row;
                if ($this->processRow($row, $line)) {
                    $processingCount++;
                }
                if ($processingCount % 1000 === 0) {
                    Pimcore::collectGarbage();
                }
            }
            $line++;
            $this->inProcessObject = null;
        }

        if (!$this->isChunkImport()) {
            $this->logInfo(self::LOG_END, 'Nombre d\'éléments traités : ' . $processingCount . '/' . $count);
        }
    }

    protected function isChunkImport()
    {
        return !(is_null($this->getTo()) && is_null($this->getFrom()));
    }

    public function postProcess()
    {
        $this->finishedAt = date(ConfigHelper::DATE_FORMAT);
        if ($this->sendReport) {
            $html = $this->reportLog();
            ReportEmail::sendResumeHtml($html, $this->loggerComponent, $this->startedAt);
        }
        if ($this->moveFileAfterImport) {
            $this->moveCsvAfterImport();
        }
    }

    protected function reportLogHeader()
    {
        $header = '<p><ul>';
        $header .= '<li>Fichier importé : ' . $this->csvPath . '</li>';
        $header .= '<li>Début de l\'import : ' . $this->startedAt . '</li>';
        $header .= '<li>Fin de l\'import : ' . $this->finishedAt . '</li>';
        $header .= '</p></ul>';
        return $header;
    }

    protected function reportLogResume()
    {
        $updated = ReportEmail::getLogs($this->loggerComponent, $this->startedAt, self::LOG_UPDATED);
        $created = ReportEmail::getLogs($this->loggerComponent, $this->startedAt, self::LOG_CREATED);
        $unchanged = ReportEmail::getLogs($this->loggerComponent, $this->startedAt, self::LOG_UNCHANGED);
        $resumeCount[] = 'Création : ' . count($created);
        $resumeCount[] = 'Mise à jour : ' . count($updated);
        $resumeCount[] = 'Non modifié : ' . count($unchanged);
        $html = '<p>';
        $html .= '<h3>Résumé</h3>';
        $html .= '<ul><li>';
        $html .= implode('</li><li>', $resumeCount);
        $html .= '</li></ul>';
        $html .= '</p>';
        return $html;
    }

    protected function reportLogCount($messages, $title)
    {
        $logCount = [];
        foreach ($messages as $key => $msg) {
            $log = ReportEmail::getLogs($this->loggerComponent, $this->startedAt, $msg);
            if (count($log)) {
                $logCount[] = $msg . ' : ' . count($log);
            }
        }
        $html = '';
        if (count($logCount)) {
            $html .= '<p>';
            $html .= '<h3>' . $title . '</h3>';
            $html .= '<ul><li>';
            $html .= implode('</li><li>', $logCount);
            $html .= '</li></ul>';
            $html .= '</p>';
        }
        return $html;

    }

    protected function reportLog()
    {
        $html = '<h1>' . $this->loggerComponent . '</h1>';
        $html .= $this->reportLogHeader();
        $errors = ReportEmail::getLogs($this->loggerComponent, $this->startedAt, self::LOG_ERROR);
        if (count($errors)) {
            $html .= '<p><ul>';
            foreach ($errors as $error) {
                $html .= '<li>' . $error['message'] . '</li>';
            }
            $html .= '</p></ul>';
        } else {

            $html .= $this->reportLogResume();
            $errorMessages = array_merge($this->errorGlobalMessages, $this->errorMessages);
            $html .= $this->reportLogCount($errorMessages, 'Erreurs');
            $warningMessages = array_merge($this->warningGlobalMessages, $this->warningMessages);
            $html .= $this->reportLogCount($warningMessages, 'Avertissements');
            $infoMessages = array_merge($this->infoGlobalMessages, $this->infoMessages);
            $html .= $this->reportLogCount($infoMessages, 'Infos');
        }
        return $html;
    }

    /**
     * Process import object.
     *
     * @param array $row
     * @param integer $line
     *
     * @return bool
     * @throws \Exception
     */
    protected function processRow($row, $line)
    {
        $this->line = $line;
        $this->setMode(self::CREATE_MODE);
        $this->setIsUpdated(false);

        if (!$this->checkMandatoryValue($row, $line)) {
            return false;
        }

        if (!$this->initObject($row, $line)) {
            return false;
        }
        if ($this->inProcessObject->getUserModification() == 0) {
            $this->inProcessObject->setUserModification($this->userId);
        }

        foreach ($this->mapping as $csvField => $target) {

            if (is_null($target)) {
                continue;
            }

            if (!$this->canProcessField($csvField, $row)) {
                continue;
            }

            if (!is_array($target)) {
                $tmp[0] = $target;
                $tmp[1] = 'field';
                $target = $tmp;
            }
            $value = isset($row[$csvField]) ? trim($row[$csvField]) : '';
            $objectField = $target[0];
            $methodToCall = $target[1];
            $extraParams = isset($target[2]) ? $target[2] : [];
            if ($csvField == '*') {
                $extraParams[] = $this->getUnMappedField();
            }
            $params = [$objectField, $value];
            $params = array_merge($params, $extraParams);

            if (method_exists($this, $methodToCall)) {

                try {
                    $changed = call_user_func_array(array($this, $methodToCall), $params);
                    if ($changed) {
                        $this->vMessage('  - ' . $objectField . ': ' . $value);
                        $this->setIsUpdated(true);
                    }
                } catch (\Exception $e) {
                    // Application error
                    $this->writeError('Ligne ' . $line . ' : ' . $e->getMessage() . ' - méthode : ' . $methodToCall . ' - Champ : ' . $objectField . ' - Valeur : ' . $value);
                    return false;
                }
            } else {
                // Application error
                $this->writeError($methodToCall . ' is not implemented. Check $mapping in ' . get_class($this));
                exit;
            }
        }

        try {
            if (($this->getMode() == self::UPDATE_MODE && $this->isUpdated())
                || $this->getMode() == self::CREATE_MODE
            ) {

                $this->inProcessObject
                    ->setPublished(true)
                    ->save();

                //TODO clean this one day, maybe
                /* Why -> We want to export the parent product when variants are modified, in case the
                 * position of the variant within his parent changes. So we check if the object is a variant
                 * then we get his parent. We then "fake"-update the parent and we save it, to ensure that it will be
                 * flagged for export.
                 *
                 * Maybe this can be improved in the export, with a better fetching of the products to export.
                 * However I didn't have the time to work on it myself.
                 */
                if ($this->inProcessObject->getType(DataObject\AbstractObject::OBJECT_TYPE_VARIANT)) {
                    $parent = $this->inProcessObject->getParent();
                    if ($parent && !$parent instanceof DataObject\Folder) {
                        $parent->setUserModification($this->userId)
                            ->setPublished(true)
                            ->save();
                    }
                }

                if ($this->mode == self::CREATE_MODE) {
                    $this->logInfo(self::LOG_CREATED, $this->initObjectMessage, $line);
                } else {
                    $this->logInfo(self::LOG_UPDATED, $this->initObjectMessage, $line);
                }

            } else {
                $this->logInfo(self::LOG_UNCHANGED, $this->initObjectMessage, $line);
            }

        } catch (\Exception $e) {
            $this->logError(self::ERR_GLOBAL_SAVE_OBJECT, $e->getMessage(), $this->line);
            return false;
        }

        $result = $this->postProcessRow();

        return $result;
    }

    protected function postProcessRow()
    {
        return true;
    }

    protected function getUnMappedField()
    {
        return array_diff($this->csvHeader, array_keys($this->mapping));
    }

    protected function checkMandatoryValue($row, $line = '')
    {
        $result = true;
        foreach ($this->mapping as $csvField => $target) {
            if (isset($row[$csvField])) {
                $value = trim($row[$csvField]);
                if ($value == '' && in_array($csvField, $this->mandatoryFields)) {
                    $this->logWarning(self::WARN_GLOBAL_MANDATORY_FIELD, $csvField, $this->line);
                    $result = false;
                }
            }
        }
        return $result;
    }

    /**
     * csv file has header row ?
     * @return bool
     */
    public function parseCsvHeader()
    {
        return true;
    }

    /**
     * Set basic field type (text, texarea, number,...)
     *
     * @param $objectFieldName
     * @param $value
     *
     * @return bool
     * @throws \Exception
     */
    public function field($objectFieldName, $value)
    {
        $getter = sprintf('get%s', ucfirst($objectFieldName));
        $setter = sprintf('set%s', ucfirst($objectFieldName));

        if (!method_exists($this->inProcessObject, $setter)) {
            return false;
        }
        $changed = $this->inProcessObject->$getter() != $value;
        $this->inProcessObject->$setter($value);
        return $changed;
    }


    /**
     * @param $objectFieldName
     * @param $value
     *
     * @return bool
     * @throws \Exception
     */
    public function setFloatVal($objectFieldName, $value)
    {
        $float = $this->getFloatVal($value);
        if ($float === false) {
            throw new \Exception($objectFieldName . ' : ' . $value . ' n\'est pas un nombre à virgule valide.');
        }
        return $this->field($objectFieldName, $float);
    }

    public function setHtmlVal($objectFieldName, $value)
    {
        return $this->field($objectFieldName, stripslashes($value));
    }

    /**
     * Field type image.
     *
     * @param $objectFieldName
     * @param $fileName
     *
     * @return bool
     * @throws \Exception
     */
    public function image($objectFieldName, $fileName)
    {
        $changed = false;
        $setter = sprintf('set%s', ucfirst($objectFieldName));
        $getter = sprintf('get%s', ucfirst($objectFieldName));
        $oldFileName = '';
        $asset = null;
        if (!method_exists($this->inProcessObject, $setter)) {
            $this->logWarning(self::WARN_GLOBAL_FIELD_NOT_EXISTS, $objectFieldName . ' (image)', $this->line);
            return false;
        }

        if ($oldAsset = $this->inProcessObject->$getter()) {
            $oldFileName = $oldAsset->getFilename();
        }

        try {
            $asset = $this->createAsset($fileName, self::IMPORT_ASSET_ROOT_FOLDER . DIRECTORY_SEPARATOR . $this->type);
        } catch (\Exception $e) {
            $this->logWarning(self::WARN_GLOBAL_ASSET_NOT_FOUND, $fileName, $this->line);
        }
        if ($asset['asset'] && $oldFileName != $asset['asset']->getFilename()) {
            $this->inProcessObject->$setter($asset['asset']);
            $changed = true;
        }
        return $changed;
    }

    /**
     * @param $assetFileName
     * @param $folderPath
     * @param bool $isReset
     *
     * @return array
     * @throws \Exception
     */
    protected function createAsset($assetFileName, $folderPath, $isReset = false)
    {
        $assetCreationResult = [];
        $parent = Asset\Service::createFolderByPath($folderPath);

        if ($this->zipAsset) {
            $assetCreationResult = AssetHelper::createAssetFromZip($this->zipAsset, $assetFileName, $parent, $this->userId, $isReset);
        } else {
            $assetCreationResult = AssetHelper::createAssetFromFile($this->assetsPath, $assetFileName, $parent, $this->userId, $isReset, $this->inProcessObject->getId());
        }
        return $assetCreationResult;
    }

    /**
     * Field type multiHref. Link to Asset.
     *
     * @param $objectFieldName
     * @param $csvValue
     * @param string $separator
     *
     * @return bool
     */
    public function multiHrefAsset($objectFieldName, $csvValue, $separator = ',')
    {
        $changed = false;
        $assets = array();
        $isReset = $this->isMultiHrefAssetInReset($objectFieldName);


        $values = array_filter(array_map('trim', explode($separator, $csvValue)));

        $setter = sprintf('set%s', ucfirst($objectFieldName));
        $getter = sprintf('get%s', ucfirst($objectFieldName));
        $oldKeyList = [];
        $oldAssetCollection = [];
        $newAssetCollection = [];
        $newKeyList = [];

        if (!method_exists($this->inProcessObject, $setter)) {
            $this->logWarning(self::WARN_GLOBAL_FIELD_NOT_EXISTS, $objectFieldName . ' (multiHrefAsset)', $this->line);
            return false;
        }

        if ($oldValues = $this->inProcessObject->$getter()) {

            /** @var Asset $oldAsset */
            foreach ($oldValues as $oldAsset) {
                $assets[] = $oldAsset;
                $oldKeyList[] = $oldAsset->getFilename();
                $oldAssetCollection[$oldAsset->getFilename()] = $oldAsset;
            }
        }

        foreach ($values as $fileName) {

            try {

                $assetCreationResult = $this->createAsset(
                    $fileName,
                    self::IMPORT_ASSET_ROOT_FOLDER . DIRECTORY_SEPARATOR . $this->type,
                    $isReset
                );

                // should not be possible, but just in case
                if (empty($assetCreationResult)) {
                    continue;
                }

                /** @var null|Asset|Asset\Archive|Asset\Audio|Asset\Document|Folder|Asset\Image|Asset\Text|Asset\Unknown|Asset\Video $asset */
                $asset = $assetCreationResult['asset'];
                $assets[] = $asset;
                $newKeyList[] = $asset->getFilename();

                if ($isReset) {

                    $newAssetCollection[] = $asset;

                    // if new file had name already in use for this product and was the same checksum
                    // we have already replaced or ignored it, no need to delete it
                    if ($assetCreationResult['isResetDone'] || $assetCreationResult['isResetAborted']) {
                        if (isset($oldAssetCollection[$asset->getFilename()])) {
                            unset($oldAssetCollection[$asset->getFilename()]);
                        }
                    }

                    // need to remove the resetted one from oldKey list to render the fact that a change occured
                    if ($assetCreationResult['isResetDone']) {
                        $key = array_search($asset->getFilename(), $oldKeyList);
                        if ($key !== false) { // need to prevent the 0
                            unset($oldKeyList[$key]);
                        }
                    }

                    if ($assetCreationResult['isResetAborted']) {
                        if (empty($asset->getDependencies()->getRequiredBy())) {
                            $this->logWarning(self::WARN_NO_REPLACEMENT_ASSET_ALREADY_EXIST, $asset->getFilename(), $this->line);
                        } else {
                            foreach ($asset->getDependencies()->getRequiredBy() as $requiredBy) {
                                if ($requiredBy['type'] == 'object' && $requiredBy['id'] == $this->inProcessObject->getId()) {
                                    $this->logWarning(self::WARN_NO_REPLACEMENT_ASSET_ALREADY_EXIST, $asset->getFilename(), $this->line);
                                    break;
                                }
                                if ($requiredBy['type'] == 'object' && $requiredBy['id'] != $this->inProcessObject->getId()) {
                                    // therefore, no change to notify for this one
                                    $key = array_search($asset->getFilename(), $newKeyList);
                                    if ($key !== false) { // need to prevent the 0
                                        unset($newKeyList[$key]);
                                    }
                                    $this->logWarning(self::WARN_NO_REPLACEMENT_ASSET_ALREADY_EXIST_ON_ANOTHER_OBJECT, $asset->getFilename(), $this->line);
                                    break;
                                }
                            }
                        }
                    }
                } else {
                    // in case no reset asked, we only keep the first one
                    if ($assetCreationResult['isNameInUse']) {
                        if (empty($asset->getDependencies()->getRequiredBy())) {
                            $this->logWarning(self::WARN_NO_ADDITION_ASSET_ALREADY_EXIST, $asset->getFilename(), $this->line);
                        } else {
                            foreach ($asset->getDependencies()->getRequiredBy() as $requiredBy) {
                                if ($requiredBy['type'] == 'object' && $requiredBy['id'] == $this->inProcessObject->getId()) {
                                    $this->logWarning(self::WARN_NO_ADDITION_ASSET_ALREADY_EXIST, $asset->getFilename(), $this->line);
                                    break;
                                }
                                if ($requiredBy['type'] == 'object' && $requiredBy['id'] != $this->inProcessObject->getId()) {
                                    // therefore, no change to notify for this one
                                    $key = array_search($asset->getFilename(), $newKeyList);
                                    if ($key !== false) { // need to prevent the 0
                                        unset($newKeyList[$key]);
                                    }
                                    $this->logWarning(self::WARN_NO_ADDITION_ASSET_ALREADY_EXIST_ON_ANOTHER_OBJECT, $asset->getFilename(), $this->line);
                                    break;
                                }
                            }
                        }
                    }
                }

            } catch (\Exception $e) {
                $this->logWarning(self::WARN_GLOBAL_ASSET_NOT_FOUND, $fileName . ' (' . $objectFieldName . ')', $this->line);
            }
        }

        // if was in reset, we need to delete those that were not replacement or new
        if ($isReset) {
            /** @var null|Asset|Asset\Archive|Asset\Audio|Asset\Document|Folder|Asset\Image|Asset\Text|Asset\Unknown|Asset\Video $oldAsset */
            foreach ($oldAssetCollection as $oldAsset) {
                if ($oldAsset) {
                    try {
                        $oldFileName = $oldAsset->getFilename();
                        $oldAsset->delete();
                        $this->vMessage('  - deleted : ' . $oldFileName);
                    } catch (\Exception $e) {
                        $this->logWarning(static::WARN_PRODUCT_ASSET_COULD_NOT_BE_DELETED,
                            'Image: ' . $oldAsset->getFullPath(),
                            $this->line
                        );
                    }
                }
            }
        }

        if ($assets) {

            // if no reset, image were only added to collection
            if (!$isReset) {
                $changed = !empty($newKeyList);
            } else {
                $diff1 = array_diff($oldKeyList, $newKeyList);
                $diff2 = array_diff($newKeyList, $oldKeyList);
                $orderDiff = array_diff_assoc($oldKeyList, $newKeyList);
                $changed = $diff1 || $diff2 || $orderDiff;
                if ($orderDiff) {
                    $this->vMessage('  - ' . static::WARN_ROW_ITEM_ASSET_ORDER_CHANGED . $objectFieldName);
                }
                // if were in reset, we need to use only the new ones to allow changing order
                $assets = $newAssetCollection;
            }

            if ($changed) {
                $this->inProcessObject->$setter($assets);
            }
        }

        return $changed;
    }

    /**
     * Field type href. Link to Object.
     *
     * @param $objectFieldName
     * @param $csvValue
     * @param $className Nom de la classe de l'object à lier
     * @param $uid Clé unique de l'object à lier
     *
     * @return bool
     * @throws \Exception
     */
    public function hrefObject($objectFieldName, $csvValue, $className, $uid)
    {
        if ($csvValue == '') {
            return false;
        }
        $changed = false;
        $setter = sprintf('set%s', ucfirst($objectFieldName));
        $getter = sprintf('get%s', ucfirst($objectFieldName));
        $targetObjectGetter = sprintf('getBy%s', ucfirst($uid));
        $oldKey = '';
        if (!method_exists($this->inProcessObject, $setter)) {
            $this->logWarning(self::WARN_GLOBAL_FIELD_NOT_EXISTS, $objectFieldName, $this->line);
            return false;
        }

        if ($oldObject = $this->inProcessObject->$getter()) {
            $oldKey = $oldObject->getKey();
        }

        $objectClassName = sprintf('\\Pimcore\\Model\\DataObject\\%s', ucfirst($className));
        $targetObject = call_user_func_array(
            array($objectClassName, $targetObjectGetter),
            array($csvValue, ['limit' => 1, 'unpublished' => true])
        );
        if ($targetObject && $targetObject instanceof $objectClassName) {
            if ($oldKey != $targetObject->getKey()) {
                $this->inProcessObject->$setter($targetObject);
                $changed = true;
            }
        } else {
            $this->logWarning(self::WARN_GLOBAL_OBJECT_NOT_FOUND, $objectClassName . ' (' . $objectFieldName . ')', $this->line);
        }
        return $changed;
    }


    /**
     * Field type multiHref. Link to Object.
     *
     * @param $objectFieldName
     * @param $csvValue
     * @param $className Nom de la classe de l'object à lier
     * @param $uid Clé unique de l'object à lier
     * @param string $separator
     *
     * @return bool
     * @throws \Exception
     */
    public function multiHrefObject($objectFieldName, $csvValue, $className, $uid, $separator = ',')
    {
        $changed = false;
        $objects = array();
        $values = array_filter(array_map('trim', explode($separator, $csvValue)));
        $getterTargetObject = sprintf('getBy%s', ucfirst($uid));
        $objectClassName = sprintf('\\Pimcore\\Model\\DataObject\\%s', ucfirst($className));
        $setter = sprintf('set%s', ucfirst($objectFieldName));
        $getter = sprintf('get%s', ucfirst($objectFieldName));
        $oldKeyList = [];
        $newKeyList = [];

        if (!method_exists($this->inProcessObject, $setter)) {
            $this->logWarning(self::WARN_GLOBAL_FIELD_NOT_EXISTS, $objectFieldName, $this->line);
            return false;
        }

        if ($oldValues = $this->inProcessObject->$getter()) {
            foreach ($oldValues as $object) {
                $oldKeyList[] = $object->getKey();
            }
        }

        foreach ($values as $value) {

            $targetObject = call_user_func_array(
                array($objectClassName, $getterTargetObject),
                array($value, ['limit' => 1, 'published' => true])
            );
            if ($targetObject && $targetObject instanceof $objectClassName) {
                $objects[] = $targetObject;
                $newKeyList[] = $targetObject->getKey();
            } else {
                $this->logWarning(self::WARN_GLOBAL_OBJECT_NOT_FOUND, $objectClassName . ' (' . $objectFieldName . ')', $this->line);
            }
        }

        if ($objects) {
            $diff1 = array_diff($oldKeyList, $newKeyList);
            $diff2 = array_diff($newKeyList, $oldKeyList);
            $changed = $diff1 || $diff2;
            if ($changed) {
                $this->inProcessObject->$setter($objects);
            }
        }
        return $changed;
    }

    /**
     * @param string $objectFieldName
     * @param string $csvValue
     * @param string $className
     * @param string $uid Unique field to get target object
     * @param string $separator
     *
     * @return bool
     * @throws \Exception
     */
    public function objectsMetadata(
        string $objectFieldName,
        string $csvValue,
        string $className,
        string $uid,
        string $separator = ','
    ): bool {
        $changed = false;
        $newRelations = array();
        $values = array_filter(array_map('trim', explode($separator, $csvValue)));
        $getterTargetObject = sprintf('getBy%s', ucfirst($uid));
        $compareFieldTargetObject = sprintf('get%s', ucfirst($uid));
        $objectClassName = sprintf('\\Pimcore\\Model\\DataObject\\%s', ucfirst($className));
        $setter = sprintf('set%s', ucfirst($objectFieldName));
        $getter = sprintf('get%s', ucfirst($objectFieldName));
        $oldKeyList = [];
        $newKeyList = [];

        if (!method_exists($this->inProcessObject, $setter)) {
            $this->logWarning(self::WARN_GLOBAL_FIELD_NOT_EXISTS, $objectFieldName, $this->line);
            return false;
        }

        if ($relations = $this->inProcessObject->$getter()) {
            /** @var \Pimcore\Model\DataObject\Data\ObjectMetadata $relation */
            foreach ($relations as $relation) {
                $oldKeyList[] = $relation->getObject()->$compareFieldTargetObject();
            }
        }

        foreach ($values as $value) {
            //Chargement de l'objet à lié
            $targetObject = call_user_func_array(
                array($objectClassName, $getterTargetObject),
                array($value, ['condition' => '', 'limit' => 1, 'published' => true])
            );
            if ($targetObject && $targetObject instanceof $objectClassName) {
                $newRelations[] = new ObjectMetadata($objectFieldName, [], $targetObject);
                $newKeyList[] = $targetObject->$compareFieldTargetObject();
            } else {
                $this->logWarning(self::WARN_GLOBAL_OBJECT_NOT_FOUND, $objectClassName . ' (' . $objectFieldName . ')', $this->line);
            }
        }

        if ($newRelations) {
            $diff1 = array_diff($oldKeyList, $newKeyList);
            $diff2 = array_diff($newKeyList, $oldKeyList);
            $changed = $diff1 || $diff2;
            if ($changed) {
                $this->inProcessObject->$setter($newRelations);
            }
        }
        return $changed;
    }

    public function objectBrick(
        string $objectFieldName,
        string $csvValue,
        string $className,
        string $uid,
        string $separator = ',',
        string $compareField = 'Key',
        array $metaData = [],
        $filterCsvValueCallBack = null
    ) {

    }


    /**
     * @param null $csvPath
     *
     * @return bool|CsvReader
     * @throws InvalidCsvException
     */
    public function getCsvReader($csvPath = null)
    {
        $csvFilePath = $csvPath ?: $this->csvPath;
        $this->vvMessage($csvFilePath);
        if (!file_exists($csvFilePath)) {
            return false;
        }
        $csvReader = new CsvReader($csvFilePath, $this->parseCsvHeader(), $this->getCsvSeparator());
        if ($this->parseCsvHeader()) {
            $this->csvHeader = $csvReader->getHeader();
            $missingFields = $this->checkMandatoryFields($this->csvHeader);
            if (count($missingFields)) {
                if ($this->getCsvSeparator() !== $csvReader->delimiter) {
                    $msg = 'CSV non conforme. Le séparateur doit être "' . $this->getCsvSeparator() . '"';
                } else {
                    if (count($missingFields) == 1) {
                        $msg = 'CSV non conforme. La colonne suivante est manquante : ' . array_shift($missingFields);
                    } else {
                        $msg = 'CSV non conforme. Les colonnes suivantes sont manquantes : ' . implode(', ', $missingFields);
                    }
                }
                $this->error = true;
                $this->logError(self::LOG_ERROR, $this->loggerComponent . ' : ' . $msg);
                throw new InvalidCsvException($msg);
            } else {
                if ($this->getCsvSeparator() !== $csvReader->delimiter) {
                    $this->output->writeln(
                        '[INFO] Le séparateur n\'est pas celui attendu. Présent : '
                        . $csvReader->delimiter . ' Attendu : ' . $this->getCsvSeparator());
                }
            }
        }
        return $csvReader;
    }

    /**
     * @return array|bool
     * @throws InvalidCsvException
     */
    public function getCsvData()
    {
        if (is_null($this->csvData)) {
            $csvReader = $this->getCsvReader();
            $this->csvData = $csvReader ? $csvReader->get() : false;
        }
        return $this->csvData;
    }


    /**
     * Check if csv is valid
     * only for csv with header and mandatoryFields is set in concrete class.
     *
     * @param $header
     *
     * @return array missing fields
     */
    protected function checkMandatoryFields($header): array
    {
        $missingFields = [];
        if (is_array($header)) {
            $mandatoryFields = $this->mandatoryFields;
            if (is_array($mandatoryFields)) {
                $intersect = array_intersect($mandatoryFields, $header);
                $diff = array_diff($mandatoryFields, $intersect);
                if (count($diff) > 0) {
                    $missingFields = $diff;
                }
            }
        }
        return $missingFields;
    }


    /**
     * @param string $csvPath
     *
     * @return $this
     */
    public function setCsvPath($csvPath)
    {
        $this->csvPath = $csvPath;
        return $this;
    }

    /**
     * @param string $assetsPath
     *
     * @return $this
     */
    public function setAssetsPath($assetsPath)
    {
        $this->assetsPath = $assetsPath;
        return $this;
    }


    /**
     * @param $absoluteFilename
     * @param bool $success
     */
    protected function moveFile($absoluteFilename, $success = true)
    {
        $date = date('Y-m-d-H-i-s');
        $parts = pathinfo($absoluteFilename);
        $destName = $parts['filename'] . '_' . $date . '.' . $parts['extension'];
        if ($success) {
            $destDir = self::PROCESSED_FOLDER_NAME . DIRECTORY_SEPARATOR;
        } else {
            $destDir = self::FAILED_FOLDER_NAME . DIRECTORY_SEPARATOR;
        }
        $destFullDir = $parts['dirname'] . DIRECTORY_SEPARATOR . $destDir;

        // Création du dossier en local
        if (!is_dir($destFullDir)) {
            mkdir($destFullDir, 0755, true);
        }

        // Déplacement du fichier csv
        if (file_exists($absoluteFilename)) {
            rename($absoluteFilename, $destFullDir . $destName);
        }
    }


    protected function moveCsvAfterImport()
    {
        if ($this->csvPath) {
            $this->moveFile($this->csvPath, !$this->error);
            // Déplacement des assets (zip)
            if (is_file($this->assetsPath)) {
                $this->moveFile($this->assetsPath, !$this->error);
            }
        }
    }

    /**
     * @return array
     */
    public function getUpdateFields()
    {
        return $this->updateFields;
    }

    /**
     * @param array $updateFieldsConfig , config from configProcessor.xml
     *
     * @return AbstractImporter
     */
    public function setUpdateFields($updateFieldsConfig)
    {
        if (!$updateFieldsConfig) {
            $this->updateFields = [self::PROTECTED_KEY => self::ALL];
        } else {

            $authorized = isset($updateFieldsConfig[self::AUTHORIZED_KEY])
                ? $updateFieldsConfig[self::AUTHORIZED_KEY]
                : null;
            $protected = isset($updateFieldsConfig[self::PROTECTED_KEY])
                ? $updateFieldsConfig[self::PROTECTED_KEY]
                : null;
            $authorizedFields = [];
            $protectedFields = [];

            if ($authorized) {
                $authorizedFields = array_map('trim', explode(',', $authorized));
            }
            if ($protected) {
                $protectedFields = array_map('trim', explode(',', $protected));
            }

            if ($authorizedFields[0] == '*') {
                // Tous les champs sont autorisés
                $this->updateFields = [self::AUTHORIZED_KEY => self::ALL];
            } elseif (count($authorizedFields)) {
                // Seulement certains champs sont mis à jour
                $this->updateFields = [self::AUTHORIZED_KEY => $authorizedFields];
            } elseif ($protectedFields[0] == '*') {
                // Aucun mis à jour
                $this->updateFields = [self::PROTECTED_KEY => self::ALL];
            } elseif (count($protectedFields)) {
                // Seulement certains champs ne sont pas mis à jour
                $this->updateFields = [self::PROTECTED_KEY => $protectedFields];
            } else {
                $this->updateFields = [self::PROTECTED_KEY => self::ALL];
            }
        }
        return $this;
    }

    /**
     * Check if $field can to be updated
     *
     * @param $field
     * @param $row
     *
     * @return bool
     */
    public function canProcessField($field, $row)
    {
        $canProcess = true;

        if ($field == '*') {
            return true;
        }

        if (!isset($row[$field]) || trim($row[$field]) == '') {
            return false;
        }

        if ($this->getMode() == self::UPDATE_MODE) {
            if (isset($this->updateFields[self::AUTHORIZED_KEY])) {

                $canProcess = $this->updateFields[self::AUTHORIZED_KEY] == self::ALL
                    || in_array($field, $this->updateFields[self::AUTHORIZED_KEY]);

            } elseif (isset($this->updateFields[self::PROTECTED_KEY])) {

                $canProcess = !$this->updateFields[self::PROTECTED_KEY] == self::ALL
                    && !in_array($field, $this->updateFields[self::PROTECTED_KEY]);

            } else {
                $canProcess = false;
            }
        }
        return $canProcess;
    }


    /**
     * @param string $value
     * @param $default
     *
     * @return float | false
     */
    public function getFloatVal($value, $default = null)
    {
        if (empty(trim($value)) && !is_null($default)) {
            $value = $default;
        }
        $value = str_replace(',', '.', trim($value));
        $value = str_replace(' ', '', $value);
        return filter_var($value, FILTER_VALIDATE_FLOAT);
    }

    /**
     * @return string
     */
    public function getImportBasePath(): string
    {
        return $this->importBasePath;
    }

    /**
     * @param string $importBasePath
     *
     * @return AbstractImporter
     */
    public function setImportBasePath(string $importBasePath): AbstractImporter
    {
        $this->importBasePath = $importBasePath;
        return $this;
    }

    public function getProcessingPath()
    {
        $dir = dirname($this->csvPath);
        return $dir . DIRECTORY_SEPARATOR . self::PROCESSING_SUB_FOLDER . DIRECTORY_SEPARATOR;
    }

    protected function addLoggerComponentOption($options)
    {
        if (!isset($options['relatedObject']) && $this->inProcessObject) {
            $options['relatedObject'] = $this->inProcessObject;
        }
        return parent::addLoggerComponentOption($options);
    }

    /**
     * To override if needed
     *
     * @param $objectFieldName
     *
     * @return bool
     */
    protected function isMultiHrefAssetInReset($objectFieldName)
    {
        return false;
    }
}
