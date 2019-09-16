<?php

declare(strict_types = 1);

use Async\Logger\Logger;
use Psr\Log\LoggerInterface;

if (!\function_exists('logger_instance')) {
	/**
     * Create/returns the global logger instance.
	 */
    function logger_instance(?string $name = null)
    {
        global $__logger__;

        if (empty($__logger__) || !$__logger__ instanceof LoggerInterface)
            $__logger__ = Logger::getLogger((empty($name) ? __CLASS__ : $name));

        return $__logger__;
    }

	/**
     * Logs a message.
     * Will pause current task, continue other tasks,
     * resume current task when the `logger` operation completes.
     *
	 * - This function needs to be prefixed with `yield`
	 */
    function logger($level, $message, array $context = [])
    {
        return \logger_instance()->log($level, $message, $context);
    }

	/**
     * Shutdown and closes the global logger instance.
     *
	 * - This function needs to be prefixed with `yield`
	 */
    function logger_shutdown()
    {
        yield \logger_instance()->close();

        global $__logger__;
        $__logger__ = null;     
   
        unset($GLOBALS['__logger__']);
    }

	/**
     * Creates/uses an custom backend `writer`
     *
	 * - This function needs to be prefixed with `yield`
	 */
    function logger_writer(callable $writer, $levels = self::ALL, $interval = 1, callable $formatter = null)
    {
        return \logger_instance()->setWriter($writer, $levels, $interval, $formatter);
    }

	/**
     * Creates/uses O.S. syslog as backend `writer`
	 */
    function logger_syslog(
        $logOpts = \LOG_PID | \LOG_ODELAY | \LOG_CONS,
        $facility = \LOG_USER,
        $levels = self::ALL,
        callable $formatter = null)
    {
        return \logger_instance()->syslogWriter($logOpts, $facility, $levels, $formatter);
    }

	/**
     * Creates/uses O.S. error_log as backend `writer`
	 */
    function logger_errorlog($type = 0, $levels = self::ALL, callable $formatter = null)
    {
        return \logger_instance()->errorLogWriter($type, $levels, $formatter);
    }

	/**
     * Creates/uses memory/array as backend `writer`
	 */
    function logger_memory(array &$array = null, $levels = self::ALL, $interval = 1, callable $formatter = null)
    {
        return \logger_instance()->arrayWriter($array, $levels, $interval, $formatter);
    }

	/**
     * Creates/uses an file/stream as backend `writer`
     * 
	 * - This function needs to be prefixed with `yield`
	 */
    function logger_stream($stream = 'php://stdout', $levels = self::ALL, $interval = 1, callable $formatter = null)
    {
        return \logger_instance()->streamWriter($stream, $levels, $interval, $formatter);
    }

	/**
     * Creates/sends an email as backend `writer`
     *
	 * - This function needs to be prefixed with `yield`
	 */
    function logger_mail($to, $subject = '', array $headers = [], $levels = self::ALL, $interval = 1, callable $formatter = null)
    {
        return \logger_instance()->mailWriter($to, $subject, $headers, $levels, $interval, $formatter);
    }

	/**
     * Logs an EMERGENCY message.
     * Will not pause current task.
     * Will create new background task to complete the `logger` operation.
     *
	 * - This function needs to be prefixed with `yield`
	 */
    function log_emergency($message, array $context = array())
    {
        return \logger_instance()->emergency($message, $context);
    }

	/**
     * Logs an ALERT message.
     * Will not pause current task.
     * Will create new background task to complete the `logger` operation.
     *
	 * - This function needs to be prefixed with `yield`
	 */
    function log_alert($message, array $context = array())
    {
        return \logger_instance()->alert($message, $context);
    }

	/**
     * Logs an CRITICAL message.
     * Will not pause current task.
     * Will create new background task to complete the `logger` operation.
     *
	 * - This function needs to be prefixed with `yield`
	 */
    function log_critical($message, array $context = array())
    {
        return \logger_instance()->critical($message, $context);
    }

	/**
     * Logs an ERROR message.
     * Will not pause current task.
     * Will create new background task to complete the `logger` operation.
     *
	 * - This function needs to be prefixed with `yield`
	 */
    function log_error($message, array $context = array())
    {
        return \logger_instance()->error($message, $context);
    }

	/**
     * Logs an WARNING message.
     * Will not pause current task.
     * Will create new background task to complete the `logger` operation.
     *
	 * - This function needs to be prefixed with `yield`
	 */
    function log_warning($message, array $context = array())
    {
        return \logger_instance()->warning($message, $context);
    }

	/**
     * Logs an NOTICE message.
     * Will not pause current task.
     * Will create new background task to complete the `logger` operation.
     *
	 * - This function needs to be prefixed with `yield`
	 */
    function log_notice($message, array $context = array())
    {
        return \logger_instance()->notice($message, $context);
    }

	/**
     * Logs an INFO message.
     * Will not pause current task.
     * Will create new background task to complete the `logger` operation.
     *
	 * - This function needs to be prefixed with `yield`
	 */
    function log_info($message, array $context = array())
    {
        return \logger_instance()->info($message, $context);
    }

	/**
     * Logs an DEBUG message.
     * Will not pause current task.
     * Will create new background task to complete the `logger` operation.
     *
	 * - This function needs to be prefixed with `yield`
	 */
    function log_debug($message, array $context = array())
    {
        return \logger_instance()->debug($message, $context);
    }
}
