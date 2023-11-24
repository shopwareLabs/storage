<?php

namespace Shopware\Storage\Opensearch;

use OpenSearch\Client;
use OpenSearchDSL\BuilderInterface;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\Compound\DisMaxQuery;
use OpenSearchDSL\Query\FullText\MultiMatchQuery;
use OpenSearchDSL\Query\Joining\NestedQuery;
use OpenSearchDSL\Query\MatchAllQuery;
use OpenSearchDSL\Query\TermLevel\ExistsQuery;
use OpenSearchDSL\Query\TermLevel\RangeQuery;
use OpenSearchDSL\Query\TermLevel\TermQuery;
use OpenSearchDSL\Query\TermLevel\TermsQuery;
use OpenSearchDSL\Query\TermLevel\WildcardQuery;
use OpenSearchDSL\Search;
use OpenSearchDSL\Sort\FieldSort;
use Shopware\Storage\Common\Document\Document;
use Shopware\Storage\Common\Document\Documents;
use Shopware\Storage\Common\Filter\FilterCriteria;
use Shopware\Storage\Common\Filter\FilterResult;
use Shopware\Storage\Common\Filter\FilterStorage;
use Shopware\Storage\Common\Schema\FieldType;
use Shopware\Storage\Common\Schema\Schema;
use Shopware\Storage\Common\Schema\SchemaUtil;
use Shopware\Storage\Common\StorageContext;

/**
 * @phpstan-import-type Sorting from FilterCriteria
 * @phpstan-import-type Filter from FilterCriteria
 */
class OpenSearchFilterStorage implements FilterStorage
{
    public function __construct(private readonly Client $client, private readonly Schema $schema)
    {
    }

    public function setup(): void
    {
        // todo@o.skroblin auto setup feature
    }

    public function remove(array $keys): void
    {
        $documents = array_map(function ($key) {
            return ['delete' => ['_id' => $key]];
        }, $keys);

        $arguments = [
            'index' => $this->schema->source,
            'body' => $documents,
        ];

        $this->client->bulk($arguments);
    }

    public function store(Documents $documents): void
    {
        $body = [];
        foreach ($documents as $document) {
            $body[] = [
                'index' => [
                    '_index' => $this->schema->source,
                    '_id' => $document->key,
                ]
            ];

            $body[] = $document->data;
        }

        if (empty($body)) {
            return;
        }

        $this->client->bulk(['body' => $body]);

        $this->client->indices()->refresh([
            'index' => $this->schema->source
        ]);
    }

    public function read(
        FilterCriteria $criteria,
        StorageContext $context
    ): FilterResult {
        $search = new Search();

        if ($criteria->page) {
            $search->setFrom(($criteria->page - 1) * $criteria->limit);
        }

        if ($criteria->limit) {
            $search->setSize($criteria->limit);
        }

        if ($criteria->keys) {
            $search->addQuery(
                new TermsQuery(
                    field: '_id',
                    terms: $criteria->keys
                )
            );
        }

        $queries = $this->parseRootFilter(
            filters: $criteria->filters,
            context: $context
        );

        foreach ($queries as $query) {
            $search->addQuery(
                query: $query,
                boolType: BoolQuery::FILTER
            );
        }

        if ($criteria->sorting) {
            foreach ($criteria->sorting as $sorting) {
                $search->addSort(new FieldSort(
                    field: $sorting['field'],
                    order: $sorting['direction']
                ));
            }
        }

        $parsed = $search->toArray();

        $result = $this->client->search([
            'index' => $this->schema->source,
            '_source' => true,
            'track_total_hits' => $criteria->total,
            'body' => $parsed
        ]);

        $documents = [];
        foreach ($result['hits']['hits'] as $hit) {
            $documents[] = new Document(
                key: $hit['_id'],
                data: $hit['_source'],
            );
        }

        return new FilterResult(
            elements: $documents,
            total: $this->getTotal($criteria, $result),
        );
    }

