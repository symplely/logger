## def-logger

![Build](https://travis-ci.org/andrew-kamenchuk/def-logger.svg?branch=master)

psr compatible php single class logger

basic usage:
```php
use def\Logger\Logger;

$logger = new Logger('php-app'); // or Logger::getLogger('php-app');

```

Now you can set a callable writer to process log messages:

```php
$logger->setWriter('print_r');

// or
// will print_r every 10 records formatted with default formatter
$logger->setWriter('print_r', Logger::ALL, 10);
```

There are some writers already defined, for example:
```php
$logger->setStreamWriter(STDERR, Logger::DEBUG | Logger::INFO); // there are also error_log, syslog and mail writers
```
You can change default formatting:
```php
$logger->setDefaultFormatter(function ($levelname, $message, array $context) {
    //
});
```
or pass custom formatter with writer:
```php
$logger->setWriter('print_r', Logger::ALL, 10, function ($levelname, $message, array $context) {
    //
});
```

It is possible to disable some levels for handling:
```php
$logger->disable(Logger::DEBUG | Logger::INFO);
```

```addContextProcessor``` method allows add some extra data to context:

```php
$logger->addContextProcessor('some_key', function (array $context) {
    return 'some_value';
});
```
