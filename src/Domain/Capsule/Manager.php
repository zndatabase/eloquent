<?php

namespace ZnDatabase\Eloquent\Domain\Capsule;

use Illuminate\Database\Capsule\Manager as CapsuleManager;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use ZnCore\Arr\Helpers\ArrayHelper;
use ZnDatabase\Base\Domain\Libs\TableAlias;

class Manager extends CapsuleManager
{

    private $tableAlias;
    private $connectionMap = [];

    public function getTableAlias(): TableAlias
    {
        return $this->tableAlias;
    }

    public function setTableAlias(TableAlias $tableAlias): void
    {
        $this->tableAlias = $tableAlias;
    }

    /**
     * @return TableAlias
     * @deprecated
     * @see getTableAlias
     */
    public function getAlias(): TableAlias
    {
        return $this->tableAlias;
    }




    public function getSchemaByConnectionName($connectionName): SchemaBuilder
    {
        //$connection = $this->getConnectionByTableName($tableName);
        $connection = $this->getConnection($connectionName);
        $schema = $connection->getSchemaBuilder();
        return $schema;
    }

    public function getQueryBuilderByConnectionName($connectionName, string $tableNameAlias): QueryBuilder
    {
        $connection = $this->getConnection($connectionName);
        $queryBuilder = $connection->table($tableNameAlias, null);
        return $queryBuilder;
    }

    public function getConnectionByTableName(string $tableName): Connection
    {
        $connectionName = $this->getConnectionNameByTableName($tableName);
        $connection = $this->getConnection($connectionName);
        return $connection;
    }

    public function getSchemaByTableName($tableName): SchemaBuilder
    {
        $connection = $this->getConnectionByTableName($tableName);
        $schema = $connection->getSchemaBuilder();
        return $schema;
    }

    public function getConnectionNames(): array {
        $connections = array_values($this->getConnectionMap());
        $connections[] = 'default';
        $connections = array_unique($connections);
        $connections = array_values($connections);
        return $connections;
    }

    public function isInOneDatabase(string $tableName1, string $tableName2): bool
    {
        return ArrayHelper::getValue($this->connectionMap, $tableName1, 'default') == ArrayHelper::getValue($this->connectionMap, $tableName2, 'default');
    }

    public function getConnectionNameByTableName(string $tableName)
    {
        return ArrayHelper::getValue($this->connectionMap, $tableName, 'default');
    }

    public function getConnectionMap(): array
    {
        return $this->connectionMap;
    }

    public function setConnectionMap($connectionMap): void
    {
        $this->connectionMap = $connectionMap;
    }
}
