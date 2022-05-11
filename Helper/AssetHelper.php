<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2017 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Helper;

use Exception;
use Pimcore\File;
use Pimcore\Model\Asset;
use Pimcore\Model\Asset\Folder;
use Pimcore\Model\Element;
use Pimcore\Config;

class AssetHelper
{

    const MAGENTO_THUMB_PREVIEW_PROFILE_NAME = 'ecom_magento';
    const IMAGE_ALLOWED_MIMETYPES_FOR_MAGENTO = ['image/jpg', 'image/jpeg'];
    const IMAGE_MIN_WIDTH_FOR_MAGENTO = 2000;
    const IMAGE_MAX_WIDTH_FOR_MAGENTO = 2200;

    /**
     * Create an asset from file.
     *
     * Cf pimcore/modules/admin/controllers/AssetController.php
     *
     * @param Asset $parent
     * @param string $sourcePath
     * @param string $filename
     * @param integer $userModificationId
     * @param bool $overwrite
     *
     * @return null|Asset
     * @throws Exception
     */
    public static function addAsset(Asset $parent, $sourcePath, $filename, $userModificationId, $overwrite = true)
    {
        $filename = Element\Service::getValidKey(trim($filename), 'asset');
        if (empty($filename)) {
            throw new Exception('The filename of the asset is empty');
        }
        if (!$overwrite) {
            // check for duplicate filename
            $filename = self::getAssetSafeFilename($parent->getRealFullPath(), $filename);
        }

        $asset = null;
        $pathExists = Asset\Service::pathExists($parent->getFullPath() . '/' . $filename);
        if ($pathExists) {
            $asset = Asset::getByPath($parent->getFullPath() . '/' . $filename);
        }

        if (!$asset) {
            $asset = Asset::create($parent->getId(), [
                "filename" => $filename,
                "sourcePath" => $sourcePath,
                "userModification" => $userModificationId
            ]);
        }
        return $asset;
    }

    /**
     * @param $targetPath
     * @param $filename
     *
     * @return mixed
     * @todo move to Service
     * Cf pimcore/modules/admin/controllers/AssetController.php
     *
     */
    public static function getAssetSafeFilename($targetPath, $filename)
    {
        $originalFilename = $filename;
        $count = 1;

        if ($targetPath == '/') {
            $targetPath = '';
        }

        while (true) {
            if (Asset\Service::pathExists($targetPath . '/' . $filename)) {
                $filename = str_replace('.' . File::getFileExtension($originalFilename),
                    '_' . $count . '.' . File::getFileExtension($originalFilename), $originalFilename);
                $count++;
            } else {
                return $filename;
            }
        }
    }

    /**
     * @param \ZipArchive $zip
     * @param $assetName
     * @param Folder $parent
     * @param int $userModificationId
     * @param bool $isReset
     *
     * @return array
     * @throws Exception
     */
    public static function createAssetFromZip(
        \ZipArchive $zip,
        $assetName,
        Asset\Folder $parent,
        $userModificationId = 1,
        $isReset = false
    ) {
        $isResetAborted = false;
        $isResetDone = false;
        $isNameInUse = false;

        $fileData = $zip->getFromName($assetName);
        $filename = Element\Service::getValidKey(trim($assetName), 'asset');

        if ($fileData) {
            $fullPath = $parent->getFullPath();
            $assetPath = $fullPath . DIRECTORY_SEPARATOR . $filename;

            $pathExists = Asset\Service::pathExists($assetPath);

            $asset = null;
            if ($pathExists != null) {
                $isNameInUse = true;
                $asset = Asset::getByPath($assetPath);
                if ($isReset) {
                    // we delete it as we need to know that previews must be rebuilt,
                    // if file was the same, no need to overwrite it and regenerate previews
                    if (md5($asset->getData()) != md5($fileData)) {
                        $asset->delete();
                        $isResetDone = true;
                    } else {
                        $isResetAborted = true;
                    }
                }
            }
            if (!$asset || $isResetDone) {
                $asset = Asset::create(
                    $parent->getId(),
                    array(
                        "filename" => $filename,
                        "data" => $fileData,
                        "userOwner" => $userModificationId,
                        "userModification" => $userModificationId
                    ));
            }
        } else {
            throw new Exception($assetName . ' non trouvé dans le fichier zip .');
        }
        return [
            'asset' => $asset,
            'isNameInUse' => $isNameInUse,
            'isResetDone' => $isResetDone,
            'isResetAborted' => $isResetAborted
        ];
    }

