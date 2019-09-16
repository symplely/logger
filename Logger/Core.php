<?php

declare(strict_types = 1);

use Async\Logger\Logger;
use Psr\Log\LoggerInterface;

if (!\function_exists('logger_instance')) {
    function logger_instance(?string $name = null)
    {
        global $__logger__;

        if (empty($__logger__) || !$__logger__ instanceof LoggerInterface)
            $__logger__ = Logger::getLogger((empty($name) ? __CLASS__ : $name));

        return $__logger__;
    }

    function logger($level, $message, array $context = [])
    {
        return \logger_instance()->log($level, $message, $context);
    }

    function logger_shutdown()
    {
        return \logger_instance()->close();
    }

    function logger_writer(callable $writer, $levels = self::ALL, $interval = 1, callable $formatter = null)
    {
        return \logger_instance()->setWriter($writer, $levels, $interval, $formatter);
    }

    function logger_stream($stream = 'php://stdout', $levels = self::ALL, $interval = 1, callable $formatter = null)
    {
        return \logger_instance()->streamWriter($stream, $levels, $interval, $formatter);
    }

    function log_emergency($message, array $context = array())
    {
        return \logger_instance()->emergency($message, $context);
    }

    function log_alert($message, array $context = array())
    {
        return \logger_instance()->alert($message, $context);
    }

    function log_critical($message, array $context = array())
    {
        return \logger_instance()->critical($message, $context);
    }

    function log_error($message, array $context = array())
    {
        return \logger_instance()->error($message, $context);
    }

    function log_warning($message, array $context = array())
    {
        return \logger_instance()->warning($message, $context);
    }

    function log_notice($message, array $context = array())
    {
        return \logger_instance()->notice($message, $context);
    }

    function log_info($message, array $context = array())
    {
        return \logger_instance()->info($message, $context);
    }

    function log_debug($message, array $context = array())
    {
        return \logger_instance()->debug($message, $context);
    }
}
