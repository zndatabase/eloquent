<?php

namespace ZnDatabase\Eloquent\Domain\Orm;

use ZnDomain\EntityManager\Interfaces\OrmInterface;
use ZnDatabase\Eloquent\Domain\Capsule\Manager;

class EloquentOrm implements OrmInterface
{

    private $connection;

    public function __construct(Manager $connection)
    {
        $this->connection = $connection;
    }

    public function beginTransaction()
    {
        $this->connection->getDatabaseManager()->beginTransaction();
    }

    public function rollbackTransaction()
    {
        $this->connection->getDatabaseManager()->rollBack();
    }

    public function commitTransaction()
    {
        $this->connection->getDatabaseManager()->commit();
    }
}
