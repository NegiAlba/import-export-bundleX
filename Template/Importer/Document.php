<?php

namespace Galilee\ImportExportBundle\Template\Importer;

use Galilee\ImportExportBundle\Helper\FileHelper;
use Galilee\ImportExportBundle\Helper\Tools;
use Galilee\ImportExportBundle\Processor\Importer\AbstractImporter;
use Pimcore\File;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\Element\Service;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

class Document extends AbstractImporter
{

    const FIELD_VALUE_SEPARATOR = ',';

    const CHUNK = 500;

    const PARENT_FOLDER_KEY = 'documents';

    const DOCUMENTS_FILE_PATH = 'import/document';

    const DOCUMENT_FOLDER = 'assets';

    const WARN_DOCUMENT_PRODUCT_NOT_FOUND = 'WARN_DOCUMENT_PRODUCT_NOT_FOUND';
    const WARN_DOCUMENT_WEBSITE_NOT_FOUND = 'WARN_DOCUMENT_WEBSITE_NOT_FOUND';
    protected $warningMessages = [
        self::WARN_DOCUMENT_PRODUCT_NOT_FOUND => 'Produit non trouvé',
        self::WARN_DOCUMENT_WEBSITE_NOT_FOUND => 'Website non trouvé'
    ];

    /**
     * @var DataObject\Folder|null
     */
    protected $parentFolder;

    /**
     * @var array
     */
    public $mandatoryFields = [
        'filepath'
    ];

    /**
     * @var DataObject\Document
     */
    protected $inProcessObject;

    /**
     * @var array
     */
    public $mapping = [
        'filepath' => [
            'filepath',
            'setFilePath'
        ],
        'label' => 'label',
        'visible' => 'visible',
        'visible_disconnected' => 'visibleDisconnected',
        'position' => 'position',
        'skus' => [
            'skus',
            'setProducts'
        ],
        'websites' => [
            'websites',
            'setWebsites'
        ]
    ];

    public $loggerComponent = 'Import des Documents';

    /**
     * @var string
     */
    protected $documentsPath;


