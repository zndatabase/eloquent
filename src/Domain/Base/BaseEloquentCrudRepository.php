<?php

namespace ZnDatabase\Eloquent\Domain\Base;

use Illuminate\Database\QueryException;
use ZnCore\Domain\Collection\Interfaces\Enumerable;
use ZnCore\Domain\Collection\Libs\Collection;
use ZnCore\Base\Arr\Helpers\ArrayHelper;
use ZnCore\Base\Text\Helpers\Inflector;
use ZnCore\Base\Text\Helpers\TextHelper;
use ZnCore\Base\Validation\Exceptions\UnprocessibleEntityException;
use ZnCore\Base\Validation\Helpers\ValidationHelper;
use ZnCore\Domain\Domain\Enums\EventEnum;
use ZnCore\Domain\Domain\Events\QueryEvent;
use ZnCore\Domain\Entity\Helpers\EntityHelper;
use ZnCore\Domain\Query\Entities\Query;
use ZnCore\Domain\Query\Enums\OperatorEnum;
use ZnCore\Domain\QueryFilter\Helpers\FilterModelHelper;
use ZnCore\Domain\QueryFilter\Interfaces\ForgeQueryByFilterInterface;
use ZnCore\Domain\Repository\Interfaces\CrudRepositoryInterface;
use ZnCore\Domain\Repository\Interfaces\FindOneUniqueInterface;
use ZnCore\Domain\Repository\Traits\CrudRepositoryDeleteTrait;
use ZnCore\Domain\Repository\Traits\CrudRepositoryFindAllTrait;
use ZnCore\Domain\Repository\Traits\CrudRepositoryFindOneTrait;
use ZnCore\Domain\Repository\Traits\CrudRepositoryInsertTrait;
use ZnCore\Domain\Repository\Traits\CrudRepositoryUpdateTrait;
use ZnCore\Domain\Repository\Traits\RepositoryRelationTrait;
use ZnDatabase\Eloquent\Domain\Helpers\QueryBuilder\EloquentQueryBuilderHelper;

abstract class BaseEloquentCrudRepository extends BaseEloquentRepository implements CrudRepositoryInterface, ForgeQueryByFilterInterface, FindOneUniqueInterface
{

    use CrudRepositoryFindOneTrait;
    use CrudRepositoryFindAllTrait;
    use CrudRepositoryInsertTrait;
    use CrudRepositoryUpdateTrait;
    use CrudRepositoryDeleteTrait;
    use RepositoryRelationTrait;

    /*public function primaryKey()
    {
        return $this->primaryKey;
    }*/

    public function forgeQueryByFilter(object $filterModel, Query $query)
    {
        FilterModelHelper::validate($filterModel);
        FilterModelHelper::forgeOrder($query, $filterModel);
        $query = $this->forgeQuery($query);
        $event = new QueryEvent($query);
        $event->setFilterModel($filterModel);
        $this
            ->getEventDispatcher()
            ->dispatch($event, EventEnum::BEFORE_FORGE_QUERY_BY_FILTER);
        $schema = $this->getSchema();
        $columnList = $schema->getColumnListing($this->tableNameAlias());
        FilterModelHelper::forgeCondition($query, $filterModel, $columnList);
    }

    public function count(Query $query = null): int
    {
        $query = $this->forgeQuery($query);
        $queryBuilder = $this->getQueryBuilder();
        $this->forgeQueryBuilder($queryBuilder, $query);
        return $queryBuilder->count();
    }

    protected function insertRaw($entity): void
    {
        $arraySnakeCase = $this->mapperEncodeEntity($entity);
        try {
            $lastId = $this->getQueryBuilder()->insertGetId($arraySnakeCase);
            $entity->setId($lastId);
        } catch (QueryException $e) {
            $errors = new UnprocessibleEntityException;
            $this->checkExists($entity);
            if ($_ENV['APP_DEBUG']) {
                $message = $e->getMessage();
                $message = TextHelper::removeDoubleSpace($message);
                $message = str_replace("'", "\\'", $message);
                $message = trim($message);
            } else {
                $message = 'Database error!';
            }
            $errors->add('', $message);
            throw $errors;
        }
    }

    public function createCollection(Enumerable $collection)
    {
        $array = [];
        foreach ($collection as $entity) {
            ValidationHelper::validateEntity($entity);
            $columnList = $this->getColumnsForModify();
            $array[] = EntityHelper::toArrayForTablize($entity, $columnList);
        }
//        $this->getQueryBuilder()->insert($array);
        $this->getQueryBuilder()->insertOrIgnore($array);
    }

    protected function getColumnsForModify()
    {
        $columnList = $this->getSchema()->getColumnListing($this->tableNameAlias());
        if (empty($columnList)) {
            $columnList = EntityHelper::getAttributeNames($this->getEntityClass());
            foreach ($columnList as &$item) {
                $item = Inflector::underscore($item);
            }
        }
        if (in_array('id', $columnList)) {
            ArrayHelper::removeByValue('id', $columnList);
        }
        return $columnList;
    }

    protected function allBySql(string $sql, array $binds = [])
    {
        return $this->getConnection()
            ->createCommand($sql, $binds)
            ->queryAll(\PDO::FETCH_CLASS);
    }

    private function updateQuery($id, array $data)
    {
        $columnList = $this->getColumnsForModify();
        $data = ArrayHelper::extractByKeys($data, $columnList);
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->find($id);
        $queryBuilder->update($data);
    }

    protected function deleteByIdQuery($id)
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->delete($id);
    }

    public function updateByQuery(Query $query, array $values)
    {
        $query = $this->forgeQuery($query);
        $queryBuilder = $this->getQueryBuilder();
        $query->select([$queryBuilder->from . '.*']);
        EloquentQueryBuilderHelper::setWhere($query, $queryBuilder);
        EloquentQueryBuilderHelper::setJoin($query, $queryBuilder);
        EloquentQueryBuilderHelper::setSelect($query, $queryBuilder);
        EloquentQueryBuilderHelper::setOrder($query, $queryBuilder);
        EloquentQueryBuilderHelper::setGroupBy($query, $queryBuilder);
        EloquentQueryBuilderHelper::setPaginate($query, $queryBuilder);
        $queryBuilder->update($values);
    }

    public function deleteByCondition(array $condition)
    {
        $queryBuilder = $this->getQueryBuilder();
        foreach ($condition as $key => $value) {
            $queryBuilder->where($key, OperatorEnum::EQUAL, $value);
        }
        $queryBuilder->delete();
    }
}
