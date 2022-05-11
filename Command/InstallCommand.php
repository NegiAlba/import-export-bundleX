<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2017 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Command;

use Galilee\ImportExportBundle\Installer;
use Pimcore\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Pimcore\Model\DataObject;

class InstallCommand extends AbstractCommand
{

    /**
     * @var Installer\Classes
     */
    private $installerClasses;

    /**
     * @var Installer\ConfigProcessor
     */
    private $installerConfigProcessor;

    /**
     * @var Installer\Data
     */
    private $installerData;

    /**
     * @var Installer\Eiffage
     */
    private $installerEiffage;

    /**
     * @var Installer\ProductWebsites
     */
    private $installerProductWebsites;

    /**
     * @var Installer\StoreView
     */
    private $installerStoreView;

    /**
     * @var Installer\ThumbnailsProfile
     */
    protected $installerThumbnailsProfile;

    /**
     * @var Installer\WebsiteSettings
     */
    protected $installerWebsiteSettings;

    /**
     * @var Installer\Attributes
     */
    protected $updaterAttributes;

    /**
     * @var Installer\EiffageBis
     */
    protected $installerEiffageBis;

    /**
     * @var Installer\Categories
     */
    protected $installerCategories;

    /**
     * @var Installer\EiffageTer
     */
    protected $installerEiffageTer;

    /**
     * InstallCommand constructor.
     *
     * @param Installer\Classes $installerClasses
     * @param Installer\ConfigProcessor $installerConfigProcessor
     * @param Installer\Data $installerData
     * @param Installer\Eiffage $installerEiffage
     * @param Installer\ProductWebsites $installerProductWebsites
     * @param Installer\StoreView $installerStoreView
     * @param Installer\ThumbnailsProfile $installerThumbnailsProfile
     * @param Installer\WebsiteSettings $installerWebsiteSettings
     * @param Installer\Attributes $updaterAttributes
     * @param Installer\EiffageBis $installerEiffageBis
     * @param Installer\Categories $installerCategories
     * @param Installer\EiffageTer $installerEiffageTer
     */
    public function __construct(
        Installer\Classes $installerClasses,
        Installer\ConfigProcessor $installerConfigProcessor,
        Installer\Data $installerData,
        Installer\Eiffage $installerEiffage,
        Installer\ProductWebsites $installerProductWebsites,
        Installer\StoreView $installerStoreView,
        Installer\ThumbnailsProfile $installerThumbnailsProfile,
        Installer\WebsiteSettings $installerWebsiteSettings,
        Installer\Attributes $updaterAttributes,
        Installer\EiffageBis $installerEiffageBis,
        Installer\Categories $installerCategories,
        Installer\EiffageTer $installerEiffageTer
    ) {
        $this->installerClasses = $installerClasses;
        $this->installerConfigProcessor = $installerConfigProcessor;
        $this->installerData = $installerData;
        $this->installerEiffage = $installerEiffage;
        $this->installerProductWebsites = $installerProductWebsites;
        $this->installerStoreView = $installerStoreView;
        $this->installerThumbnailsProfile = $installerThumbnailsProfile;
        $this->installerWebsiteSettings = $installerWebsiteSettings;
        $this->updaterAttributes = $updaterAttributes;
        $this->installerEiffageBis = $installerEiffageBis;
        $this->installerCategories = $installerCategories;
        $this->installerEiffageTer = $installerEiffageTer;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('galilee:install')
            ->setDescription('Installation')
            ->addOption(
                'type', 't',
                InputOption::VALUE_OPTIONAL,
                'Install type : classes, config, data, eiffage, product_websites, store_views'
            )
            ->addOption(
                'limit', 'l',
                InputOption::VALUE_OPTIONAL,
                'Limit for product_websites and store_views'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $type = $input->getOption('type');

        if ($type) {
            switch ($type) {
                case 'classes':
                    $this->installClasses();
                    break;
                case 'config':
                    $this->installConfigProcessors();
                    break;
                case 'data':
                    $this->installData();
                    break;
                case 'eiffage':
                    $this->installEiffage();
                    break;
                case 'product_websites':
                    $this->installProductWebsites($input->getOption('limit'));
                    break;
                case 'store_views':
                    $this->installStoreView($input->getOption('limit'));
                    break;
                case 'website_settings':
                    $this->installWebsiteSettings();
                    break;
                case 'update_attributes':
                    $this->updateAttributes();
                    break;
                case 'eiffage_bis':
                    $this->installEiffageBis();
                    break;
                case 'categories':
                    $this->installCategories();
                    break;
                case 'eiffage_ter':
                    $this->installEiffageTer();
                    break;
            }
        } else {
            $this->installClasses();
            $this->installConfigProcessors();
            $this->installData();
            $this->installThumbnailConfig();
            $this->installWebsiteSettings();
        }
        $this->saveObjectBrick();

    }

    protected function installData()
    {
        $this->installerData->setOutput($this->output);
        $this->installerData->install();
    }

    protected function installConfigProcessors()
    {
        $this->installerConfigProcessor->setOutput($this->output);
        $this->installerConfigProcessor->install();
    }

    protected function installClasses()
    {
        /** @var AbstractCommand $installClassCommand */
        $importClassCommand = $this->getApplication()->find('definition:import:class');
        $this->installerClasses->setImportClassCommand($importClassCommand);
        $this->installerClasses->setOutput($this->output);
        try {
            $this->installerClasses->install();
        } catch (\Exception $e) {
        }
    }

    protected function installEiffage()
    {
        $this->installerEiffage->setOutput($this->output);
        $this->installerEiffage->install();
    }

    protected function installProductWebsites($limit)
    {
        $this->installerProductWebsites->setLimit($limit);
        $this->installerProductWebsites->setOutput($this->output);
        $this->installerProductWebsites->install();
    }

    protected function installStoreView($limit)
    {
        $this->installerStoreView->setLimit($limit);
        $this->installerStoreView->setOutput($this->output);
        $this->installerStoreView->install();
    }

    protected function installThumbnailConfig()
    {
        $this->installerThumbnailsProfile->setOutput($this->output);
        $this->installerThumbnailsProfile->install();
    }

    protected function installWebsiteSettings()
    {
        $this->installerWebsiteSettings->setOutput($this->output);
        $this->installerWebsiteSettings->install();
    }

    protected function updateAttributes()
    {
        $this->updaterAttributes->setOutput($this->output);
        $this->updaterAttributes->install();
    }

    protected function installEiffageBis()
    {
        $this->installerEiffageBis->setOutput($this->output);
        $this->installerEiffageBis->install();
    }

    protected function installCategories()
    {
        $this->installerCategories->setOutput($this->output);
        $this->installerCategories->install();
    }

    protected function installEiffageTer()
    {
        $this->installerEiffageTer->setOutput($this->output);
        $this->installerEiffageTer->install();
    }

    protected function saveObjectBrick()
    {
        $list = new DataObject\Objectbrick\Definition\Listing();
        $list = $list->load();
        foreach ($list as $brick) {
            $brick->save();
        }
    }

    protected function verboseMessage($m)
    {
        if ($this->output->isVerbose()) {
            $this->output->writeln($m);
        }
    }


}
