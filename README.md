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
bq mk "$dataset.$table" --schema="channel:string:required,message:string,level:integer,level_name:string,context:string,extra:string,logged_at:timestamp" --time_partitioning_field="logged_at"
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
* __`instanceof DateTimeInterface`:__ Instances of this interface are converted to a string using the date format of `Y-m-d H:i:s.uP`.
