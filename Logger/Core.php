<?php

declare(strict_types = 1);

use Async\Logger\Logger;
use Psr\Log\LoggerInterface;

if (!\function_exists('logger_instance')) {
	/**
     * Create/returns an global logger instance by.
	 */
    function logger_instance(?string $name = null)
    {
        global $__logger__, $__loggerTag__;

        if (!empty($name) || isset($__loggerTag__[$name]))
            $__loggerTag__[$name] = Logger::getLogger($name);
        else
            $__logger__ = Logger::getLogger($name);

        return empty($name) ?  $__logger__ : $__loggerTag__[$name];
    }

	/**
     * Logs a message.
     * Will pause current task, continue other tasks,
     * resume current task when the `logger` operation completes.
     *
	 * - This function needs to be prefixed with `yield`
	 */
    function logger($level, $message, array $context = [], ?string $name = null)
    {
        return \logger_instance($name)->log($level, $message, $context);
    }

	/**
     * Closes and clears an global logger instance by.
     *
	 * - This function needs to be prefixed with `yield`
	 */
    function logger_shutdown(?string $name = null)
    {
        global $__logger__, $__loggerTag__;

        yield \logger_instance($name)->close();

        if (!empty($name) || isset($__loggerTag__[$name])) {
            $__loggerTag__[$name] = null;
            unset($GLOBALS['$__loggerTag__'][$name]);
        } else {
            $__logger__ = null;
            unset($GLOBALS['__logger__']);
        }
    }

	/**
     * Closes and clears `All` global `logger` instances.
     *
	 * - This function needs to be prefixed with `yield`
	 */
    function logger_nuke()
    {
        global $__logger__, $__loggerTag__;

        if (!empty($__loggerTag__)) {
            $names = \array_keys($__loggerTag__);
            foreach($names as $name) {
                if ($__loggerTag__[$name] instanceof LoggerInterface) {
                    yield $__loggerTag__[$name]->close();
                }

                $__loggerTag__[$name] = null;
                unset($GLOBALS['$__loggerTag__'][$name]);
            }
        }

        if (!empty($__logger__)) {
            if ($__logger__ instanceof LoggerInterface) {
                yield $__logger__->close();
            }

            $__logger__ = null;
            unset($GLOBALS['__logger__']);
        }
    }

	/**
     * Creates/uses an custom backend `writer`
     *
	 * - This function needs to be prefixed with `yield`
	 */
    function logger_writer(
        callable $writer,
        $levels = Logger::ALL,
        int $interval = 1,
        callable $formatter = null,
        ?string $name = null)
    {
        return \logger_instance($name)->setWriter($writer, $levels, $interval, $formatter);
    }

	/**
     * Creates/uses O.S. syslog as backend `writer`
	 */
    function logger_syslog(
        $logOpts = \LOG_PID | \LOG_ODELAY | \LOG_CONS,
        $facility = \LOG_USER,
        $levels = Logger::ALL,
        callable $formatter = null, ?string $name = null)
    {
            return \logger_instance($name)->syslogWriter($logOpts, $facility, $levels, $formatter);
    }

	/**
     * Creates/uses O.S. error_log as backend `writer`
	 */
    function logger_errorlog($type = 0, $levels = Logger::ALL, callable $formatter = null, ?string $name = null)
    {
        return \logger_instance($name)->errorLogWriter($type, $levels, $formatter);
    }

	/**
     * Creates/uses an memory array as backend `writer`
	 */
    function logger_array(
        array &$array = null,
        $levels = Logger::ALL,
        int $interval = 1,
        callable $formatter = null,
        ?string $name = null)
    {
        return \logger_instance($name)->arrayWriter($array, $levels, $interval, $formatter);
    }

	/**
     * Creates/uses an file/stream as backend `writer`
     *
	 * - This function needs to be prefixed with `yield`
	 */
    function logger_stream(
        $stream = 'php://stdout',
        $levels = Logger::ALL,
        int $interval = 1,
        callable $formatter = null,
        ?string $name = null)
    {
        return \logger_instance($name)->streamWriter($stream, $levels, $interval, $formatter);
    }

	/**
     * Creates/sends an email as backend `writer`
     *
	 * - This function needs to be prefixed with `yield`
	 */
    function logger_mail(
        string $to,
        $subject = '',
        array $headers = [],
        $levels = Logger::ALL,
        int $interval = 1,
        callable $formatter = null,
        ?string $name = null)
    {
        return \logger_instance($name)->mailWriter($to, $subject, $headers, $levels, $interval, $formatter);
    }

	/**
     * Logs an EMERGENCY message.
     * Will not pause current task.
     * Will create new background task to complete the `logger` operation.
     *
	 * - This function needs to be prefixed with `yield`
	 */
    function log_emergency(string $message, $context = [], ?string $name = null)
    {
        if (\is_string($context) && empty($name)) {
            $name = $context;
            $context = [];
        }

        return \logger_instance($name)->emergency($message, $context);
    }

	/**
     * Logs an ALERT message.
     * Will not pause current task.
     * Will create new background task to complete the `logger` operation.
     *
	 * - This function needs to be prefixed with `yield`
	 */
    function log_alert($message, $context = [], ?string $name = null)
    {
        if (\is_string($context) && empty($name)) {
            $name = $context;
            $context = [];
        }

        return \logger_instance($name)->alert($message, $context);
    }

	/**
     * Logs an CRITICAL message.
     * Will not pause current task.
     * Will create new background task to complete the `logger` operation.
     *
	 * - This function needs to be prefixed with `yield`
	 */
    function log_critical(string $message, $context = [], ?string $name = null)
    {
        if (\is_string($context) && empty($name)) {
            $name = $context;
            $context = [];
        }

        return \logger_instance($name)->critical($message, $context);
    }

	/**
     * Logs an ERROR message.
     * Will not pause current task.
     * Will create new background task to complete the `logger` operation.
     *
	 * - This function needs to be prefixed with `yield`
	 */
    function log_error(string $message, $context = [], ?string $name = null)
    {
        if (\is_string($context) && empty($name)) {
            $name = $context;
            $context = [];
        }

        return \logger_instance($name)->error($message, $context);
    }

	/**
     * Logs an WARNING message.
     * Will not pause current task.
     * Will create new background task to complete the `logger` operation.
     *
	 * - This function needs to be prefixed with `yield`
	 */
    function log_warning(string $message, $context = [], ?string $name = null)
    {
        if (\is_string($context) && empty($name)) {
            $name = $context;
            $context = [];
        }

        return \logger_instance($name)->warning($message, $context);
    }

	/**
     * Logs an NOTICE message.
     * Will not pause current task.
     * Will create new background task to complete the `logger` operation.
     *
	 * - This function needs to be prefixed with `yield`
	 */
    function log_notice(string $message, $context = [], ?string $name = null)
    {
        if (\is_string($context) && empty($name)) {
            $name = $context;
            $context = [];
        }

        return \logger_instance($name)->notice($message, $context);
    }

	/**
     * Logs an INFO message.
     * Will not pause current task.
     * Will create new background task to complete the `logger` operation.
     *
	 * - This function needs to be prefixed with `yield`
	 */
    function log_info($message, $context = [], ?string $name = null)
    {
        if (\is_string($context) && empty($name)) {
            $name = $context;
            $context = [];
        }

        return \logger_instance($name)->info($message, $context);
    }

	/**
     * Logs an DEBUG message.
     * Will not pause current task.
     * Will create new background task to complete the `logger` operation.
     *
	 * - This function needs to be prefixed with `yield`
	 */
    function log_debug(string $message, $context = [], ?string $name = null)
    {
        if (\is_string($context) && empty($name)) {
            $name = $context;
            $context = [];
        }

        return \logger_instance($name)->debug($message, $context);
    }

    /**
     * Add custom context data to the message to be logged.
     * Make an context processor on the `{`$key`}` placeholder.
     */
    function logger_processor($key, callable $processor, ?string $name = null)
    {
        return \logger_instance($name)->addProcessor($key, $processor);
    }

    /**
     * Adds Unique Id to the message to be logged.
     * An concrete context processor on `{unique_id}` placeholder.
     */
    function logger_uniqueId($prefix = '', ?string $name = null)
    {
        return \logger_instance($name)->addUniqueId($prefix);
    }

    /**
     * Adds PHP's process ID to the message to be logged.
     * An concrete context processor on `{pid}` placeholder.
     */
    function logger_pid(?string $name = null)
    {
        return \logger_instance($name)->addPid();
    }

    /**
     * Adds timestamp to the message to be logged.
     * An concrete context processor on `{timestamp}` placeholder.
     */
    function logger_timestamp($micro = false, ?string $name = null)
    {
        return \logger_instance($name)->addTimestamp($micro);
    }

    /**
     * Adds memory usage to the message to be logged.
     * An concrete context processor on `{memory_usage}` placeholder.
     *
	 * - This function needs to be prefixed with `yield`
     */
    function logger_memoryUsage($format = null, $real = false, $peak = false, ?string $name = null)
    {
        return \logger_instance($name)->addMemoryUsage($format, $real, $peak);
    }

    /**
     * Adds type of PHP interface to the message to be logged.
     * An concrete context processor on `{php_sapi}` placeholder.
     */
    function logger_phpSapi(?string $name = null)
    {
        return \logger_instance($name)->addPhpSapi();
    }

    /**
     * Adds PHP version to the message to be logged.
     * An concrete context processor on `{php_version}` placeholder.
     */
    function logger_phpVersion(?string $name = null)
    {
        return \logger_instance($name)->addPhpVersion();
    }
}
