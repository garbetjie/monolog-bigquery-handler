<?php

namespace Garbetjie\Monolog\Handler;

use Google\Cloud\BigQuery\BigQueryClient;
use Google\Cloud\BigQuery\Table;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

class BigQueryHandler extends AbstractProcessingHandler
{
    /**
     * @var BigQueryClient
     */
    private $client;

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

        $this->client = $bigQueryClient;
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
        $records = \array_map(
            function(array $record) {
                $customFields = $this->prepareRecordValues($this->customFields);

                return [
                    'data' => [
                        'channel' => $record['channel'],
                        'message' => $record['message'],
                        'level' => $record['level'],
                        'level_name' => $record['level_name'],
                        'context' => \json_encode($this->prepareRecordValues($record['context']) ?: new \stdClass()),
                        'extra' => \json_encode($this->prepareRecordValues($record['extra']) ?: new \stdClass()),
                        'logged_at' => $record['datetime']->format('Y-m-d H:i:s.uP')
                    ] + $customFields
                ];
            },
            $records
        );

        $this->table->insertRows($records);
    }

    private function prepareRecordValues(array $record): array
    {
        foreach ($record as $key => $value) {
            if ($value instanceof \DateTimeInterface) {
                $record[$key] = $value->format('Y-m-d H:i:s.uP');
            }

            if ($value instanceof \Closure) {
                $record[$key] = $value();
            }
        }

        return $record;
    }
}