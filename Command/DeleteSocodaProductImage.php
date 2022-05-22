<?php

namespace Galilee\ImportExportBundle\Command;

use Pimcore\Model\DataObject;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteSocodaProductImage extends \Pimcore\Console\AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('socoda:catalog:delete_socoda_product_images')
            ->setDescription('Delete Product images Socoda')
            ->addOption(
                'offset', 'o',
                InputOption::VALUE_OPTIONAL,
                'Offset for number of products'
            )
            ->addOption(
                'limit', 'l',
                InputOption::VALUE_OPTIONAL,
                'Limit for number of products'
            );
        parent::configure();
    }

    /**
     * {@inheritdoc}
     *
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
        $list = $this->createList($limit, $offset);
        foreach ($list as $product) {
            $images = $product->getImages();
            if (is_array($images)) {
                foreach ($images as $image) {
                    $image->delete();
                }
                $product->setImages([]);
                $product->save();
            }
        }

        $offset = $offset + $limit;
        $list = $this->createList($limit, $offset);
        echo($count = $list->getCount()) == 0 ? $count."\n" : $offset."\n";

        return 0;
    }

    /**
     * @param null $limit
     * @param null $offset
     *
     * @return DataObject\Product\Listing
     *
     * @throws \Exception
     */
    protected function createList($limit = null, $offset = null)
    {
        $list = new DataObject\Product\Listing();
        $list->setLimit($limit);
        $list->setOffset($offset);
        $list->setOrderKey('oo_id');
        $list->setOrder('ASC');
        $list->addConditionParam('idSocoda IS NOT NULL');
        $list->addConditionParam("statusImportSocoda IN ('1','2','3')");

        return $list;
    }
}