    /**
     * @param $sourcePath
     * @param $assetName
     * @param Folder $parent
     * @param int $userModificationId
     * @param bool $isReset
     * @param null $objectId
     *
     * @return array
     * @throws Exception
     */
    public static function createAssetFromFile(
        $sourcePath,
        $assetName,
        Asset\Folder $parent,
        $userModificationId = 1,
        $isReset = false,
        $objectId = null
    ) {
        $isResetDone = false;
        $isResetAborted = false;
        $isNameInUse = false;

        $filename = Element\Service::getValidKey(trim($assetName), 'asset');
        if (empty($filename)) {
            throw new Exception('The filename of the asset is empty');
        }

        if (substr($sourcePath, -1) != DIRECTORY_SEPARATOR) {
            $sourcePath .= DIRECTORY_SEPARATOR;
        }
        $sourceFile = $sourcePath . $assetName;

        if (!file_exists($sourceFile)) {
            throw new Exception($assetName . ' not found.');
        }
        $asset = null;
        $pathExists = Asset\Service::pathExists($parent->getFullPath() . '/' . $filename);
        if ($pathExists) {
            $isNameInUse = true;
            $asset = Asset::getByPath($parent->getFullPath() . '/' . $filename);
            $assetIsFromObject = false;
            if ($objectId != null) {
                foreach ($asset->getDependencies()->getRequiredBy() as $requiredBy) {
                    if ($requiredBy['type'] == 'object' && $requiredBy['id'] == $objectId) {
                        $assetIsFromObject = true;
                        break;
                    }
                }
            } else {
                $assetIsFromObject = true;
            }
            if ($isReset) {
                // we delete it as we need to know that previews must be rebuilt,
                // if file was the same, no need to overwrite it and regenerate previews
                if ((md5_file($sourceFile) != md5_file($asset->getFileSystemPath())) && $assetIsFromObject) {
                    $asset->delete();
                    $isResetDone = true;
                } else {
                    $isResetAborted = true;
                }
            }
        }

        if (!$asset || $isResetDone) {
            $asset = Asset::create($parent->getId(), [
                "filename" => $filename,
                "sourcePath" => $sourceFile,
                "userModification" => $userModificationId
            ]);
        }


        return [
            'asset' => $asset,
            'isNameInUse' => $isNameInUse,
            'isResetDone' => $isResetDone,
            'isResetAborted' => $isResetAborted
        ];
    }

    /**
     * Remove space for export magento
     *
     * @param $fileName
     *
     * @return string
     */
    public static function getCleanFilename(string $fileName): string
    {
        return preg_replace("/[^A-Za-z0-9._-]/", '_', $fileName);
    }

    /**
     * generate thumb if needed and render thumb url
     *
     * @param Asset\Image $image
     * @param $thumbConfig
     *
     * @return string
     * @throws Exception
     */
    public static function getThumbnailUrl(Asset\Image $image, $thumbConfig = self::MAGENTO_THUMB_PREVIEW_PROFILE_NAME): string
    {
        // @see models/Asset/Image/Thumbnail.php::generate() : error was already caught, therefore we lost the error message, only this file and a log output in cron.log
        $errorImage = PIMCORE_WEB_ROOT . '/bundles/pimcoreadmin/img/filetype-not-supported.svg';
        $result = $image->getThumbnail($thumbConfig, false)->getPath();
        if ($result == $errorImage) {
            throw new Exception('Unable to generate preview, please check the source file');
        }
        $domain = Config::getSystemConfig()->get('general')->get('domain');
        if (!$domain) {
            throw new Exception('Unable to generate preview, missing SystemConfig > website > domain configuration');
        }
        return $domain . $result;
    }

    /**
     *
     * @param Asset\Image $image
     *
     * @return string
     * @throws Exception
     */
    public static function getImageUrl(Asset\Image $image): string
    {

        $domain = Config::getSystemConfig()->get('general')->get('domain');
        if (!$domain) {
            throw new Exception('Unable to generate preview, missing SystemConfig > website > domain configuration');
        }
        return $domain . $image->getFullPath();
    }

    /**
     * @param Asset\Image $image
     *
     * @return bool
     */
    public static function IsPreviewNeeded(Asset\Image $image)
    {
        if (in_array($image->getMimetype(), self::IMAGE_ALLOWED_MIMETYPES_FOR_MAGENTO)
            && ($width = $image->getWidth())
            && ($height = $image->getHeight())
            && $width == $height
            && self::IMAGE_MIN_WIDTH_FOR_MAGENTO <= $width
            && $width <= self::IMAGE_MAX_WIDTH_FOR_MAGENTO
        ) {
            return false;
        }
        return true;
    }

    /**
     * @param string $id
     * @param $assetPath
     * @param $filename
     * @param string $thumbConfig
     *
     * @return bool
     */
    public static function PreviewExist(string $id, $assetPath, $filename, string $thumbConfig = self::MAGENTO_THUMB_PREVIEW_PROFILE_NAME): bool
    {
        return is_file(self::getPreviewFileSystemPath($id, $assetPath, $filename, $thumbConfig));
    }

    /**
     * @param string $id
     * @param $assetPath
     * @param $filename
     * @param string $thumbConfig
     *
     * @return string
     */
    public static function getPreviewFileSystemPath(
        string $id,
        $assetPath,
        $filename,
        string $thumbConfig = self::MAGENTO_THUMB_PREVIEW_PROFILE_NAME
    ): string {
        $info = pathinfo($filename);
        return PIMCORE_WEB_ROOT . '/var/tmp/image-thumbnails' . $assetPath . 'image-thumb__' . $id . '__' . $thumbConfig . '/' . $info['filename'] . (ctype_upper($info['extension']) ? '.' . $info['extension'] : '') . '.jpeg';
    }

}
