<?php

namespace Async\Logger;

use Psr\Log\LogLevel;
use Psr\Log\AbstractLogger;

class AsyncLogger extends AbstractLogger
{
    /**
     * Creates an async task for a message if logging is enabled for level.
     */
    protected function _make_log_task($level, $message, array $context = array())
    {
        $task = $this->log($level, $message, $context);
        yield \await($task);
    }

    public function emergency($message, array $context = array())
    {
        return $this->_make_log_task(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert($message, array $context = array())
    {
        return $this->_make_log_task(LogLevel::ALERT, $message, $context);
    }

    public function critical($message, array $context = array())
    {
        return $this->_make_log_task(LogLevel::CRITICAL, $message, $context);
    }

    public function error($message, array $context = array())
    {
        return $this->_make_log_task(LogLevel::ERROR, $message, $context);
    }

    public function warning($message, array $context = array())
    {
        return $this->_make_log_task(LogLevel::WARNING, $message, $context);
    }

    public function notice($message, array $context = array())
    {
        return $this->_make_log_task(LogLevel::NOTICE, $message, $context);
    }

    public function info($message, array $context = array())
    {
        return $this->_make_log_task(LogLevel::INFO, $message, $context);
    }

    public function debug($message, array $context = array())
    {
        return $this->_make_log_task(LogLevel::DEBUG, $message, $context);
    }
    
    public function log($level, $message, array $context = array())
    {
    }
}
