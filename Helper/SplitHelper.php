<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2017 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Helper;

use Pimcore\Db;
use Pimcore\Model\DataObject\ClassDefinition;

class SplitHelper
{
    const MAX_ROW_PER_FILE = 1000;

    /**
     * @var CsvReader
     */
    public $csvReader;

    /**
     * @var Destination path
     */
    public $path;

    public $csvPath;

    public function __construct(string $csvPath,  string $path)
    {
        $this->path = $path;
        $this->csvPath = $csvPath;
    }

    public function getSplitFullFileName($idx)
    {
        $filename = pathinfo($this->csvPath)['filename'];
        return $this->path . $filename . '.' . str_pad($idx, 11, 0, STR_PAD_LEFT) . '.csv';
    }

    public function getCsvWriter($idx)
    {
        $splitName = $this->getSplitFullFileName($idx);
        $csvWriter = new CsvWriter($splitName);
        $csvWriter->separator = ';';
        $csvWriter->addRow($this->csvReader->getHeader());
        return $csvWriter;
    }

    public function splitCsv()
    {
        if (!$this->csvReader) {
            return false;
        }
        $idx = 1;
        $count = 0;
        $csvWriter = $this->getCsvWriter($idx);
        while ($row = fgetcsv($this->csvReader->fp, $this->csvReader->length, $this->csvReader->delimiter)) {
            //No more than 1000 rows per file
            if ($count >= self::MAX_ROW_PER_FILE) {
                $csvWriter->close();
                $count = 0;
                $idx++;
                $csvWriter = $this->getCsvWriter($idx);
            } else {
                $count++;
            }
            $csvWriter->addRow($row);
        }
        return $idx;
    }

    public function getSplitFileList()
    {
        $splitFiles = [];
        $pattern = $this->path . '*.csv';
        foreach (glob($pattern) as $filename) {
            $splitFiles[] = $filename;
        }
        return $splitFiles;
    }

}
