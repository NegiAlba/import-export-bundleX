<?php


namespace Galilee\ImportExportBundle\Command;

use Galilee\ImportExportBundle\Helper\AssetHelper;
use Galilee\ImportExportBundle\Helper\DbHelper;
use Pimcore\Console\AbstractCommand;
use Pimcore\Db;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;


class GeneratePreviewCommand extends AbstractCommand
{

    const DEFAULT_PREVIEW_PROFILE = 'ecom_magento';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('socoda:preview:generate')
            ->setDescription('Generate Product images previews')
            ->addOption(
                'profile', 'p',
                InputOption::VALUE_OPTIONAL,
                'default profile is ' . AssetHelper::MAGENTO_THUMB_PREVIEW_PROFILE_NAME
            )
            ->addOption(
                'offset', 'o',
                InputOption::VALUE_OPTIONAL,
                'Offset for number of assets'
            )
            ->addOption(
                'limit', 'l',
                InputOption::VALUE_OPTIONAL,
                'Limit for number of assets'
            )->addArgument(
                'action',
                InputArgument::OPTIONAL,
                'Availables actions: (\'reset_image\'');
        parent::configure();
    }

    /**
     * {@inheritdoc}
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!($offset = $input->getOption('offset'))) {
            $offset = null;
        }
        if (!($limit = $input->getOption('limit'))) {
            $limit = null;
        }
        if (!($profile = $input->getOption('profile'))) {
            $profile = AssetHelper::MAGENTO_THUMB_PREVIEW_PROFILE_NAME;
        }

        if (!Asset\Image\Thumbnail\Config::getByAutoDetect($profile)) {
            throw new \Exception('The thumbnail profile "' . $profile . '" does not exist');
        }

        $action = $input->getArgument('action');
        if ($action && $action != 'reset_images') {
            throw new \Exception('Unsupported action : ' . $action);
        }

        $t1 = microtime(true);
        $list = $this->createList($limit, $offset);
        $i = 0;
        $count = count($list);
        /** @var Asset\Image $asset */
        foreach ($list as $asset) {
            $i++;
            try {
                if (AssetHelper::IsPreviewNeeded($asset)) {
                    $t2 = microtime(true);
                    if (method_exists($asset, 'getThumbnail')) {
                        $thumb = $asset->getThumbnail($profile, false);
                        // need to call path to generate the thumb
                        $thumbPath = $thumb->getFileSystemPath();
                        $duration2 = microtime(true) - $t2;
                        if ($this->output->isVerbose()) {
                            $this->output->writeln(sprintf('>>> preview generated in %f secondes [%d/%d]', $duration2, $i, $count));
                            $this->output->writeln(sprintf(' -asset: %s' . PHP_EOL . ' -preview: %s ', $asset->getFullPath(), $thumbPath));
                        }

                        if ($action == 'reset_images') {
                            $t3 = microtime(true);
                            $productList = $this->getAssetProduct($asset->getId());
                            $dbHelper = new DbHelper(DataObject\Product::class, 'Product');
                            foreach ($productList as $productData) {
                                $dbHelper->update($productData['oo_id'], ['resetImages' => 1], true);
                                if ($this->output->isVerbose()) {
                                    $this->output->writeln(sprintf(' -product: %s', $productData['o_path'] . $productData['o_key']));
                                }
                                $duration3 = microtime(true) - $t3;
                                if ($this->output->isVeryVerbose()) {
                                    $this->output->writeln(sprintf('  -> product updated as reset_images true in %f secondes', $duration3));
                                }
                            }
                        }
                    }
                } else {
                    if ($this->output->isVerbose()) {
                        $this->output->writeln(sprintf('>>> No preview needed on this one, format and size are OK [%d/%d]', $i, $count));
                        $this->output->writeln(sprintf(' -asset: %s', $asset->getFullPath()));
                    }
                }
                if ($this->output->isVeryVerbose()) {
                    $this->output->writeln(sprintf(' -mimeType: %s' . PHP_EOL . ' -width: %d ' . PHP_EOL . ' -height: %d ', $asset->getMimetype(), $asset->getWidth(), $asset->getHeight()));
                }
            } catch (\Exception $e) {
                $this->output->writeln(sprintf('ERROR : Something went wrong on [%d/%d]' . PHP_EOL . ' -> %s', $asset->getFullPath(), $i, $count, $e->getMessage()));
            }
        }

        if ($this->output->isVerbose()) {
            $duration = microtime(true) - $t1;
            $this->output->writeln(sprintf(">> batch generated in %f secondes", $duration));
        }

        $offset = $offset + $limit;
        $list = $this->createList($limit, $offset);
        // echo is for split cli in bash script
        echo ($count = count($list->load())) == 0 ? $count . "\n" : $offset . "\n";

    }

    /**
     * @param null $limit
     * @param null $offset
     *
     * @return Asset\Listing
     */
    protected function createList($limit = null, $offset = null)
    {
        $list = new Asset\Listing();
        if ($limit) {
            $list->setLimit($limit);
        }
        if ($offset) {
            $list->setOffset($offset);
        }
        $list->setOrderKey('id');
        $list->setOrder('ASC');
        $list->setCondition('path LIKE ' . $list->quote('%/product%'));
        $list->addConditionParam('type = ?', 'image', 'AND');
        return $list;
    }

    /**
     * @param $assetId
     * @return mixed
     */
    public function getAssetProduct($assetId)
    {
        $productTable = sprintf('object_%d', DataObject\Product::classId());
        $sql = sprintf(
            'SELECT' .
            ' *' .
            ' FROM %s' .
            ' WHERE IFNULL(resetImages, 0) = 0 AND images LIKE "%s"',
            $productTable, '%asset|' . $assetId . ',%');

        if ($this->output->isVeryVerbose()) {
            $this->output->writeln(sprintf(">> SQL : %s", $sql));
        }
        return Db::get()->fetchAll($sql);
    }
}
