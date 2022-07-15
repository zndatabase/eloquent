<?php

namespace ZnDatabase\Eloquent\Domain\Helpers\QueryBuilder;

use Illuminate\Database\Query\Builder;
//use Illuminate\Support\Str;
use ZnDomain\Query\Entities\Join;
use ZnDomain\Query\Enums\OperatorEnum;
use ZnDatabase\Base\Domain\Enums\DbDriverEnum;
use ZnDatabase\Base\Domain\Helpers\DbHelper;
use ZnDatabase\Base\Domain\Interfaces\QueryBuilderInterface;
use ZnDomain\Query\Entities\Query;
use ZnDomain\Query\Entities\Where;

class EloquentQueryBuilderHelper implements QueryBuilderInterface
{

    public static function forgeColumnName(string $columnName, Builder $queryBuilder): string
    {
        if(strpos($columnName, '.')  === false) {
            $columnName = $queryBuilder->from . '.' . $columnName;
        }
        return $columnName;
    }

    public static function setWhere(Query $query, Builder $queryBuilder)
    {
        $driver = $queryBuilder->getConnection()->getConfig('driver');

        $queryArr = $query->toArray();
        if ( ! empty($queryArr[Query::WHERE])) {
            foreach ($queryArr[Query::WHERE] as $key => $value) {
                $column = self::forgeColumnName($key, $queryBuilder);
                if (is_array($value)) {
                    $queryBuilder->whereIn($column, $value);
                } else {
                    $queryBuilder->where($column, $value);
                }
            }
        }

        $whereArray = $query->getWhereNew();
        if ( ! empty($whereArray)) {
            /** @var Where $where */
            foreach ($whereArray as $where) {
                $column = self::forgeColumnName($where->column, $queryBuilder);
                if (is_array($where->value)) {
                    $queryBuilder->whereIn($column, $where->value, $where->boolean, $where->not);
                } else {
                    if($where->operator == OperatorEnum::ILIKE && $driver == DbDriverEnum::SQLITE) {
                        $where->operator = OperatorEnum::LIKE;
                    }
                    $queryBuilder->where($column, $where->operator, $where->value, $where->boolean);
                }
            }
        }
    }

    public static function setJoin(Query $query, Builder $queryBuilder)
    {
        $queryArr = $query->toArray();
        if ( ! empty($queryArr['join_new'])) {
            /** @var Join $join */
            foreach ($queryArr['join_new'] as $join) {
                $queryBuilder->join($join->table, $join->first, $join->operator, $join->second, $join->type, $join->where);
            }
        }
        if ( ! empty($queryArr[Query::JOIN])) {
            foreach ($queryArr[Query::JOIN] as $key => $value) {
                $queryBuilder->join($value['table'], $value['on'][0], '=', $value['on'][1], $value['type']);
            }
        }
    }

    public static function setOrder(Query $query, Builder $queryBuilder)
    {
        $queryArr = $query->toArray();
        if ( ! empty($queryArr[Query::ORDER])) {
            foreach ($queryArr[Query::ORDER] as $field => $direction) {
                $column = self::forgeColumnName($field, $queryBuilder);
                $queryBuilder->orderBy($column, DbHelper::encodeDirection($direction));
            }
        }
    }

    public static function setGroupBy(Query $query, Builder $queryBuilder)
    {
        $queryArr = $query->toArray();
        if ( ! empty($queryArr[Query::GROUP])) {
            $queryBuilder->groupBy($queryArr[Query::GROUP]);
            /*foreach ($queryArr[Query::GROUP] as $field => $direction) {
                $queryBuilder->groupBy($field, DbHelper::encodeDirection($direction));
            }*/
        }
    }

    public static function setSelect(Query $query, Builder $queryBuilder)
    {
        $queryArr = $query->toArray();
        if ( ! empty($queryArr[Query::SELECT])) {
            $queryBuilder->select($queryArr[Query::SELECT]);
        }
    }

    public static function setPaginate(Query $query, Builder $queryBuilder)
    {
        $queryArr = $query->toArray();
        if ( ! empty($queryArr[Query::LIMIT])) {
            $queryBuilder->limit($queryArr[Query::LIMIT]);
        }
        if ( ! empty($queryArr[Query::OFFSET])) {
            $queryBuilder->offset($queryArr[Query::OFFSET]);
        }

        if ($query->getLimit() !== null) {
            $queryBuilder->limit($query->getLimit());
        }
        if ($query->getOffset() !== null) {
            $queryBuilder->offset($query->getOffset());
        }
    }

}