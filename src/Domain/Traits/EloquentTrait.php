<?php

namespace ZnDatabase\Eloquent\Domain\Traits;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use ZnDatabase\Eloquent\Domain\Capsule\Manager;
use ZnDatabase\Base\Domain\Libs\TableAlias;

trait EloquentTrait
{

    private $capsule;

    abstract public function connectionName();

    public function setCapsule(Manager $capsule): void
    {
        $this->capsule = $capsule;
    }

    public function getCapsule(): Manager
    {
        return $this->capsule;
    }

    public function getConnection(string $connectionName = null): Connection
    {
        $connectionName = $connectionName ?: $this->connectionName();
        return $this
            ->getCapsule()
            ->getConnection($connectionName);
    }

    protected function getSchema(string $connectionName = null): SchemaBuilder
    {
        return $this
            ->getConnection($connectionName)
            ->getSchemaBuilder();
    }

    /*public function encodeTableName(string $name, string $connectionName = null): string {
        $connectionName = $connectionName ?: $this->connectionName();
        return $this->getAlias()->encode($connectionName, $name);
    }*/

    public function getQueryBuilderByTableName(string $name): QueryBuilder
    {
        $targetTableName = $this->encodeTableName($name);
        $connection = $this->getCapsule()->getConnectionByTableName($name);
        return $connection->table($targetTableName);
    }

    /*public function getConnectionByTableName(string $name) {
        return $this->getCapsule()->getConnectionByTableName($name);
    }*/
}
