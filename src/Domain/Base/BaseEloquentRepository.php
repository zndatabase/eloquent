<?php

namespace ZnDatabase\Eloquent\Domain\Base;

use Illuminate\Database\Query\Builder as QueryBuilder;
use ZnCore\Domain\Collection\Libs\Collection;
use ZnCore\Domain\Collection\Interfaces\Enumerable;
use ZnCore\Domain\Domain\Interfaces\GetEntityClassInterface;
use ZnCore\Domain\Domain\Traits\DispatchEventTrait;
use ZnCore\Domain\Domain\Traits\ForgeQueryTrait;
use ZnCore\Domain\EntityManager\Interfaces\EntityManagerInterface;
use ZnCore\Domain\EntityManager\Traits\EntityManagerAwareTrait;
use ZnCore\Domain\Query\Entities\Query;
use ZnCore\Domain\Repository\Traits\RepositoryMapperTrait;
use ZnCore\Domain\Repository\Traits\RepositoryDispatchEventTrait;
use ZnCore\Domain\Repository\Traits\RepositoryQueryTrait;
use ZnDatabase\Base\Domain\Traits\TableNameTrait;
use ZnDatabase\Eloquent\Domain\Capsule\Manager;
use ZnDatabase\Eloquent\Domain\Helpers\QueryBuilder\EloquentQueryBuilderHelper;
use ZnDatabase\Eloquent\Domain\Traits\EloquentTrait;

abstract class BaseEloquentRepository implements GetEntityClassInterface
{

    use EloquentTrait;
    use TableNameTrait;
    use EntityManagerAwareTrait;
    use RepositoryMapperTrait;
    use DispatchEventTrait;
    use ForgeQueryTrait;

    public function __construct(EntityManagerInterface $em, Manager $capsule)
    {
        $this->setCapsule($capsule);
        $this->setEntityManager($em);
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
