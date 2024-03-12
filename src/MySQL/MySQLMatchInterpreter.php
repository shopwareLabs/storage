<?php

namespace Shopware\Storage\MySQL;

use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Storage\Common\Schema\Collection;
use Shopware\Storage\Common\Search\Group;
use Shopware\Storage\Common\Search\Query;
use Shopware\Storage\Common\Search\Search;
use Shopware\Storage\Common\StorageContext;

class MySQLMatchInterpreter extends MySQLSearchInterpreter
{
    public function interpret(Collection $collection, QueryBuilder $builder, Search $search, StorageContext $context): void
    {
        $this->parse(queries: $search->group->queries, builder: $builder);

        $fields = $this->scoring(queries: $search->group->queries, builder: $builder);

        $builder->addOrderBy('(' . PHP_EOL . '    ' . implode(' + ' . PHP_EOL . '    ', $fields) . PHP_EOL . ')', 'DESC');

        $where = $this->group(group: $search->group, builder: $builder);

        $builder->andHaving($where . PHP_EOL);
    }

    private function getAlias(Query $query): string
    {
        return 'match_' . md5($query->field . $query->term . $query->boost);
    }

    /**
     * @param array<Query|Group> $queries
     */
    private function parse(array $queries, QueryBuilder $builder): void
    {
        foreach ($queries as $query) {
            if ($query instanceof Query) {
                $this->parseQuery(query: $query, builder: $builder);

                continue;
            }

            if ($query instanceof Group) {
                $this->parse(queries: $query->queries, builder: $builder);
            }
        }
    }

    private function parseQuery(Query $query, QueryBuilder $builder): void
    {
        $key = $this->getAlias($query);

        // accessor is `categories.name`, search field is stored as _categories_name_search
        $field = str_replace('.', '_', $query->field);

        $name = '_' . $field . '_search';

        $template = '
(
    MATCH (`#name#`) AGAINST (:#key#) 
    + 
    IF(`#name#` LIKE :#key#_like, 0.01, 0)        
) as `#alias#`';

        $variables = [
            '#name#' => $name,
            '#key#' => $key,
            '#alias#' => $key,
        ];

        $builder->addSelect(
            str_replace(array_keys($variables), array_values($variables), $template)
        );

        $builder->setParameter($key, $query->term);
        $builder->setParameter($key . '_like', $query->term . '%');
    }

    /**
     * @param array<Query|Group> $queries
     * @return array<string>
     */
    private function scoring(array $queries, QueryBuilder $builder): array
    {
        $fields = [];
        foreach ($queries as $query) {
            if ($query instanceof Query) {
                $fields[] = '`' . $this->getAlias($query) . '`';
                continue;
            }

            if ($query instanceof Group) {
                $nested = $this->scoring(queries: $query->queries, builder: $builder);
                $fields = array_merge($fields, $nested);

                continue;
            }
        }

        return $fields;
    }

    /**
     * @param array<Query|Group> $queries
     * @return array<string>
     */
    private function filter(array $queries, QueryBuilder $builder): array
    {
        $where = [];
        foreach ($queries as $query) {
            if ($query instanceof Query) {
                $where[] = sprintf('    IF(`%s` > 0, 1, 0)', $this->getAlias($query));
                continue;
            }

            $where[] = $this->group($query, $builder);
        }

        return $where;
    }

    private function group(Group $group, QueryBuilder $builder): string
    {
        $nested = $this->filter(queries: $group->queries, builder: $builder);

        $ifs = implode(' + ' . PHP_EOL, $nested);

        return sprintf('IF((' . PHP_EOL . '%s' . PHP_EOL . ') >= %s, 1, 0)', $ifs, $group->hits);
    }
}
