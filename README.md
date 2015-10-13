## def-logger

![Build](https://travis-ci.org/andrew-kamenchuk/def-logger.svg?branch=master)

psr compatible php logger

basic usage:
```php
use def\Logger\Logger;

$logger = new Logger('def-logger');

```

Basic Logic: handler - is a php callable, applied to logger with some levels bitmask

```php
$logger->setHandler('print_r');

// or
// will print_r every 10 records formatted by default formatter
$logger->setHandler('print_r', null, Logger::ALL, 10);
```

There are some handlers already defined, example:
```php
$logger->setStreamHandler(STDERR, null, Logger::DEBUG);
```
You can change default formatting:
```php
$logger->setDefaultFormatter(function($levelname, $message, array $context) {
	return 'some string';
});
```
or passing formatter to setHandler, setStreamHandler, etc :
```php
$logger->setHandler('print_r', function($levelname, $message, array $context) {
	//	
});
```

You can use any Psr\Log\LoggerInterface implementation:
```php
$logger->bindLogger(new Some\Psr\Logger());

$logger->info('test message'); // will delegate logging to Some\Psr\Logger
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
