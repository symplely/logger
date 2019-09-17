# logger

[![Build Status](https://travis-ci.org/symplely/logger.svg?branch=master)](https://travis-ci.org/symplely/logger)[![Build status](https://ci.appveyor.com/api/projects/status/n9rqhj2aw2pe9csv/branch/master?svg=true)](https://ci.appveyor.com/project/techno-express/logger/branch/master)[![codecov](https://codecov.io/gh/symplely/logger/branch/master/graph/badge.svg)](https://codecov.io/gh/symplely/logger)[![Codacy Badge](https://api.codacy.com/project/badge/Grade/639d7b9525414cb0abb22ebbe68379b5)](https://www.codacy.com/manual/techno-express/logger?utm_source=github.com&amp;utm_medium=referral&amp;utm_content=symplely/logger&amp;utm_campaign=Badge_Grade)[![Maintainability](https://api.codeclimate.com/v1/badges/7b4b5060e690092dc307/maintainability)](https://codeclimate.com/github/symplely/logger/maintainability)

An simple, fast, asynchronous PSR-3 compatible logger.

To have similar functionality as in Python's Async [aiologger](https://github.com/B2W-BIT/aiologger) Package.

## Table of Contents

* [Introduction/Usage](#introduction/usage)
* [Functions](#functions)
* [Installation](#installation)
* [Usage/Historical](#usage/historical)
* [Contributing](#contributing)
* [License](#license)

**This package is under development, all `asynchronous` parts has not been fully implemented.**

## Introduction/Usage

**logger** can be used to create log entries in different formats using multiple backend *writers*.
Basic usage of **logger** requires both a *writer* and a *logger* instance. A *writer* stores the log entry into a backend, and the *logger* instance consumes the *writer* to perform logging operations.

## Functions

## Installation

```text
composer require symplely/logger
```

## Usage/Historical

```php
use Async\Logger\Logger;

$logger = Logger::getLogger('php-app');
// or
$logger = new Logger('php-app'); Logger::getLogger('php-app');
```

Now you can set a backend *writer* to process log messages:

```php
yield $logger->setWriter('print_r');

// Or print_r every 10 records formatted with default formatter
yield $logger->setWriter('print_r', Logger::ALL, 10);
```

Here are some preset backend *writers*:

```php
yield $logger->streamWriter($stream = 'php://stdout',
    $levels = Logger::ALL, $interval = 1, callable $formatter = null);

yield $logger->mailWriter($to, $subject = null, array $headers = [],
    $levels = Logger::ALL, $interval = 1, callable $formatter = null);

$logger->errorLogWriter($errorType = 0,
    $levels = Logger::ALL, callable $formatter = null);

$logger->syslogWriter($logOpts = \LOG_PID | \LOG_ODELAY | \LOG_CONS,
    $facility = \LOG_USER, $levels = Logger::ALL, callable $formatter = null);

$logger->arrayWriter(array &$array = null,
    $levels = Logger::ALL, $interval = 1, callable $formatter = null);
```

You can change default formatting:

```php
$logger->defaultFormatter(function ($level, $message, array $context) {
    return sprintf("[%s] (%s): %-10s '%s'", date(DATE_RFC822), $logger->getName(), strtoupper($level), $message);
});
```

Or pass custom formatter with writer:

```php
yield $logger->setWriter('print_r', Logger::ALL, 10, function ($level, $message, array $context) {
    //
});
```

It is possible to disable some levels for handling:

```php
$logger->disable(Logger::DEBUG | Logger::INFO);
```

A processor is executed before the log data are passed to the writer. The input of a processor is a log event, an array containing all of the information to log; the output is also a log event, but can contain modified or additional values. A processor modifies the log event to prior to sending it to the writer.

The ```addProcessor``` method allows add some extra data to context, which represent [PSR-3 message placeholders](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md#12-message).

Placeholder names correspond to keys in the "context" array passed when logging a message:

`$logger->info('User with email {email} registered', ['email' => 'user@example.org']);`

```php
$logger->addProcessor('some_key', function (array $context) {
    return 'some_value';
});
```

Here are some preset concrete context processors:

```php
$logger->addUniqueId($prefix);
$logger->addPid();
$logger->addTimestamp($micro);
$logger->addMemoryUsage($format, $real, $peak;
$logger->addPhpSapi();
$logger->addPhpVersion();
```

Log an information message

```php
// will be executed in an new background task, will not pause current task
yield $logger->info('Informational message');

// will pause current task, do other tasks until logger completes the log entry
yield $logger->log(LogLevel::INFO, 'We have a PSR-compatible logger');
```

### Contributing

Contributions are encouraged and welcome; I am always happy to get feedback or pull requests on Github :) Create [Github Issues](https://github.com/symplely/logger/issues) for bugs and new features and comment on the ones you are interested in.

### License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
