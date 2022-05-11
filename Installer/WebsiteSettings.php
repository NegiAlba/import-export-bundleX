<?php

namespace Galilee\ImportExportBundle\Installer;

use Pimcore\Model\WebsiteSetting;

class WebsiteSettings extends AbstractInstaller
{

    public function install()
    {
        $websiteSetting = WebsiteSetting::getByName('clean_html_on_export');
        if ($websiteSetting == null) {
            $websiteSetting = new WebsiteSetting();
            $websiteSetting->setType('bool');
            $websiteSetting->setName('clean_html_on_export');
            $websiteSetting->setData(0);
            $websiteSetting->save();
            $this->output->writeln('The website setting clean_html_on_export has been succefully created.');
        }

        $disableSort = WebsiteSetting::getByName('disable_sort');
        if ($disableSort == null) {
            $disableSort = new WebsiteSetting();
            $disableSort->setType('bool');
            $disableSort->setName('disable_sort');
            $disableSort->setData("1");
            $disableSort->save();
            $this->output->writeln('The website setting disable_sort has been succefully created.');
        }
        $childProductVisibility = WebsiteSetting::getByName('child_product_visibility');
        if ($childProductVisibility == null) {
            $childProductVisibility = new WebsiteSetting();
            $childProductVisibility->setType('text');
            $childProductVisibility->setName('child_product_visibility');
            $childProductVisibility->setData("3");
            $childProductVisibility->save();
            $this->output->writeln('The website setting child_product_visibility has been succefully created.');
        }
    }
}
