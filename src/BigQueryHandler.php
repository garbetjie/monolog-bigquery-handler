<?php

namespace Garbetjie\Monolog\Handler;

use Google\Cloud\BigQuery\BigQueryClient;
use Google\Cloud\BigQuery\Table;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

class BigQueryHandler extends AbstractProcessingHandler
{
    /**
     * @var Table
     */
    private $table;

    /**
     * @var null|bool
     */
    private $handling = null;

    /**
     * @var array
     */
    private $customFields = [];

    /**
     * @var array
     */
    private $fieldMapping = [];

    /**
     * BigQueryHandler constructor.
     *
     * @param BigQueryClient $bigQueryClient
     * @param string $dataSetName
     * @param string $tableName
     * @param int $level
     * @param bool $bubble
     */
    public function __construct(BigQueryClient $bigQueryClient, string $dataSetName, string $tableName, $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);

        $this->table = $bigQueryClient->dataset($dataSetName)->table($tableName);
    }

    /**
     * Set a custom field (or multiple) to be inserted along with the Monolog record.
     *
     * An array of custom fields can be added, or a single $name/$value pair. Any custom fields that are the same as
     * the core fields will be ignored.
     *
     * @param string|array $name
     * @param mixed [$value]
     */
    public function setCustomField($name, $value = null)
    {
        if (\is_array($name)) {
            $this->customFields = \array_merge($this->customFields, $name);
        } else {
            $this->customFields[$name] = $value;
        }
    }

    /**
     * Remove a custom field that has been previously added.
     *
     * @param string|array $name
     */
    public function removeCustomField($name)
    {
        if (! \is_array($name)) {
            $name = [$name];
        }

        foreach ($name as $key) {
            unset($this->customFields[$key]);
        }
    }

    /**
     * Sets the mapping of source => destination fields when inserting into BigQuery.
     *
     * @param array $mapping
     */
    public function setFieldMapping(array $mapping)
    {
        $this->fieldMapping = $mapping;
    }

    /**
     * @param array $record
     * @return bool
     */
    public function isHandling(array $record): bool
    {
        // Levels don't match, so we're not handling this record.
        if (!parent::isHandling($record)) {
            return false;
        }

        // Check already performed to see whether the table exists. Simply return it.
        if ($this->handling !== null) {
            return $this->handling;
        }

        // Check if the table exists.
        $this->handling = $this->table->exists();

        return $this->handling;
    }

    /**
     * @inheritdoc
     */
    protected function write(array $record)
    {
        $this->handleBatch([$record]);
    }

    /**
     * @inheritdoc
     */
    public function handleBatch(array $records)
    {
        $this->table->insertRows(
            \array_map(
                function($record) {
                    return ['data' => $this->buildRecord($record)];
                },
                $records
            )
        );
    }

    /**
     * Builds the record to be logged.
     *
     * @param array $record
     * @return array
     */
    private function buildRecord(array $record): array
    {
        $built = [];

        foreach ($this->customFields as $key => $value) {
            $built[$key] = $this->formatValue($value);
        }

        foreach ($record as $key => $value) {
            if ($key === 'formatted') {
                continue;
            }

            $destinationKey = $this->fieldMapping[$key] ?? $key;
            $built[$destinationKey] = $this->formatValue($value);

            switch($key) {
                case 'extra':
                case 'context':
                    $built[$destinationKey] = json_encode($built[$destinationKey] ?: new \stdClass());
                    break;

                case 'datetime':
                    // BigQuery expects timestamps to be in UTC.
                    $built[$destinationKey]->setTimeZone(new \DateTimeZone('UTC'));
                    break;
            }
        }

        return $built;
    }

    /**
     * Formats the given value into something that can be logged easily.
     *
     * @param mixed $value
     * @return mixed|string
     */
    private function formatValue($value)
    {
        if (\is_callable($value)) {
            return \call_user_func($value);
        }

        // If a \DateTime instance, should be converted to a string.
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d\TH:i:s.u');
        }

        return $value;
    }
}