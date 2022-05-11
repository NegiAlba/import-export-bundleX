<?php


namespace Galilee\ImportExportBundle\Installer;

use Galilee\ImportExportBundle\Helper\AssetHelper;
use Pimcore\Model\Asset\Image\Thumbnail as PimThumbnail;

class ThumbnailsProfile extends AbstractInstaller
{

    public function install()
    {
        $pipe = PimThumbnail\Config::getByName(AssetHelper::MAGENTO_THUMB_PREVIEW_PROFILE_NAME);
        if (null == $pipe) {
            $pipe = new PimThumbnail\Config();
            $pipe->setName(AssetHelper::MAGENTO_THUMB_PREVIEW_PROFILE_NAME);
            $pipe->addItem('frame', [
                "width" => 2100,
                "height" => 2100,
                "forceResize" => TRUE
            ]);
            $pipe->setFormat('JPEG');
            $pipe->setDownloadable(true);
            $pipe->setQuality(70);
            $pipe->save();
            $this->output->writeln(AssetHelper::MAGENTO_THUMB_PREVIEW_PROFILE_NAME . ' thumb profile successfully created.');

        }
    }
}