# BigQuery Monolog Handler

A simple (and configurable) Monolog handler for writing log messages to BigQuery,
making use of Google's `google/cloud-bigquery` PHP client.


## Usage

Sending each log message individually:

```php
use Garbetjie\Monolog\Handler\BigQueryHandler;
use Google\Cloud\BigQuery\BigQueryClient;
use Monolog\Logger;

$bigQueryClient = new BigQueryClient();
$handler = new BigQueryHandler($bigQueryClient, 'dataset_name', 'table_name', $level = Logger::DEBUG, $bubble = true);
$logger = new Logger('channel', [$handler]);

$logger->debug('debug message');
```

The recommended usage for this is using a `BufferHandler`. This will ensure that log messages are batched,
and will reduce the number of calls made.

When determining the size of the buffer to use, ensure that you're aware of the [quota limits for streaming inserts](https://cloud.google.com/bigquery/quotas#streaming_inserts)
in BigQuery. If any of these limits are hit, the whole batch of messages will fail.

```php
use Garbetjie\Monolog\Handler\BigQueryHandler;
use Google\Cloud\BigQuery\BigQueryClient;
use Monolog\Handler\BufferHandler;
use Monolog\Logger;

$bigQueryClient = new BigQueryClient();
$handler = new BigQueryHandler($bigQueryClient, 'dataset_name', 'table_name', $level = Logger::DEBUG, $bubble = true);
$handler = new BufferHandler($handler, $bufferLimit = 10);
$logger = new Logger('channel', [$handler]);

$logger->debug('message 1');
$logger->debug('message 2');
$logger->debug('message 3');
$logger->debug('message 4');
$logger->debug('message 5');
```


## Creating a table

Currently, the handler requires a specific structure. The command below can be used to quickly and easily create this structure:

```bash
bq mk "$dataset.$table" --schema="channel:string:required,message:string,level:integer,level_name:string,context:string,extra:string,logged_at:timestamp" --time_partitioning_field="logged_at"
```