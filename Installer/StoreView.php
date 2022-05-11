<?php

namespace Galilee\ImportExportBundle\Installer;

use Pimcore\Db\Connection;
use Pimcore\Model\DataObject;

class StoreView extends AbstractInstaller
{

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var int
     */
    protected $limit;

    /**
     * StoreView constructor.
     *
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @throws \Exception
     */
    public function install()
    {
        /** @var DataObject\Website $baseWebsite */
        $baseWebsite = DataObject\Website::getByCode('base', 1);
        if ($baseWebsite != null) {
            $code = 'default';
            $defaultStoreView = DataObject\StoreView::getByPath($baseWebsite->getFullPath() . DIRECTORY_SEPARATOR . $code);
            if ($defaultStoreView == null) {
                $defaultStoreView = new DataObject\StoreView();
                $defaultStoreView->setCode($code);
                $defaultStoreView->setKey($code);
                $defaultStoreView->setParent($baseWebsite);
                $defaultStoreView->setPublished(true);
                $defaultStoreView->save();
            }

            $this->output->writeln('Set StoreView of Website base...');
            $relation = new DataObject\Data\ObjectMetadata('website', [], $baseWebsite);
            $defaultStoreView->setWebsite([$relation]);
            $defaultStoreView->save();
            $this->output->writeln('Store View Added.');

            $this->output->writeln("Set StoreView of Products...");
            $list = new DataObject\Product\Listing();
            $list->setCondition("storeViews IS NULL");
            if ($this->limit) {
                $list->setLimit($this->limit);
            }
            $list->setUnpublished(1);
            $list = $list->load();

            $total = count($list);
            $i = 1;
            foreach ($list as $product) {
                $relation = new DataObject\Data\ObjectMetadata('storeViews', [], $defaultStoreView);
                $product->setStoreViews([$relation]);
                $product->save();
                $this->output->writeln(sprintf('Product updated %d/%d', $i, $total));
                $i++;
            }

            $this->output->writeln('Product left=' . $this->connection->fetchRow(sprintf("SELECT COUNT(oo_id) as count FROM object_%d WHERE storeViews IS NULL",
                    DataObject\Product::classId()))['count']);
        } else {
            $this->output->writeln('No Website with code base.');
        }
    }

    /**
     * @param $limit
     */
    public function setLimit($limit)
    {
        $this->limit = (int)$limit;
    }
}
