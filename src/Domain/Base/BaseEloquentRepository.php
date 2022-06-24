<?php

namespace ZnDatabase\Eloquent\Domain\Base;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use ZnCore\Base\Develop\Helpers\DeprecateHelper;
use ZnCore\Base\EventDispatcher\Traits\EventDispatcherTrait;
use ZnCore\Domain\Domain\Enums\EventEnum;
use ZnCore\Domain\Domain\Events\EntityEvent;
use ZnCore\Domain\Domain\Events\QueryEvent;
use ZnCore\Domain\Domain\Interfaces\GetEntityClassInterface;
use ZnCore\Domain\EntityManager\Interfaces\EntityManagerInterface;
use ZnCore\Domain\Query\Entities\Query;
use ZnCore\Domain\EntityManager\Traits\EntityManagerAwareTrait;
use ZnCore\Domain\Repository\Traits\RepositoryDispatchEventTrait;
use ZnCore\Domain\Repository\Traits\RepositoryQueryTrait;
use ZnDatabase\Eloquent\Domain\Capsule\Manager;
use ZnDatabase\Eloquent\Domain\Helpers\QueryBuilder\EloquentQueryBuilderHelper;
use ZnDatabase\Eloquent\Domain\Traits\EloquentTrait;
use ZnCore\Domain\Repository\Traits\MapperTrait;
use ZnDatabase\Base\Domain\Traits\TableNameTrait;

abstract class BaseEloquentRepository implements GetEntityClassInterface
{

//    use EventDispatcherTrait;
    use EloquentTrait;
    use TableNameTrait;
    use EntityManagerAwareTrait;
    use MapperTrait;
    use RepositoryDispatchEventTrait;
    use RepositoryQueryTrait;

//    protected $autoIncrement = 'id';
    //private $entityClassName;

    public function __construct(EntityManagerInterface $em, Manager $capsule)
    {
        $this->setCapsule($capsule);
        $this->setEntityManager($em);
    }

    /*public function getEntityClass(): string
    {
        return $this->entityClassName;
    }*/

    /*protected function forgeQuery(Query $query = null): Query
    {
        $query = Query::forge($query);
        $this->dispatchQueryEvent($query, EventEnum::BEFORE_FORGE_QUERY);
        return $query;
    }*/

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
    }

    protected function findBy(Query $query = null): Enumerable
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
        $collection = $this->findByBuilder($queryBuilder);
        return $collection;
    }

    protected function findByBuilder(QueryBuilder $queryBuilder): Collection
    {
        $postCollection = $queryBuilder->get();
        $array = $postCollection->toArray();
        $collection = $this->mapperDecodeCollection($array);
        return $collection;
    }
}
