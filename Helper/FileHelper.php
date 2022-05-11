<?php
/**
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2017 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Helper;

class FileHelper
{
    public const PATTERN_TIMESTAMPED_FILE = '/^(\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2})_%s$/';

    public static function getTimeStampedFiles($path, $filename)
    {
        $result = [];
        $globPattern = sprintf('*%s', $filename);
        $path = rtrim($path, '/');
        if (is_dir($path)) {
            $result = glob($path.DIRECTORY_SEPARATOR.$globPattern);
        }

        return $result;
    }

    public static function getWrongTimeStampedFiles($path, $filename)
    {
        $path = rtrim($path, '/');
        $patternMatch = sprintf(static::PATTERN_TIMESTAMPED_FILE, $filename);
        $matchesFiles = static::getTimeStampedFiles($path, $filename);
        $wrongFiles = [];

        foreach ($matchesFiles as $file) {
            $pathParts = pathinfo($file);
            preg_match($patternMatch, $pathParts['basename'], $matches);
            if (0 === count($matches) && $pathParts['basename'] !== $filename) {
                $wrongFiles[] = $file;
            }
        }

        return $wrongFiles;
    }

    public static function getCurrentTimeStampedFile($path, $filename)
    {
        $path = rtrim($path, '/');
        $patternMatch = sprintf(static::PATTERN_TIMESTAMPED_FILE, $filename);
        $matchesFiles = static::getTimeStampedFiles($path, $filename);
        $current = null;

        if (in_array($path.DIRECTORY_SEPARATOR.$filename, $matchesFiles)) {
            $current = $path.DIRECTORY_SEPARATOR.$filename;
        } else {
            foreach ($matchesFiles as $file) {
                $pathParts = pathinfo($file);
                preg_match($patternMatch, $pathParts['basename'], $matches);
                if ($matches[1]) {
                    $current = $file;
                    break;
                }
            }
        }

        return $current;
    }

    /**
     * Find asset zip file with same timestamp as csv file.
     * If not found return {type}.zip (default).
     *
     * @param $type
     * @param $csvFile
     *
     * @return string
     */
    public static function getCurrentTimeStampedZipAssetFile($type, $csvFile)
    {
        $pathdir = pathinfo($csvFile, PATHINFO_DIRNAME);
        $pathfile = pathinfo($csvFile, PATHINFO_FILENAME);
        $patternMatch = sprintf(static::PATTERN_TIMESTAMPED_FILE, $type.'.csv');
        $timestampedZipFilename = $pathdir.DIRECTORY_SEPARATOR.$pathfile.'.zip';
        if (file_exists($timestampedZipFilename)) {
            // {/import/path}/{Y-m-d-H-i-s}_{type}.zip
            $assetFile = $timestampedZipFilename;
        } else {
            // {/import/path}/{type}.zip
            $assetFile = $pathdir.DIRECTORY_SEPARATOR.$type.'.zip';
        }

        return $assetFile;
    }
}