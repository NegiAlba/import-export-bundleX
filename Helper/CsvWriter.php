<?php

namespace Galilee\ImportExportBundle\Helper;

class CsvWriter
{
    public $file;
    public $separator = ',';

    public function __construct($fullPath)
    {
        $dirName = dirname($fullPath);
        if (!file_exists($dirName)) {
            mkdir($dirName, 0755, true);
        }
        $this->file = fopen($fullPath, 'w');
    }

    public function addRow(array $data)
    {
        return fputcsv($this->file, $data, $this->separator);
    }

    public function close()
    {
        if ($this->file) {
            fclose($this->file);
        }
        $this->file = null;
    }

    public function __destruct()
    {
        $this->close();
    }
}