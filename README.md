# BigQuery Monolog Handler

A simple (and configurable) Monolog handler for writing log messages to BigQuery, making use of Google's
`google/cloud-bigquery` PHP client.

## Installation

```
composer require garbetjie/monolog-bigquery-handler
```

Requires PHP 7.0.

## Getting started

The handler expects the BigQuery dataset and table to have been created already - the handler will not create either of
them.

You can run the following command (using the BigQuery command line tool) to create a minimal table that will be able to
accept log records (replace `$dataset` and `$table` with the actual dataset and table names):

```bash
bq mk --schema="channel:string,message:string,level:integer,level_name:string,context:string,extra:string,datetime:timestamp" --time_partitioning_field="datetime" "$dataset.$table"
```

## Usage

Simply create an instance of the BigQuery client, and pass it to the handler, along with the dataset and table names to
insert log records to.
 
```php
$bigQueryClient = new Google\Cloud\BigQuery\BigQueryClient();
$handler = new Garbetjie\Monolog\Handler\BigQueryHandler($bigQueryClient, 'dataset_name', 'table_name', $level = Logger::DEBUG, $bubble = true);
$logger = new Monolog\Logger('channel', [$handler]);

$logger->debug('debug message');
```

The usage shown above will send each message individually. It is recommended to make use of a `BufferHandler`. This will
ensure that log messages are batched, and will reduce the duration spent sending log messages.

When determining the size of the buffer to use, ensure that you're aware of the [quota limits for streaming inserts](https://cloud.google.com/bigquery/quotas#streaming_inserts)
in BigQuery. If any of these limits are hit, the whole batch of messages will fail.

An example of using the `BufferHandler` is shown below:

```php
// Using a buffer handler to batch log messages.

$bigQueryClient = new Google\Cloud\BigQuery\BigQueryClient();
$handler = new Garbetjie\Monolog\Handler\BigQueryHandler($bigQueryClient, 'dataset_name', 'table_name', $level = Logger::DEBUG, $bubble = true);
$handler = new Monolog\Handler\BufferHandler($handler, $bufferLimit = 10);
$logger = new Monolog\Logger('channel', [$handler]);

$logger->debug('message 1');
$logger->debug('message 2');
$logger->debug('message 3');
```

### Custom fields

Additional custom fields can be sent along with the logged fields. These custom fields can be added in the following
way:

```php
// Using custom fields

$handler = new Garbetjie\Monolog\Handler\BigQueryHandler($bigQueryClient, 'dataset_name', 'table_name', $level = Logger::DEBUG, $bubble = true);

$handler->setCustomField('field_name', 'field_value');
// Or $handler->setCustomField(['name' => 'value', 'other_name' => 'other_value'])

$handler->removeCustomField('field_name');
// Or $handler->removeCustomField(['field_name', 'other_field_name']);
```

Custom field values can be any scalar value that can be JSON encoded, or one of the following values:

* __`callable`:__ Any `callable` can be passed, and it will be executed before the log record is sent to BigQuery.

### Mapping fields

By default, the field names to insert to are the same as the [structure of a log record](https://github.com/Seldaek/monolog/blob/334b8d8783a1262c3b8311d6599889d82e9cc58c/doc/message-structure.md).
However, if you have BigQuery column names that aren't the same as these, you can map the log record field names to your
own column names:

```php
$bigQueryClient = new Google\Cloud\BigQuery\BigQueryClient();
$handler = new Garbetjie\Monolog\Handler\BigQueryHandler($bigQueryClient, 'dataset_name', 'table_name', $level = Logger::DEBUG, $bubble = true);
$handler->setFieldMapping(['datetime' => 'logged_at']);  // The `datetime` log record field will now be saved to the `logged_at` column.

$logger->debug('message 1');
```