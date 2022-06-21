<?php

namespace ZnDatabase\Eloquent\Domain\Base;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use ZnCore\Base\Exceptions\AlreadyExistsException;
use ZnCore\Base\Exceptions\InvalidMethodParameterException;
use ZnCore\Base\Exceptions\NotFoundException;
use ZnCore\Base\Helpers\ClassHelper;

use ZnCore\Base\Legacy\Yii\Helpers\ArrayHelper;
use ZnCore\Base\Legacy\Yii\Helpers\Inflector;
use ZnCore\Base\Libs\EventDispatcher\Traits\EventDispatcherTrait;
use ZnCore\Base\Libs\I18Next\Facades\I18Next;
use ZnCore\Base\Libs\Text\Helpers\TextHelper;
use ZnCore\Base\Libs\Domain\Enums\EventEnum;
use ZnCore\Base\Libs\Query\Enums\OperatorEnum;
use ZnCore\Base\Libs\Domain\Events\EntityEvent;
use ZnCore\Base\Libs\Domain\Events\QueryEvent;
use ZnCore\Base\Libs\Validation\Exceptions\UnprocessibleEntityException;
use ZnCore\Base\Libs\Entity\Helpers\EntityHelper;
use ZnCore\Base\Libs\QueryFilter\Helpers\FilterModelHelper;
use ZnCore\Base\Libs\Validation\Helpers\ValidationHelper;
use ZnCore\Base\Libs\Entity\Interfaces\EntityIdInterface;
use ZnCore\Base\Libs\Entity\Interfaces\UniqueInterface;
use ZnCore\Base\Libs\QueryFilter\Interfaces\ForgeQueryByFilterInterface;
use ZnCore\Base\Libs\Repository\Interfaces\CrudRepositoryInterface;
use ZnCore\Base\Libs\Repository\Interfaces\FindOneUniqueInterface;
use ZnCore\Base\Libs\Query\Entities\Query;
use ZnDatabase\Eloquent\Domain\Helpers\QueryBuilder\EloquentQueryBuilderHelper;
use ZnCore\Base\Libs\Relation\Libs\QueryFilter;

abstract class BaseEloquentCrudRepository extends BaseEloquentRepository implements CrudRepositoryInterface, ForgeQueryByFilterInterface, FindOneUniqueInterface
{

    protected $primaryKey = ['id'];

    /*public function _relations()
    {
        return [];
    }*/

    public function primaryKey()
    {
        return $this->primaryKey;
    }

    public function forgeQueryByFilter(object $filterModel, Query $query)
    {
        FilterModelHelper::validate($filterModel);
        FilterModelHelper::forgeOrder($query, $filterModel);
        $query = $this->forgeQuery($query);
        $event = new QueryEvent($query);
        $event->setFilterModel($filterModel);
        $this->getEventDispatcher()->dispatch($event, EventEnum::BEFORE_FORGE_QUERY_BY_FILTER);
        $schema = $this->getSchema();
        $columnList = $schema->getColumnListing($this->tableNameAlias());
        FilterModelHelper::forgeCondition($query, $filterModel, $columnList);
    }

    protected function queryFilterInstance(Query $query = null)
    {
        $query = $this->forgeQuery($query);
        /** @var QueryFilter $queryFilter */
        $queryFilter = new QueryFilter($this, $query);
        return $queryFilter;
    }

    public function count(Query $query = null): int
    {
        $query = $this->forgeQuery($query);
        $queryBuilder = $this->getQueryBuilder();
        $this->forgeQueryBuilder($queryBuilder, $query);
//        EloquentQueryBuilderHelper::setWhere($query, $queryBuilder);
//        EloquentQueryBuilderHelper::setJoin($query, $queryBuilder);
        return $queryBuilder->count();
    }

    public function all(Query $query = null): Enumerable
    {
        $query = $this->forgeQuery($query);
        $queryFilter = $this->queryFilterInstance($query);
//        $queryWithoutRelations = $queryFilter->getQueryWithoutRelations();
        $queryWithoutRelations = $query;
        $collection = $this->_all($queryWithoutRelations);
        $queryFilter->loadRelations($collection);
        return $collection;
    }

    public function loadRelations(Collection $collection, array $with)
    {
        $query = $this->forgeQuery();
        $query->with($with);
        $queryFilter = $this->queryFilterInstance($query);
        $queryFilter->loadRelations($collection);
    }

    public function oneById($id, Query $query = null): EntityIdInterface
    {
        if (empty($id)) {
            throw (new InvalidMethodParameterException('Empty ID'))
                ->setParameterName('id');
        }
        $query = $this->forgeQuery($query);
        $query->where($this->primaryKey[0], $id);
        $entity = $this->one($query);
        return $entity;
    }

    public function one(Query $query = null)
    {
        $query->limit(1);
        $collection = $this->all($query);
        if ($collection->count() < 1) {
            throw new NotFoundException('Not found entity!');
        }
        $entity = $collection->first();
        $event = $this->dispatchEntityEvent($entity, EventEnum::AFTER_READ_ENTITY);
        return $entity;
    }

