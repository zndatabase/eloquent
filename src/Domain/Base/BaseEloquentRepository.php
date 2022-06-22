<?php

namespace ZnDatabase\Eloquent\Domain\Base;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Illuminate\Support\Collection;
use ZnCore\Base\Helpers\DeprecateHelper;
use ZnCore\Base\Libs\EventDispatcher\Traits\EventDispatcherTrait;
use ZnCore\Domain\Domain\Enums\EventEnum;
use ZnCore\Domain\Domain\Events\EntityEvent;
use ZnCore\Domain\Domain\Events\QueryEvent;
use ZnCore\Domain\Domain\Interfaces\GetEntityClassInterface;
use ZnCore\Domain\EntityManager\Interfaces\EntityManagerInterface;
use ZnCore\Domain\Query\Entities\Query;
use ZnCore\Domain\EntityManager\Traits\EntityManagerAwareTrait;
use ZnDatabase\Eloquent\Domain\Capsule\Manager;
use ZnDatabase\Eloquent\Domain\Helpers\QueryBuilder\EloquentQueryBuilderHelper;
use ZnDatabase\Eloquent\Domain\Traits\EloquentTrait;
use ZnCore\Domain\Repository\Traits\MapperTrait;
use ZnDatabase\Base\Domain\Traits\TableNameTrait;

abstract class BaseEloquentRepository implements GetEntityClassInterface
{

    use EventDispatcherTrait;
    use EloquentTrait;
    use TableNameTrait;
    use EntityManagerAwareTrait;
    use MapperTrait;

//    protected $autoIncrement = 'id';
    private $entityClassName;

    public function __construct(EntityManagerInterface $em, Manager $capsule)
    {
        $this->setCapsule($capsule);
        $this->setEntityManager($em);
    }

    /*public function autoIncrement()
    {
        return $this->autoIncrement;
    }*/

    /*public function getConnection(): Connection
    {
        $connection = $this->capsule->getConnection($this->connectionName());
        return $connection;
    }*/

    /**
     * @param Query|null $query
     * @return Query
     */
    protected function forgeQuery(Query $query = null): Query
    {
        $query = Query::forge($query);
        $this->dispatchQueryEvent($query, EventEnum::BEFORE_FORGE_QUERY);
        return $query;
    }

    protected function forgeQueryBuilder(QueryBuilder $queryBuilder, Query $query)
    {
//        $queryBuilder = $queryBuilder ?? $this->getQueryBuilder();
        EloquentQueryBuilderHelper::setWhere($query, $queryBuilder);
        EloquentQueryBuilderHelper::setJoin($query, $queryBuilder);
//        return
    }

    protected function getQueryBuilder(): QueryBuilder
    {
        return $this->getQueryBuilderByTableName($this->tableName());
//        $connection = $this->getConnection();
//        $queryBuilder = $connection->table($this->tableNameAlias(), null);
//        return $queryBuilder;
    }

    /*protected function getSchema(): SchemaBuilder
    {
        $connection = $this->getConnection();
        $schema = $connection->getSchemaBuilder();
        return $schema;
    }*/

    /*function getAttributeMap(): array {
        return [

        ];
    }*/

    protected function findBy(Query $query = null)
    {
        $query = $this->forgeQuery($query);
        $queryBuilder = $this->getQueryBuilder();
        $this->forgeQueryBuilder($queryBuilder, $query);
        $query->select([$queryBuilder->from . '.*']);
//        EloquentQueryBuilderHelper::setWhere($query, $queryBuilder);
//        EloquentQueryBuilderHelper::setJoin($query, $queryBuilder);
        EloquentQueryBuilderHelper::setSelect($query, $queryBuilder);
        EloquentQueryBuilderHelper::setOrder($query, $queryBuilder);
        EloquentQueryBuilderHelper::setGroupBy($query, $queryBuilder);
        EloquentQueryBuilderHelper::setPaginate($query, $queryBuilder);
        $collection = $this->allByBuilder($queryBuilder);
        return $collection;
    }

    protected function allByBuilder(QueryBuilder $queryBuilder): Collection
    {
        $postCollection = $queryBuilder->get();
        $array = $postCollection->toArray();
        $collection = $this->mapperDecodeCollection($array);
        return $collection;
    }

    public function getEntityClass(): string
    {
        return $this->entityClassName;
    }

    protected function dispatchQueryEvent(Query $query, string $eventName): QueryEvent
    {
        $event = new QueryEvent($query);
        $this->getEventDispatcher()->dispatch($event, $eventName);
        return $event;
    }

    protected function dispatchEntityEvent(object $entity, string $eventName): EntityEvent
    {
        $event = new EntityEvent($entity);
        $this->getEventDispatcher()->dispatch($event, $eventName);
        return $event;
    }

    /*protected function oneByBuilder(QueryBuilder $queryBuilder)
    {
        $item = $queryBuilder->first();
        if (empty($item)) {
            throw new NotFoundException('Not found entity!');
        }
        return $this->forgeEntity($item);
    }*/

}
