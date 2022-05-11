<?php

namespace Galilee\ImportExportBundle\Command;

use Galilee\ImportExportBundle\Uninstaller;
use Pimcore\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UninstallCommand extends AbstractCommand
{

    /**
     * @var Uninstaller\Prices
     */
    protected $uninstallerPrices;

    /**
     * @var Uninstaller\BrandName
     */
    protected $uninstallerBrandName;

    /**
     * @var Uninstaller\Stocks
     */
    protected $uninstallerStocks;

    /**
     * UninstallCommand constructor.
     *
     * @param Uninstaller\Prices $uninstallerPrices
     * @param Uninstaller\BrandName $uninstallerBrandName
     * @param Uninstaller\Stocks $uninstallerStocks
     */
    public function __construct(
        Uninstaller\Prices $uninstallerPrices,
        Uninstaller\BrandName $uninstallerBrandName,
        Uninstaller\Stocks $uninstallerStocks
    ) {
        $this->uninstallerPrices = $uninstallerPrices;
        $this->uninstallerBrandName = $uninstallerBrandName;
        $this->uninstallerStocks = $uninstallerStocks;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('galilee:uninstall')
            ->setDescription('Uninstallation')
            ->addOption(
                'type', 't',
                InputOption::VALUE_REQUIRED,
                'Install type : prices'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $type = $input->getOption('type');

        switch ($type) {
            case 'prices':
                $this->uninstallPrices();
                break;
            case 'brand_name':
                $this->uninstallBrandName();
                break;
            case 'stocks':
                $this->uninstallStocks();
                break;
        }
    }

    protected function uninstallPrices()
    {
        $this->uninstallerPrices->setOutput($this->output);
        $this->uninstallerPrices->uninstall();
    }

    protected function uninstallBrandName()
    {
        $this->uninstallerBrandName->setOutput($this->output);
        $this->uninstallerBrandName->uninstall();
    }

    protected function uninstallStocks()
    {
        $this->uninstallerStocks->setOutput($this->output);
        $this->uninstallerStocks->uninstall();
    }
}
