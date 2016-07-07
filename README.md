## def-logger

![Build](https://travis-ci.org/andrew-kamenchuk/def-logger.svg?branch=master)

psr compatible php single class logger

basic usage:
```php
use def\Logger\Logger;

$logger = new Logger('def-logger');

```

Now you can set a callable handler to write log messages:

```php
$logger->setHandler('print_r');

// or
// will print_r every 10 records formatted by default formatter
$logger->setHandler('print_r', null, Logger::ALL, 10);
```

There are some handlers already defined, for example:
```php
$logger->setStreamHandler(STDERR, null, Logger::DEBUG); // there are also error_log, syslog and mail handler
```
You can change default formatting:
```php
$logger->setDefaultFormatter(function($levelname, $message, array $context) {
    return "$levelname *|$message|* ";
});
```
or pass custom formatter to setHandler, setStreamHandler, etc :
```php
$logger->setHandler('print_r', function($levelname, $message, array $context) {
    //
});
```

It is possible to disable some levels for handling:
```php
$logger->disable(Logger::DEBUG | Logger::INFO);
```

```addContextProcessor``` method allows add some extra data to context:

```php
$logger->addContextProcessor('some_key', function(array $context) {
    return 'some_value';
});
```
