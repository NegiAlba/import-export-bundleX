<?php

namespace Galilee\ImportExportBundle\Helper;

ini_set('auto_detect_line_endings', TRUE);

/**
 * Description of CsvImporter
 *
 * @author admin
 */
class CsvReader
{
    public $fp;
    public $parseHeader;
    public $header;
    public $delimiter;
    public $length;

    protected $allowedDelimiters = [',', ';'];

    /**
     * CsvReader constructor.
     * @param $fileName
     * @param bool $parseHeader
     * @param string $delimiter
     * @param int $length
     * @throws \Exception
     */
    function __construct($fileName, $parseHeader = true, $delimiter = ";", $length = 0)
    {
        if (!file_exists($fileName)) {
            throw new \Exception('File not found : ' . $fileName);
        }
        $this->fp = fopen($fileName, "r");
        $this->parseHeader = $parseHeader;
        $this->delimiter = $this->detectDelimiter($delimiter);
        $this->length = $length;

        if ($this->parseHeader) {
            rewind($this->fp);
            $this->header = fgetcsv($this->fp, $this->length, $this->delimiter);
            foreach ($this->header as $i => $value) {
                $this->header[$i] = trim($value);
            }
        }
    }

    protected function detectDelimiter($delimiter = ';')
    {
        if ($this->parseHeader) {
            rewind($this->fp);
            $header = fgets($this->fp);
            $count = [];
            foreach ($this->allowedDelimiters as $allowedDelimiter) {
                $count[$allowedDelimiter] = count(str_getcsv($header, $allowedDelimiter));
                rewind($this->fp);
            }
            if (max($count) > 1) {
                $delimiter = array_search(max($count), $count);
            }
        }
        return $delimiter;
    }

    public function getHeader()
    {
        return $this->header;
    }


    function __destruct()
    {
        if ($this->fp) {
            fclose($this->fp);
        }
    }

    function get($max_lines = 0)
    {
        //if $max_lines is set to 0, then get all the data

        rewind($this->fp);
        if ($this->parseHeader) {
            fgetcsv($this->fp, $this->length, $this->delimiter);
        }

        $data = array();

        if ($max_lines > 0) {
            $line_count = 0;
        } else {
            $line_count = -1;
        } // so loop limit is ignored

        while ($line_count < $max_lines
            && ($row = fgetcsv($this->fp, $this->length, $this->delimiter)) !== FALSE
        ) {
            if ($this->parseHeader) {
                $row_new = [];
                foreach ($this->header as $i => $heading_i) {
                    $row_new[$heading_i] = (isset($row[$i]) ? trim($row[$i]) : "");
                }
                $data[] = $row_new;
            } else {
                $data[] = array_map('trim', $row);
            }

            if ($max_lines > 0)
                $line_count++;
        }
        return $data;
    }

    public function getGenerator()
    {
        rewind($this->fp);
        if ($this->parseHeader) {
            fgetcsv($this->fp, $this->length, $this->delimiter);
        }
        while (($row = fgetcsv($this->fp, $this->length, $this->delimiter)) !== FALSE) {
            if ($this->parseHeader) {
                $rowWithKey = [];
                foreach ($this->header as $i => $heading_i) {
                    $rowWithKey[$heading_i] = (isset($row[$i]) ? trim($row[$i]) : "");
                }
                yield $rowWithKey;
            } else {
                yield array_map('trim', $row);
            }
        }
    }


    public function getCount()
    {
        rewind($this->fp);
        $count = $this->parseHeader ? -1 : 0;
        while (($row = fgetcsv($this->fp, $this->length, $this->delimiter)) !== FALSE) {
            $count++;
        }
        rewind($this->fp);
        return $count;
    }

}
