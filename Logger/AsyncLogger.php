<?php

declare(strict_types=1);

namespace Async\Logger;

use Psr\Log\LogLevel;
use Psr\Log\AbstractLogger;
use Async\Coroutine\Kernel;
use Async\Coroutine\Coroutine;
use Async\Coroutine\TaskInterface;
use Async\Coroutine\CoroutineInterface;

class AsyncLogger extends AbstractLogger
{
    /**
     * For tracking logger tasks id
     *
     * @var array
     */
    protected $loggerTaskId = [];

    /**
     * Creates an async task for a message if logging is enabled for level.
     */
    protected function _make_log_task($level, $message, array $context = array())
    {
        $loggerId = yield \await($this->log($level, $message, $context), 'true');
        $this->loggerTaskId[] = $loggerId;
    }

    /**
     * Wait for all pending logging tasks to commit, then
     * remove finish logger tasks from current logger tasks list.
     */
    public function commit()
    {
        if (\is_array($this->loggerTaskId) && (\count($this->loggerTaskId) > 0)) {
            $this->controller();
            $remove = yield \gather($this->loggerTaskId);
            foreach ($remove as $id => $null) {
                unset($this->loggerTaskId[$id]);
            }
        }
    }

    protected function controller()
    {
        /**
         * Handle not started tasks, force start.
         */
        $onNotStarted = function (TaskInterface $tasks, CoroutineInterface $coroutine) {
            // @codeCoverageIgnoreStart
            try {
                if ($tasks->getState() === 'running' || $tasks->rescheduled()) {
                    $coroutine->execute(true);
                } elseif ($tasks->isCustomState('true') && !$tasks->completed()) {
                    $coroutine->schedule($tasks);
                    $coroutine->execute(true);
                }

                if ($tasks->completed()) {
                    $tasks->customState();
                }
            } catch (\Throwable $error) {
                $tasks->setState('erred');
                $tasks->setException($error);
                $coroutine->schedule($tasks);
                $coroutine->execute(true);
            }
        };
        // @codeCoverageIgnoreEnd

        Kernel::gatherController(
            'true',
            null,
            $onNotStarted
        );
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
    { }

    public static function write($stream, $string)
    {
        yield Kernel::writeWait($stream);
        yield Coroutine::value(\fwrite($stream, $string));
    }
}
