<?php

namespace Galilee\ImportExportBundle\Installer;

use Pimcore\Db\Connection;
use Pimcore\Model\DataObject;

class ProductWebsites extends AbstractInstaller
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
     * ProductWebsites constructor.
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
        $parentFolderKey = 'websites';
        $code = 'base';
        $baseWebsite = DataObject\Website::getByPath(DIRECTORY_SEPARATOR . $parentFolderKey . DIRECTORY_SEPARATOR . $code);
        if ($baseWebsite == null) {
            $parentFolder = DataObject\Service::createFolderByPath($parentFolderKey);
            $baseWebsite = new DataObject\Website();
            $baseWebsite->setCode($code);
            $baseWebsite->setKey($code);
            $baseWebsite->setParent($parentFolder);
            $baseWebsite->setPublished(true);
            $baseWebsite->save();
        }

        $this->output->writeln("Set Website of Products...");
        $list = new DataObject\Product\Listing();
        $list->setCondition('websites IS NULL');
        if ($this->limit) {
            $list->setLimit($this->limit);
        }
        $list->setUnpublished(1);
        $list = $list->load();

        $total = count($list);
        $i = 1;
        foreach ($list as $product) {
            $relation = new DataObject\Data\ObjectMetadata('websites', [], $baseWebsite);
            $product->setWebsites([$relation]);
            $product->save();
            $this->output->writeln(sprintf('Product updated %d/%d', $i, $total));
            $i++;
        }

        $this->output->writeln('Product left=' . $this->connection->fetchRow(sprintf("SELECT COUNT(oo_id) as count FROM object_%d WHERE websites IS NULL",
                DataObject\Product::classId()))['count']);
    }

    /**
     * @param $limit
     */
    public function setLimit($limit)
    {
        $this->limit = (int)$limit;
    }
}
