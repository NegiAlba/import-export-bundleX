<?php

namespace Galilee\ImportExportBundle\Template\Exporter;

use Galilee\ImportExportBundle\Helper\CsvWriter;
use Galilee\ImportExportBundle\Processor\Exporter\AbstractExporter;
use Pimcore\Db;
use Pimcore\Model\DataObject;

class Document extends AbstractExporter
{
    /**
     * @var string
     */
    public $exportFileName = 'document.csv';

    /**
     * @var array
     */
    public $baseColumns = [
        'filepath',
        'label',
        'visible',
        'visible_disconnected',
        'position',
        'skus',
        'websites'
    ];

    public $loggerComponent = 'Export des Documents';

    /**
     * {@inheritdoc}
     */
    public function preProcess()
    {
        $this->exportFileName = date("Y-m-d-H-i-s") . '_' . $this->exportFileName;
    }

    protected function getQuery()
    {
        $exportDate = strtotime($this->exportDate);
        $productTable = sprintf('object_%d', DataObject\Document::classId());
        $sql = sprintf(
            'SELECT' .
            ' *' .
            ' FROM %s' .
            ' WHERE o_modificationDate >= %d ',
            $productTable, $exportDate);
        return $sql;
    }

    /**
     * {@inheritdoc}
     */
    public function process()
    {
        $this->writeInfo('Export des documents modifiés après le : ' . $this->exportDate);

        $db = Db::get();
        $sql = $this->getQuery();
        $documentsArray = $db->fetchAll($sql);
        $totalCount = count($documentsArray);
        $this->writeInfo('Nombre de Document(s) : ' . $totalCount);

        if ($totalCount) {
            $csvWriter = new CsvWriter($this->exportPath . $this->exportFileName);
            $csvWriter->addRow($this->baseColumns);

            $count = 1;
            foreach ($documentsArray as $documentArray) {
                $document = DataObject\Document::getById($documentArray['oo_id']);
                if ($document) {
                    $this->vMessage($count . '/' . $totalCount . ' > ' . $document->getFilepath());
                    $websiteRelations = $document->getWebsites();
                    if ($websiteRelations != null) {
                        $row = $this->getRow($document);
                        if ($row) {
                            $csvWriter->addRow($row);
                        }
                    } else {
                        $this->writeWarning('Pas de Website attribué pour le document: ' . $document->getFilepath());
                    }
                    $count++;
                } else {
                    $this->vMessage('Error load document ' . $documentArray['oo_id']);
                }
            }
            $csvWriter->close();
            $this->writeInfo('Fichier d\'export  : ' . $this->exportPath . $this->exportFileName);
        }
    }

    /**
     * @param DataObject\Document $document
     *
     * @return array
     */
    protected function getRow(DataObject\Document $document)
    {
        $row = array_fill_keys($this->baseColumns, '');

        $row['filepath'] = $document->getFilepath();
        $row['label'] = $document->getLabel();
        $row['visible'] = ($document->getVisible() == true) ? '1' : '0';
        $row['visible_disconnected'] = ($document->getVisibleDisconnected() === true) ? '1' : '0';
        $row['position'] = (!is_null($document->getPosition())) ? $document->getPosition() : '';
        $row['skus'] = implode(
            self::DEFAULT_EXPORT_MULTI_VALUE_SEPARATOR,
            array_map(
                function (DataObject\Product $product) {
                    $hasChildren = $product->hasChildren(\Galilee\ImportExportBundle\Template\Exporter\Product::OBJECT_TYPES);
                    $type = $hasChildren
                        ? \Galilee\ImportExportBundle\Template\Exporter\Product::PRODUCT_TYPE_GROUPED
                        : \Galilee\ImportExportBundle\Template\Exporter\Product::PRODUCT_TYPE_SIMPLE;
                    $sku = $product->getSku();
                    if ($type == \Galilee\ImportExportBundle\Template\Exporter\Product::PRODUCT_TYPE_GROUPED) {
                        $sku .= '-grouped';
                    }
                    return $sku;
                },
                $this->getProducts($document)));
        $row['websites'] = implode(
            self::DEFAULT_EXPORT_MULTI_VALUE_SEPARATOR,
            array_map(
                function (DataObject\Data\ObjectMetadata $objectMetaData) {
                    return $objectMetaData->getObject()->getCode();
                },
                $document->getWebsites()));

        return $row;
    }

    /**
     * @param DataObject\Document $document
     *
     * @return array
     */
    protected function getProducts(DataObject\Document $document)
    {
        $products = [];
        $productTable = sprintf('object_%d', DataObject\Product::classId());
        $sql = sprintf(
            'SELECT' .
            ' *' .
            ' FROM %s' .
            ' WHERE documents LIKE "%%,%s,%%"',
            $productTable, $document->getId());
        $db = Db::get();
        $productsArray = $db->fetchAll($sql);

        if (count($productsArray)) {
            foreach ($productsArray as $productArray) {
                $product = DataObject\Product::getById($productArray['oo_id']);
                if ($product) {
                    $products[] = $product;
                } else {
                    $this->vMessage('Error load product ' . $productArray['oo_id']);
                }
            }
        }

        return $products;
    }
}