    /**
     * Document constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->documentsPath = PIMCORE_ASSET_DIRECTORY . DIRECTORY_SEPARATOR . self::DOCUMENTS_FILE_PATH;
    }

    /**
     * {@inheritdoc}
     */
    protected function initObject($row, $csvLineNumber)
    {
        $filepath = $row['filepath'];
        $filename = pathinfo($filepath, PATHINFO_FILENAME);
        $key = File::getValidFilename($filename);
        $key = Tools::normalize($key);

        $parent = $this->parentFolder;

        $this->inProcessObject = DataObject\Document::getByPath(DIRECTORY_SEPARATOR . $parent . DIRECTORY_SEPARATOR . $key);

        if (!$this->inProcessObject) {
            $this->inProcessObject = new DataObject\Document();
            $this->inProcessObject->setKey($key);
            $this->inProcessObject->setParentId($parent->getId());
            $this->inProcessObject->setPublished(true);
        } else {
            $this->setMode(self::UPDATE_MODE);
        }

        try {
            $asset = $this->moveFileDocument($filepath, $key);
            $this->inProcessObject->setFile($asset);
        } catch (\Exception $exception) {
            $this->writeError($exception->getMessage());
            return false;
        }

        $this->feedEmptyField($row);

        $this->inProcessObject->setOmitMandatoryCheck(true);
        $this->inProcessObject->save();
        $this->inProcessObject->setOmitMandatoryCheck(false);
        $this->initObjectMessage = 'Document : ' . $this->inProcessObject->getFullPath();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function preProcess()
    {
        $this->parentFolder = DataObject\Service::createFolderByPath(self::PARENT_FOLDER_KEY);
        $parent = Asset\Service::createFolderByPath(DIRECTORY_SEPARATOR . self::DOCUMENTS_FILE_PATH,
            ['locked' => true]);
        $this->unZip();
        return parent::preProcess();
    }

    /**
     * @param string $objectFieldName
     * @param string $csvValue
     *
     * @return bool
     */
    protected function setFilePath(
        string $objectFieldName,
        string $csvValue
    ) {
        $filepath = $this->inProcessObject->getFile()->getFilename();
        $this->inProcessObject->setFilepath($filepath);
        // @todo return false if no value change.
        return true;
    }

    /**
     * @param string $objectFieldName
     * @param string $csvValue
     *
     * @return bool
     * @throws \Exception
     */
    protected function setProducts(
        string $objectFieldName,
        string $csvValue
    ) {

        $skus = explode(self::FIELD_VALUE_SEPARATOR, $csvValue);

        foreach ($skus as $sku) {
            $list = new DataObject\Product\Listing();
            $list->setObjectTypes([DataObject::OBJECT_TYPE_VARIANT, DataObject::OBJECT_TYPE_OBJECT]);
            $list->setCondition(sprintf('sku = \'%s\'', $sku));
            $list->setLimit(1);
            $products = $list->load();
            if (!empty($products)) {
                /** @var DataObject\Product $product */
                $product = $products[0];
                $this->inProcessObject->save();
                $documentsMetaData = $product->getDocuments();
                $documentMetaData = new DataObject\Data\ObjectMetadata('documents', ['file'], $this->inProcessObject);
                $documentMetaData->setFile($this->inProcessObject->getFile());
                $documentsMetaData[] = $documentMetaData;
                $product->setDocuments($documentsMetaData);
                $product->save();
            } else {
                $this->logWarning(self::WARN_DOCUMENT_PRODUCT_NOT_FOUND, 'SKU : ' . $sku, $this->line);
            }
        }
        // @todo return false if no value change.
        return true;
    }

    /**
     * @param string $objectFieldName
     * @param string $csvValue
     *
     * @return bool
     * @throws \Exception
     */
    protected function setWebsites(
        string $objectFieldName,
        string $csvValue
    ) {
        $parentFolderKey = 'websites';
        $className = 'Website';
        $objectClassName = sprintf('\\Pimcore\\Model\\DataObject\\%s', ucfirst($className));

        $websites = explode(self::FIELD_VALUE_SEPARATOR, $csvValue);

        foreach ($websites as $key => $website) {
            $key = File::getValidFilename($website);
            $obj = $objectClassName::getByPath('/' . $parentFolderKey . '/' . $key);
            if ($obj == null) {
                $this->logWarning(self::WARN_DOCUMENT_WEBSITE_NOT_FOUND,
                    'Code: ' . $website,
                    $this->line
                );
                unset($websites[$key]);
            }
        }

        if (is_array($this->inProcessObject->getWebsites()) && count($this->inProcessObject->getWebsites()) > 0) {
            /** @var \Pimcore\Model\DataObject\Data\ObjectMetadata $objectMetaData */
            foreach ($this->inProcessObject->getWebsites() as $objectMetaData) {
                /** @var DataObject\Website $website */
                $website = $objectMetaData->getObject();
                $code = $website->getCode();
                if (!in_array($code, $websites)) {
                    $websites[] = $code;
                }
            }
        }

        $csvValue = implode(self::FIELD_VALUE_SEPARATOR, $websites);

        return self::objectsMetadata(
            $objectFieldName,
            $csvValue,
            $className,
            'Code',
            self::FIELD_VALUE_SEPARATOR
        );
    }

    /**
     * @param string $filename
     *
     * @return Asset
     * @throws \Exception
     */
    protected function moveFileDocument(string $filename)
    {
        $key = Service::getValidKey(trim($filename), 'asset');
        $importPath = $this->getImportBasePath() . DIRECTORY_SEPARATOR . self::DOCUMENT_FOLDER;
        $filepath = $importPath . DIRECTORY_SEPARATOR . $filename;
        if (!file_exists($filepath)) {
            if (!$asset = Asset::getByPath(DIRECTORY_SEPARATOR . self::DOCUMENTS_FILE_PATH . DIRECTORY_SEPARATOR . $key)) {
                throw new FileNotFoundException(sprintf('Le fichier "%s" est introuvable dans "%s".', $filename,
                    self::DOCUMENT_FOLDER . DIRECTORY_SEPARATOR));
            } else {
                return $asset;
            }
        }

        if ($asset = Asset::getByPath(DIRECTORY_SEPARATOR . self::DOCUMENTS_FILE_PATH . DIRECTORY_SEPARATOR . $key)) {
            $asset->delete();
        }

        $asset = $this->createAsset($filename, self::DOCUMENTS_FILE_PATH);
        $asset['asset']->save();
        unlink($filepath);

        return $asset['asset'];
    }

    /**
     * @return bool
     */
    protected function unZip()
    {
        $csvFile = FileHelper::getCurrentTimeStampedFile($this->importBasePath, $this->getType() . '.csv');
        $zipFile = FileHelper::getCurrentTimeStampedZipAssetFile($this->getType(), $csvFile);
        if (!file_exists($zipFile)) {
            return false;
        }

        $archive = new \ZipArchive();
        if ($archive->open($zipFile) === true) {
            $archive->extractTo($this->getImportBasePath() . DIRECTORY_SEPARATOR . self::DOCUMENT_FOLDER);
            $archive->close();
            unlink($zipFile);
            return true;
        }

        return false;
    }

    /**
     * @param array $row
     *
     * @throws \Exception
     */
    protected function feedEmptyField(array $row)
    {
        if ((!isset($row['label']) || $row['label'] == '') && $this->inProcessObject->getLabel() == null) {
            $filepath = $row['filepath'];
            $filename = pathinfo($filepath, PATHINFO_FILENAME);
            $this->inProcessObject->setLabel($filename);
        }
        if ((!isset($row['visible']) || $row['visible'] == '') && $this->inProcessObject->getVisible() == null) {
            $this->inProcessObject->setVisible(true);
        }
        if ((!isset($row['visible_disconnected']) || $row['visible_disconnected'] == '') && $this->inProcessObject->getVisibleDisconnected() == null) {
            $this->inProcessObject->setVisibleDisconnected(false);
        }
        if ((!isset($row['position']) || $row['position'] == '') && $this->inProcessObject->getPosition() == null) {
            $this->inProcessObject->setPosition('');
        }
        if ((!isset($row['websites']) || $row['websites'] == '') && ($this->inProcessObject->getWebsites() == null || count($this->inProcessObject->getWebsites()) < 1)) {
            $this->setWebsites('websites', 'base');
        }
    }
}