    /**
     * @param Filter[] $filters
     * @return array<BuilderInterface>
     */
    private function parseRootFilter(array $filters, StorageContext $context): array
    {
        $queries = [];

        foreach ($filters as $filter) {
            $root = SchemaUtil::resolveRootFieldSchema(
                schema: $this->schema,
                filter: $filter
            );

            $translated = $root['translated'] ?? false;

            // object field? created nested
            if (in_array($root['type'], [FieldType::OBJECT, FieldType::OBJECT_LIST], true) || $translated) {
                $nested = $this->parseFilter(
                    filter: $filter,
                    context: $context
                );

                $queries[] = new NestedQuery(path: $root['name'], query: $nested);

                continue;
            }

            $queries[] = $this->parseFilter(
                filter: $filter,
                context: $context
            );
        }

        return $queries;
    }

    /**
     * @param Filter $filter
     */
    private function translatedQuery(\Closure $factory, array $filter, StorageContext $context): BuilderInterface
    {
        $queries = [];

        $before = [];

        foreach ($context->languages as $index => $languageId) {
            if (array_key_first($context->languages) === $index) {
                $queries[] = new BoolQuery([
                    BoolQuery::MUST => [
                        new ExistsQuery(field: $filter['field'] . '.' . $languageId),
                        $factory([
                            'field' => $filter['field'] . '.' . $languageId,
                            'value' => $filter['value'],
                            'type' => $filter['type']
                        ])
                    ]
                ]);

                $before[] = $languageId;

                continue;
            }

            $bool = new BoolQuery();

            foreach ($before as $id) {
                $bool->add(
                    new ExistsQuery(field: $filter['field'] . '.' . $id),
                    BoolQuery::MUST_NOT
                );
            }

            $bool->add(
                $factory([
                    'field' => $filter['field'] . '.' . $languageId,
                    'value' => $filter['value'],
                    'type' => $filter['type']
                ])
            );

            $before[] = $languageId;
            $queries[] = $bool;
        }

        $source = new BoolQuery([
            BoolQuery::SHOULD => $queries
        ]);

        $source->addParameter('minimum_should_match', 1);

        return $source;
    }

