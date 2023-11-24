<?php

declare(strict_types=1);

namespace Shopware\Storage\MySQL\Util;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * @phpstan-type Row=array{data: array<string, mixed>, columns: list<string>, }
 */
class MultiInsert
{
    /**
     * @var array<string, Row[]>
     */
    private array $inserts = [];

    /**
     * @var int<1, max>
     */
    private readonly int $chunkSize;

    public function __construct(
        private readonly Connection $connection,
        int $chunkSize = 250,
        private readonly bool $ignore = false,
        private readonly bool $replace = false
    ) {
        if ($chunkSize < 1) {
            throw new \InvalidArgumentException(
                sprintf('Parameter $chunkSize needs to be a positive integer starting with 1, "%d" given', $chunkSize)
            );
        }
        $this->chunkSize = $chunkSize;
    }

    /**
     * @param array<string, mixed>      $data
     * @param array<string, ParameterType::*>|null $types
     */
    public function add(string $table, array $data, ?array $types = null): void
    {
        $columns = [];

        foreach ($data as $key => &$value) {
            $columns[] = $key;

            $type = ParameterType::STRING;

            if ($types !== null && isset($types[$key])) {
                $type = $types[$key];
            }

            if ($value === null) {
                $value = 'NULL';
            } elseif (is_array($value)) {
                $value = $this->connection->quote(json_encode($value, \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_IGNORE), $type);
            } else {
                $value = $this->connection->quote($value, $type);
            }
        }

        $this->inserts[$table][] = [
            'data' => $data,
            'columns' => $columns,
            'types' => $types,
        ];
    }

    public function execute(): void
    {
        if (empty($this->inserts)) {
            return;
        }

        $grouped = $this->prepare();
        RetryableTransaction::retryable($this->connection, function () use ($grouped): void {
            foreach ($grouped as $query) {
                $this->connection->executeStatement($query);
            }
        });
        unset($grouped);

        $this->inserts = [];
    }

    /**
     * @return array<string>
     */
    private function prepare(): array
    {
        $queries = [];
        $template = 'INSERT INTO %s (%s) VALUES %s;';

        if ($this->ignore) {
            $template = 'INSERT IGNORE INTO %s (%s) VALUES %s;';
        }

        if ($this->replace) {
            $template = 'REPLACE INTO %s (%s) VALUES %s;';
        }

        foreach ($this->inserts as $table => $rows) {
            $columns = $this->prepareColumns($rows);
            $data = $this->prepareValues($columns, $rows);

            $columns = array_map(self::escape(...), $columns);

            $chunks = array_chunk($data, $this->chunkSize);
            foreach ($chunks as $chunk) {
                $queries[] = sprintf(
                    $template,
                    self::escape($table),
                    implode(', ', $columns),
                    implode(', ', $chunk)
                );
            }
        }

        return $queries;
    }

    /**
     * @param array<Row> $rows
     * @return array<string>
     */
    private function prepareColumns(array $rows): array
    {
        $columns = [];
        foreach ($rows as $row) {
            foreach ($row['columns'] as $column) {
                $columns[$column] = 1;
            }
        }

        return array_keys($columns);
    }

    /**
     * @param array<string> $columns
     * @param array<Row> $rows
     * @return array<string>
     */
    private function prepareValues(array $columns, array $rows): array
    {
        $stackedValues = [];
        /** @var array<string, mixed> $defaults */
        $defaults = array_combine(
            $columns,
            array_fill(0, \count($columns), 'DEFAULT')
        );
        foreach ($rows as $row) {
            $data = $row['data'];
            $values = $defaults;
            if (!\is_array($values)) {
                continue;
            }

            /**
             * @var string $key
             * @var mixed $value
             */
            foreach ($data as $key => $value) {
                $values[$key] = $value;
            }
            $stackedValues[] = '(' . implode(',', $values) . ')';
        }

        return $stackedValues;
    }

    private static function escape(string $string): string
    {
        if (\mb_strpos($string, '`') !== false) {
            throw new \InvalidArgumentException('Backtick not allowed in identifier');
        }

        return '`' . $string . '`';
    }

}
