<?php

namespace ZnDatabase\Eloquent\Domain\Factories;

use ZnCore\Base\Arr\Helpers\ArrayHelper;
use ZnCore\Base\FileSystem\Helpers\FileStorageHelper;
use ZnCore\Base\Store\Helpers\StoreHelper;
use ZnDatabase\Base\Domain\Enums\DbDriverEnum;
use ZnDatabase\Base\Domain\Facades\DbFacade;
use ZnDatabase\Base\Domain\Libs\TableAlias;
use ZnDatabase\Eloquent\Domain\Capsule\Manager;
use ZnDatabase\Eloquent\Domain\Libs\ConfigBuilders\EloquentConfigBuilder;

class ManagerFactory
{

    public static function createManagerFromEnv(): Manager
    {
        $connections = DbFacade::getConfigFromEnv();
        return self::createManagerFromConnections($connections);
    }

    public static function createManagerFromConnections(array $connections): Manager
    {
        $config = StoreHelper::load($_ENV['ROOT_DIRECTORY'] . '/' . $_ENV['ELOQUENT_CONFIG_FILE']);
//        $config = LoadHelper::loadConfig($_ENV['ELOQUENT_CONFIG_FILE']);
        $connectionMap = ArrayHelper::getValue($config, 'connection.connectionMap', []);

        $map = ArrayHelper::getValue($config, 'connection.map', []);
        $tableAlias = self::createTableAlias($connections, $map);
        $capsule = new Manager;
        $capsule->setConnectionMap($connectionMap);
        $capsule->setTableAlias($tableAlias);

        self::touchSqlite($connections);

        foreach ($connections as $connectionName => $connection) {
            $capsule->addConnection(EloquentConfigBuilder::build($connection), $connectionName);
        }
        return $capsule;
    }

    public static function touchSqlite(array $connections)
    {
        foreach ($connections as $connectionName => $connectionConfig) {
            if ($connectionConfig['driver'] == DbDriverEnum::SQLITE) {
                FileStorageHelper::touchFile($connectionConfig['database']);
            }
        }
    }

    public static function createTableAlias(array $connections, array $configMap): TableAlias
    {
        $tableAlias = new TableAlias;
        foreach ($connections as $connectionName => $connectionConfig) {
            if (!isset($connectionConfig['map'])) {
                $connectionConfig['map'] = $configMap;
            }
            $map = ArrayHelper::getValue($connectionConfig, 'map', []);
            if ($connectionConfig['driver'] !== DbDriverEnum::PGSQL) {
                foreach ($map as $from => &$to) {
                    $to = str_replace('.', '_', $to);
                }
            }
            $tableAlias->addMap($connectionName, $map);
        }
        return $tableAlias;
    }
}