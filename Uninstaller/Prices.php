<?php

namespace Galilee\ImportExportBundle\Uninstaller;

use Pimcore\Db\Connection;
use Pimcore\Model\DataObject;

class Prices extends AbstractUninstaller
{
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * Prices constructor.
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
    public function uninstall()
    {
        $classId = (new DataObject\Price())->getClassId();
        $className = (new DataObject\Price())->getClassName();

        $this->output->writeln("Deleting class...");
        $sql = sprintf("DELETE FROM classes WHERE id='%d'", $classId);
        $this->connection->executeQuery($sql);

        $this->output->writeln("Deleting objects...");
        $sql = sprintf("DELETE FROM objects WHERE o_classId='%d'", $classId);
        $this->connection->executeQuery($sql);

        $this->output->writeln("Deleting folders...");
        $sql = sprintf("DELETE FROM objects WHERE o_type='folder' AND o_key='customer-price'");
        $this->connection->executeQuery($sql);

        $this->output->writeln("Deleting tables...");
        $sql = sprintf("DROP TABLE IF EXISTS object_query_%d", $classId);
        $this->connection->executeQuery($sql);

        $sql = sprintf("DROP TABLE IF EXISTS object_store_%d", $classId);
        $this->connection->executeQuery($sql);

        $sql = sprintf("DROP TABLE IF EXISTS object_relations_%d", $classId);
        $this->connection->executeQuery($sql);

        $this->output->writeln("Deleting view...");
        $sql = sprintf("DROP VIEW IF EXISTS object_%d", $classId);
        $this->connection->executeQuery($sql);

        $this->output->writeln("Deleting files...");
        exec(sprintf('rm -rf var/classes/definition_%1$s.php var/classes/DataObject/%1$s var/classes/DataObject/%1$s.php',
            $className));
    }
}
