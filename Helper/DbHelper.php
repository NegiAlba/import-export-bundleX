<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2017 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Helper;

use Doctrine\DBAL\DBALException;
use Exception;
use Pimcore\Db;
use Pimcore\Db\Connection;
use Pimcore\Model\DataObject\ClassDefinition;

class DbHelper
{

    const OBJECTS_TABLE = 'objects';

    /** @var Connection */
    protected $db;

    protected $class;
    protected $className;
    protected $classId;

    /**
     * DbHelper constructor.
     * @param null $class
     * @param null $className
     */
    public function __construct($class = null, $className = null)
    {
        if ($class && class_exists($class)) {
            $this->class = $class;
            $this->classId = $class::classId();
            $this->className = $className;
        }
        $this->db = Db::get();
    }

    /**
     * @param int $id
     * @param array $data
     * @param bool $updateModificationDate
     */
    public function update(int $id, array $data, bool $updateModificationDate = true)
    {
        if ($updateModificationDate) {
            $this->db->update(static::OBJECTS_TABLE, ['o_modificationDate' => time()], ['o_id' => $id]);
        }
        $this->db->update($this->getQueryTableName(), $data, ['oo_id' => $id]);
        $this->db->update($this->getStoreTableName(), $data, ['oo_id' => $id]);
    }

    /**
     * @param string $key
     * @param string $path
     * @param int $parentId
     *
     * @return int|null
     * @throws DBALException
     */
    public function findOrCreateFolder(string $key, string $path, int $parentId): ?int
    {
        $id = null;
        $sql = sprintf('SELECT o_id as id FROM %s WHERE o_type = \'folder\' AND o_key = \'%s\' AND o_path = \'%s\' LIMIT 1',
            'objects',
            $key,
            $path
        );
        $row = $this->db->fetchRow($sql);
        if ($row) {
            $id = $row['id'];
        } else {
            $id = $this->insertObjectsTable(
                $parentId,
                'folder',
                $key,
                $path,
                time(),
                time(),
                NULL,
                NULL
            );
        }
        return $id;
    }


    /**
     * @param string $columns
     * @param string $key
     * @param string $path
     * @return mixed
     * @throws DBALException
     */
    public function findByPath(string $columns, string $key, string $path)
    {
        $sql = sprintf(
            'SELECT %s FROM %s WHERE o_key = \'%s\' AND o_path = \'%s\' LIMIT 1',
            $columns,
            sprintf('object_%d', $this->classId),
            $key,
            $path
        );
        $row = $this->db->fetchRow($sql);
        return $row;
    }

    /**
     * @param $field
     * @param $value
     * @param $columns
     *
     * @return array | false
     */
    public function findBy($field, $value, $columns)
    {
        $row = false;
        $sql = sprintf(
            'SELECT %s FROM %s WHERE %s = \'%s\' LIMIT 1',
            $columns,
            sprintf('object_%d', $this->classId), // FROM
            $field,
            $value
        );
        try {
            $row = $this->db->fetchRow($sql);
        } catch (DBALException $DBALException) {
            $this->writeError('Une erreur technique est survenue sur le SKU ' . $value . ' : ' . $DBALException->getMessage());
        }
        return $row;
    }

    /**
     * @param int $parentId
     * @param string $type 'object' | 'folder' ...
     * @param string $key
     * @param string $path
     * @param int $creationDate timestamp
     * @param int $modificationDate timestamp
     * @param int $classId
     * @param string $className
     *
     * @return int
     */
    public function insertObjectsTable(
        int $parentId,
        string $type,
        string $key,
        string $path,
        int $creationDate,
        int $modificationDate,
        int $classId = null,
        string $className = null
    ): int
    {
        $data = [
            'o_id' => NULL,
            'o_parentId' => $parentId,
            'o_type' => $type,
            'o_key' => $key,
            'o_path' => $path,
            'o_index' => '0',
            'o_published' => '1',
            'o_creationDate' => $creationDate,
            'o_modificationDate' => $modificationDate,
            'o_userOwner' => 0,
            'o_userModification' => 0,
            'o_classId' => $classId,
            'o_className' => $className,
            'o_childrenSortBy' => NULL
        ];
        $this->db->insert(static::OBJECTS_TABLE, $data);
        return $this->db->lastInsertId();
    }

    /**
     * $data :
     * 'customerId' => $customerId,
     * 'basePrice' => $basePrice,
     * 'netPrice' => $netPrice,
     * 'product__id' => $productId,
     * 'product__type' => 'object'
     *
     * @param int $id
     * @param array $data
     */
    public function insertQueryTable(int $id, array $data)
    {
        $data['oo_classId'] = $this->classId;
        $data['oo_className'] = $this->className;
        $data['oo_id'] = $id;
        $this->db->insert($this->getQueryTableName(), $data);
    }

    /**
     * @param $id
     * @param $data
     */
    public function insertStoreTable(int $id, array $data)
    {
        $data['oo_id'] = $id;
        $this->db->insert($this->getStoreTableName(), $data);
    }

    /**
     * @param $srcId
     * @param $destId
     * @param $fieldname
     * @param string $type
     * @param string $ownertype
     * @param string $ownername
     */
    public function insertRelationsTable(
        $srcId,
        $destId,
        $fieldname,
        $type = 'object',
        $ownertype = 'object',
        $ownername = ''
    )
    {
        $data = [
            'src_id' => $srcId,
            'dest_id' => $destId,
            'type' => $type,
            'fieldname' => $fieldname,
            'index' => 0,
            'ownertype' => $ownertype,
            'ownername' => $ownername,
            'position' => 0
        ];
        $this->db->insert($this->getRelationTableName(), $data);
    }


    public function getCountCreatedFromDate($date)
    {
        $sql = sprintf('SELECT COUNT(*) AS count FROM %s WHERE o_creationDate >= \'%s\'',
            $this->getObjectTableName(), $date);
        return $this->db->fetchRow($sql)['count'];
    }

    public function getCountUpdatedFromDate($date)
    {
        $sqlUpdate = sprintf('SELECT COUNT(*) AS count FROM %s WHERE o_creationDate < \'%s\' AND o_modificationDate >= \'%s\'',
            $this->getObjectTableName(), $date, $date);
        return $this->db->fetchRow($sqlUpdate)['count'];
    }


    public function getObjectTableName()
    {
        return sprintf('object_%d', $this->classId);
    }

    public function getQueryTableName()
    {
        return sprintf('object_query_%d', $this->classId);
    }

    public function getStoreTableName()
    {
        return sprintf('object_store_%d', $this->classId);
    }

    public function getRelationTableName()
    {
        return sprintf('object_relations_%d', $this->classId);
    }
}
