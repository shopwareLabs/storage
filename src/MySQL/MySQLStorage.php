<?php

namespace Shopware\Storage\MySQL;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Storage\Common\Aggregation\AggregationAware;
use Shopware\Storage\Common\Aggregation\AggregationCaster;
use Shopware\Storage\Common\Aggregation\Type\Aggregation;
use Shopware\Storage\Common\Aggregation\Type\Avg;
use Shopware\Storage\Common\Aggregation\Type\Count;
use Shopware\Storage\Common\Aggregation\Type\Distinct;
use Shopware\Storage\Common\Aggregation\Type\Max;
use Shopware\Storage\Common\Aggregation\Type\Min;
use Shopware\Storage\Common\Aggregation\Type\Sum;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\Document\Hydrator;
use Shopware\Storage\Common\Filter\Criteria;
use Shopware\Storage\Common\Filter\Paging\Limit;
use Shopware\Storage\Common\Filter\Result;
use Shopware\Storage\Common\Filter\FilterAware;
use Shopware\Storage\Common\Filter\Paging\Page;
use Shopware\Storage\Common\Schema\Collection;
use Shopware\Storage\Common\Storage;
use Shopware\Storage\Common\StorageContext;
use Shopware\Storage\Common\Total;
use Shopware\Storage\MySQL\Util\MultiInsert;

class MySQLStorage implements Storage, FilterAware, AggregationAware
{
    public function __construct(
        private readonly MySQLParser $parser,
        private readonly Hydrator $hydrator,
        private readonly MySQLAccessorBuilder $accessor,
        private readonly AggregationCaster $caster,
        private readonly Connection $connection,
        private readonly Collection $collection,
    ) {}

    private static function escape(string $source): string
    {
        return '`' . $source . '`';
    }

    public function setup(): void
    {
        // todo@o.skroblin auto setup feature
    }


    public function remove(array $keys): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `' . $this->collection->name . '` WHERE `key` IN (:keys)',
            ['keys' => $keys],
            ['keys' => ArrayParameterType::STRING]
        );
    }

    public function store(Documents $documents): void
    {
        $queue = new MultiInsert(
            connection: $this->connection,
            replace: true,
        );

        foreach ($documents as $document) {
            $queue->add($this->collection->name, $document->encode());
        }

        $queue->execute();
    }

    public function filter(Criteria $criteria, StorageContext $context): Result
    {
        $query = $this->buildQuery($criteria, $context);

        /** @var array<array<string, mixed>> $data */
        $data = $query->executeQuery()->fetchAllAssociative();

        $documents = [];
        foreach ($data as $row) {
            $documents[] = $this->hydrator->hydrate(
                collection: $this->collection,
                data: $row,
                context: $context
            );
        }

        return new Result(
            elements: $documents,
            total: $this->getTotal($query, $criteria)
        );
    }

    public function aggregate(array $aggregations, Criteria $criteria, StorageContext $context): array
    {
        $result = [];

        foreach ($aggregations as $aggregation) {
            $data = $this->loadAggregation(
                aggregation: $aggregation,
                criteria: $criteria,
                context: $context
            );

            $data = $this->caster->cast(
                collection: $this->collection,
                aggregation: $aggregation,
                data: $data
            );

            $result[$aggregation->name] = $data;
        }

        return $result;
    }

    private function loadAggregation(Aggregation $aggregation, Criteria $criteria, StorageContext $context): mixed
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->collection->name, 'root');

        $filters = array_merge(
            $criteria->filters,
            $aggregation->filters
        );

        //todo@skroblin support of post filters?
        if (!empty($filters)) {
            $parsed = $this->parser->parseFilters(
                collection: $this->collection,
                query: $query,
                filters: $filters,
                context: $context
            );

            foreach ($parsed as $filter) {
                $query->andWhere($filter);
            }
        }

        $accessor = $this->accessor->build(
            collection: $this->collection,
            query: $query,
            accessor: $aggregation->field,
            context: $context
        );

        if ($aggregation instanceof Min) {
            $query->select('MIN(' . $accessor . ')');
            return $query->executeQuery()->fetchOne();
        }

        if ($aggregation instanceof Max) {
            $query->select('MAX(' . $accessor . ')');
            return $query->executeQuery()->fetchOne();
        }

        if ($aggregation instanceof Avg) {
            $query->select('AVG(' . $accessor . ')');
            return $query->executeQuery()->fetchOne();
        }

        if ($aggregation instanceof Sum) {
            $query->select('SUM(' . $accessor . ')');
            return $query->executeQuery()->fetchOne();
        }

        if ($aggregation instanceof Count) {
            $query->select([
                $accessor . ' as `key`',
                'COUNT(' . $accessor . ') as count',
            ]);
            $query->groupBy($accessor);

            return $query->executeQuery()->fetchAllAssociative();
        }

        if ($aggregation instanceof Distinct) {
            $query->select('DISTINCT ' . $accessor);
            return $query->executeQuery()->fetchFirstColumn();
        }

        throw new \LogicException(sprintf('Unsupported aggregation type %s', get_class($aggregation)));
    }

    private function buildQuery(Criteria $criteria, StorageContext $context): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder();

        $query->select('root.*');
        $query->from(self::escape($this->collection->name), 'root');

        if ($criteria->paging instanceof Page) {
            $query->setFirstResult(($criteria->paging->page - 1) * $criteria->paging->limit);
            $query->setMaxResults($criteria->paging->limit);
        } elseif ($criteria->paging instanceof Limit) {
            $query->setMaxResults($criteria->paging->limit);
        }

        if ($criteria->primaries) {
            $query->andWhere('`key` IN (:keys)');
            $query->setParameter('keys', $criteria->primaries, ArrayParameterType::STRING);
        }

        if ($criteria->sorting) {
            foreach ($criteria->sorting as $sorting) {
                $accessor = $this->accessor->build(
                    collection: $this->collection,
                    query: $query,
                    accessor: $sorting->field,
                    context: $context
                );

                $query->addOrderBy($accessor, $sorting->order);
            }
        }

        if ($criteria->filters) {
            $filters = $this->parser->parseFilters(
                collection: $this->collection,
                query: $query,
                filters: $criteria->filters,
                context: $context
            );

            foreach ($filters as $filter) {
                $query->andWhere($filter);
            }
        }

        return $query;
    }

    private function getTotal(QueryBuilder $query, Criteria $criteria): ?int
    {
        if ($criteria->total === Total::NONE) {
            return null;
        }

        $query->resetOrderBy();

        $query->select('COUNT(*)');

        $total = $query->executeQuery()->fetchOne();

        if ($total === false) {
            throw new \RuntimeException('Could not fetch total');
        }

        if (!is_string($total) && !is_int($total)) {
            throw new \RuntimeException('Invalid total type');
        }

        return (int) $total;
    }
}