    /**
     * @param Filter $filter
     */
    private function parseFilter(array $filter, StorageContext $context): BuilderInterface
    {
        $type = $filter['type'];
        $value = $filter['value'];

        try {
            $schema = SchemaUtil::resolveFieldSchema($this->schema, $filter);
        } catch (\Exception) {
            $schema = [];
        }

        // nested support?
        $translated = $schema['translated'] ?? false;

        // create an inline function which generates me a BuilderInterface, based on the given filter

        $factory = function (\Closure $factory) use ($filter) {
            return $factory($filter);
        };

        if ($translated) {
            $factory = function (\Closure $generator) use ($filter, $context) {
                return $this->translatedQuery($generator, $filter, $context);
            };
        }

        switch (true) {
            // field === null
            case $value === null && $type === 'equals':
                return $factory(function (array $filter) {
                    return new BoolQuery([
                        BoolQuery::MUST_NOT => new ExistsQuery(field: $filter['field'])
                    ]);
                });

                // field !== null
            case $value === null && $type === 'not':
                return $factory(function (array $filter) {
                    return new ExistsQuery(field: $filter['field']);
                });
            case $type === 'equals':
                return $factory(function (array $filter) {
                    return new TermQuery(
                        field: $filter['field'],
                        value: $filter['value']
                    );
                });
            case $type === 'not':
                return $factory(function (array $filter) {
                    return new BoolQuery([
                        BoolQuery::MUST_NOT => new TermsQuery(
                            field: $filter['field'],
                            terms: [$filter['value']]
                        )
                    ]);
                });

            case $type === 'equals-any':
                return $factory(function (array $filter) {
                    return new TermsQuery(
                        field: $filter['field'],
                        terms: $filter['value']
                    );
                });

            case $type ===  'not-any':
                return $factory(function (array $filter) {
                    return new BoolQuery([
                        BoolQuery::MUST_NOT => new TermsQuery(
                            field: $filter['field'],
                            terms: $filter['value']
                        )
                    ]);
                });
            case $type ===  'contains':
                return $factory(function (array $filter) {
                    return new WildcardQuery(
                        field: $filter['field'],
                        value: '*' . $filter['value'] . '*'
                    );
                });
            case $type ===  'starts-with':
                return $factory(function (array $filter) {
                    return new WildcardQuery(
                        field: $filter['field'],
                        value: $filter['value'] . '*'
                    );
                });
            case $type ===  'ends-with':
                return $factory(function (array $filter) {
                    return new WildcardQuery(
                        field: $filter['field'],
                        value: '*' . $filter['value']
                    );
                });
            case $type ===  'gte':
                return $factory(function (array $filter) {
                    return new RangeQuery(
                        field: $filter['field'],
                        parameters: ['gte' => $filter['value']]
                    );
                });

            case $type ===  'lte':
                return $factory(function (array $filter) {
                    return new RangeQuery(
                        field: $filter['field'],
                        parameters: ['lte' => $filter['value']]
                    );
                });

            case $type ===  'gt':
                return $factory(function (array $filter) {
                    return new RangeQuery(
                        field: $filter['field'],
                        parameters: ['gt' => $filter['value']]
                    );
                });
            case $type ===  'lt':
                return $factory(function (array $filter) {
                    return new RangeQuery(
                        field: $filter['field'],
                        parameters: ['lt' => $filter['value']]
                    );
                });

            case $type ===  'and':
                if (!isset($filter['queries'])) {
                    throw new \LogicException('Missing queries in and query');
                }

                $nested = array_map(function ($query) use ($context) {
                    /** @var Filter $query */
                    return $this->parseFilter(
                        filter: $query,
                        context: $context
                    );
                }, $filter['queries']);

                return new BoolQuery([
                    BoolQuery::MUST => $nested
                ]);

            case $type ===  'or':
                if (!isset($filter['queries'])) {
                    throw new \LogicException('Missing queries in and query');
                }

                $nested = array_map(function ($query) use ($context) {
                    /** @var Filter $query */
                    return $this->parseFilter(
                        filter: $query,
                        context: $context
                    );
                }, $filter['queries']);

                $bool = new BoolQuery([
                    BoolQuery::SHOULD => $nested
                ]);

                $bool->addParameter('minimum_should_match', 1);

                return $bool;

            case $type ===  'nand':
                if (!isset($filter['queries'])) {
                    throw new \LogicException('Missing queries in and query');
                }
                $nested = array_map(function ($query) use ($context) {
                    /** @var Filter $query */
                    return $this->parseFilter(
                        filter: $query,
                        context: $context
                    );
                }, $filter['queries']);

                return new BoolQuery([
                    BoolQuery::MUST_NOT => $nested
                ]);

            case $type ===  'nor':
                if (!isset($filter['queries'])) {
                    throw new \LogicException('Missing queries in and query');
                }
                $nested = array_map(function ($query) use ($context) {
                    /** @var Filter $query */
                    return $this->parseFilter(
                        filter: $query,
                        context: $context
                    );
                }, $filter['queries']);

                $bool = new BoolQuery([
                    BoolQuery::MUST_NOT => $nested
                ]);

                $bool->addParameter('minimum_should_match', 1);

                return $bool;

            default:
                throw new \RuntimeException(sprintf('Filter type %s is not supported', $type));
        }
    }

    /**
     * @param array<string, mixed> $result
     */
    private function getTotal(FilterCriteria $criteria, array $result): int|null
    {
        if (!$criteria->total) {
            return null;
        }

        if (!array_key_exists('hits', $result) || !is_array($result['hits'])) {
            throw new \LogicException('Missing hits key in opensearch result set');
        }

        if (!array_key_exists('total', $result['hits']) || !is_array($result['hits']['total'])) {
            throw new \LogicException('Missing hits.total key in opensearch result set');
        }

        if (!array_key_exists('value', $result['hits']['total'])) {
            throw new \LogicException('Missing hits.total.value key in opensearch result set');
        }

        return (int) $result['hits']['total']['value'];
    }

}