    public function checkExists(EntityIdInterface $entity)
    {
        try {
            $existedEntity = $this->oneByUnique($entity);
            if ($existedEntity) {
                $message = I18Next::t('core', 'domain.message.entity_already_exist');
                $e = new AlreadyExistsException($message);
                $e->setEntity($existedEntity);
                throw $e;
            }
        } catch (NotFoundException $e) {
        }
    }

    public function create(EntityIdInterface $entity)
    {
        ValidationHelper::validateEntity($entity);

        $arraySnakeCase = $this->mapperEncodeEntity($entity);
        $queryBuilder = $this->getQueryBuilder();
        try {

            $event = $this->dispatchEntityEvent($entity, EventEnum::BEFORE_CREATE_ENTITY);
            if ($event->isPropagationStopped()) {
                return $entity;
            }

            $lastId = $queryBuilder->insertGetId($arraySnakeCase);
            $entity->setId($lastId);

            $event = $this->dispatchEntityEvent($entity, EventEnum::AFTER_CREATE_ENTITY);

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


            /*try {

            } catch (AlreadyExistsException $e) {
                if ($entity instanceof UniqueInterface) {
                    $unique = $entity->unique();
                    if ($unique) {
                        foreach ($unique as $attributeNames) {
                            foreach ($attributeNames as $attributeName) {
                                $errors->add($attributeName, $e->getMessage());
                            }
                        }
                    }
                }
                if ($errors->getErrorCollection()->isEmpty()) {
                    $errors->add('', $e->getMessage());
                }
            }*/
            throw $errors;
        }
    }

    private function oneByUniqueGroup(UniqueInterface $entity, $uniqueConfig): ?EntityIdInterface
    {
        $isBreak = false;
        $query = new Query();
        foreach ($uniqueConfig as $uniqueName) {
            $value = EntityHelper::getValue($entity, $uniqueName);
            if($value === null) {
                return null;
            }
            $query->where(Inflector::underscore($uniqueName), $value);
        }
        $all = $this->all($query);
        if ($all->count() > 0) {
            return $all->first();
        }
        return null;
    }

    public function oneByUnique(UniqueInterface $entity): EntityIdInterface
    {
        $unique = $entity->unique();
        if (!empty($unique)) {
            foreach ($unique as $uniqueConfig) {
                $oneEntity = $this->oneByUniqueGroup($entity, $uniqueConfig);
                if($oneEntity) {
                    return $oneEntity;
                }
            }
        }
        throw new NotFoundException();
    }

    public function createCollection(Collection $collection)
    {
//        DeprecateHelper::softThrow();
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
        /*if(!empty($this->getEntityClass()) && ClassHelper::instanceOf($this->getEntityClass(), \ZnCore\Base\Libs\Entity\Interfaces\EntityIdInterface::class, true)) {
            ArrayHelper::removeByValue('id', $columnList);
        }*/
        /*if ($this->autoIncrement()) {
            ArrayHelper::removeByValue($this->autoIncrement(), $columnList);
        }*/
        if (in_array('id', $columnList)) {
            ArrayHelper::removeByValue('id', $columnList);
        }
        return $columnList;
    }

    /*public function persist(EntityIdInterface $entity)
    {

    }*/

    protected function allBySql(string $sql, array $binds = [])
    {
        return $this->getConnection()
            ->createCommand($sql, $binds)
            ->queryAll(\PDO::FETCH_CLASS);
    }

    public function update(EntityIdInterface $entity)
    {
        ValidationHelper::validateEntity($entity);
        $this->oneById($entity->getId());

        $event = $this->dispatchEntityEvent($entity, EventEnum::BEFORE_UPDATE_ENTITY);

        $data = $this->mapperEncodeEntity($entity);
        $this->updateQuery($entity->getId(), $data);

        $event = $this->dispatchEntityEvent($entity, EventEnum::AFTER_UPDATE_ENTITY);

        //$this->updateById($entity->getId(), $data);
    }

    /*public function updateById($id, $data)
    {
        $this->oneById($id);
        $this->updateQuery($id, $data);
    }*/

    private function updateQuery($id, array $data)
    {
        $columnList = $this->getColumnsForModify();
        $data = ArrayHelper::extractByKeys($data, $columnList);
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->find($id);
        $queryBuilder->update($data);
    }

    public function deleteById($id)
    {
        $entity = $this->oneById($id);

        $event = $this->dispatchEntityEvent($entity, EventEnum::BEFORE_DELETE_ENTITY);

        if (!$event->isSkipHandle()) {
            $queryBuilder = $this->getQueryBuilder();
            $queryBuilder->delete($id);
        }
        
        $event = $this->dispatchEntityEvent($entity, EventEnum::AFTER_DELETE_ENTITY);
    }

    public function updateByQuery(Query $query, array $values)
    {
        $query = $this->forgeQuery($query);
        $queryFilter = $this->queryFilterInstance($query);
//        $queryWithoutRelations = $queryFilter->getQueryWithoutRelations();
        $queryWithoutRelations = $query;
//        $collection = $this->_all($queryWithoutRelations);
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
//        $collection = $this->allByBuilder($queryBuilder);
//        return $collection;
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
