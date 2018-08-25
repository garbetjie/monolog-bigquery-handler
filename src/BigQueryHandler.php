<?php

namespace Garbetjie\Monolog\Handler;

use Google\Cloud\BigQuery\BigQueryClient;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

class BigQueryHandler extends AbstractProcessingHandler
{
    private $client;
    private $table;
    private $handling = null;

    public function __construct(BigQueryClient $bigQueryClient, $dataSetName, $tableName, $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);

        $this->client = $bigQueryClient;
        $this->table = $bigQueryClient->dataset($dataSetName)->table($tableName);
    }

    public function isHandling(array $record)
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

    protected function write(array $record)
    {
        $this->handleBatch([$record]);
    }

    public function handleBatch(array $records)
    {
        $records = \array_map(
            function(array $record) {
                $record['extra'] = $this->prepareRecordValues($record['extra']);

                return [
                    'data' => [
                        'channel' => $record['channel'],
                        'message' => $record['message'],
                        'level' => $record['level'],
                        'level_name' => $record['level_name'],
                        'context' => \json_encode($this->prepareRecordValues($record['context']) ?: new \stdClass()),
                        'extra' => \json_encode($this->prepareRecordValues($record['extra']) ?: new \stdClass()),
                        'logged_at' => $record['datetime']->format('Y-m-d H:i:s.uP')
                    ]
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