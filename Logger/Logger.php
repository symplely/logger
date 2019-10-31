<?php

declare(strict_types=1);

namespace Async\Logger;

use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;
use Psr\Log\InvalidArgumentException;
use Async\Logger\AsyncLogger;

class Logger extends AsyncLogger implements LoggerInterface
{
    const NULL      = 0;
    const DEBUG     = 0x01;
    const INFO      = 0x02;
    const NOTICE    = 0x04;
    const WARNING   = 0x08;
    const ERROR     = 0x10;
    const CRITICAL  = 0x20;
    const ALERT     = 0x40;
    const EMERGENCY = 0x80;
    const ALL       = 0xff;

    private static $levels = [
        self::DEBUG     => LogLevel::DEBUG,
        self::INFO      => LogLevel::INFO,
        self::NOTICE    => LogLevel::NOTICE,
        self::WARNING   => LogLevel::WARNING,
        self::ERROR     => LogLevel::ERROR,
        self::CRITICAL  => LogLevel::CRITICAL,
        self::ALERT     => LogLevel::ALERT,
        self::EMERGENCY => LogLevel::EMERGENCY,
    ];

    private static $loggers = [];

    private $name;

    private $handlers = [];

    private $defaultFormatter;

    private $processors = [];

    private $onClose = [];

    private $enabled = self::ALL;

    public function __construct($name)
    {
        $this->name = $name;

        $this->defaultFormatter(function ($level, $message) use ($name) {
            return \sprintf("[%s] (%s): %-10s '%s'", \date(\DATE_RFC822), $name, \strtoupper($level), $message);
        });

        if (isset(self::$loggers[$name])) {
            return $this->__constructError($name);
        }

        self::$loggers[$name] = $this;
    }

    public function __constructError($name)
    {
        yield $this->close();
        throw new InvalidArgumentException("Logger('$name') already defined");
    }

    public static function getLogger($name): LoggerInterface
    {
        if (empty($name)) {
            $path = \explode('\\', \get_called_class());
            $name = \array_pop($path);
        }

        return isset(self::$loggers[$name]) ? self::$loggers[$name] : new self($name);
    }

    public static function isLogger($name): bool
    {
        return isset(self::$loggers[$name]);
    }

    public function getName()
    {
        return $this->name;
    }

    public function defaultFormatter(callable $formatter)
    {
        $this->defaultFormatter = $formatter;

        return $this;
    }

    public function disable($levels)
    {
        $this->enabled &= ~$levels;

        return $this;
    }

    public function enable($levels)
    {
        $this->enabled |= $levels;

        return $this;
    }

    /**
     * Setup the writer handler, Set the logging level of the handler.
     */
    public function setWriter(callable $writer, $levels = self::ALL, $interval = 1, callable $formatter = null)
    {
        if (empty($formatter)) {
            $formatter = $this->defaultFormatter;
        }

        if ($interval > 1) {
            $this->handlers[] = function (
                $level,
                $message,
                array $context,
                $flush = false
            ) use ($writer, $formatter, $levels, $interval) {
                static $buffer = [];

                if ($level & $levels) {
                    $buffer[] = $formatter(self::$levels[$level], $message, $context);
                }

                while (\count($buffer) >= $interval) {
                    yield $writer(\array_splice($buffer, 0, $interval));
                }

                if ($flush && !empty($buffer)) {
                    yield $writer(\array_splice($buffer, 0));
                }
            };
        } else {
            $this->handlers[] = function ($level, $message, array $context) use ($writer, $formatter, $levels) {
                if (0 == ($level & $levels)) {
                    return;
                }

                yield $writer($formatter(self::$levels[$level], $message, $context));
            };
        }
    }

    public function log($level, $message, array $context = [])
    {
        if (false === $level = \array_search($level, self::$levels, true)) {
            yield $this->close();
            throw new InvalidArgumentException(\sprintf("Unknown logger(%s) level name: '%s'", $this->name, $level));
        }

        if (0 == ($level & $this->enabled)) {
            return;
        }

        if (isset($context)) {
            foreach ($this->processors as $key => $processor) {
                $context[$key] = $processor($context);
            }

            $message = self::interpolate((string) $message, $context);

            foreach ($this->handlers as $handler) {
                yield $handler($level, $message, $context);
            }
        }
    }

    public function onClose(callable $command)
    {
        $this->onClose[] = $command;
    }

    /**
     * Shutdown and perform any cleanup actions.
     */
    public function close()
    {
        yield $this->flush();

        foreach ($this->onClose as $command) {
            yield $command();
        }

        unset(self::$loggers[$this->name]);
    }

    /**
     * Ensure all logging output has been flushed
     */
    public function flush()
    {
        foreach ($this->handlers as $handler) {
            yield $handler(self::NULL, null, [], true);
        }
    }

    /**
     * Replaces placeholders in $string with data from $vars
     */
    private static function interpolate($string, array $vars)
    {
        if (!\preg_match_all("~\{([\w\.]+)\}~", $string, $matches, \PREG_SET_ORDER)) {
            return $string;
        }

        $replacement = [];

        foreach ($matches as list($match, $key)) {
            if (!\array_key_exists($key, $vars)) {
                continue;
            }

            $value = $vars[$key];

            if ($value instanceof \Exception) {
                $replacement[$match] = \sprintf(
                    "%s(%s at %s:%d)",
                    \get_class($value),
                    $value->getMessage(),
                    $value->getFile(),
                    $value->getLine()
                );
            } elseif (\is_object($value) && \method_exists($value, "__toString")) {
                $replacement[$match] = $value->__toString();
            } elseif (\is_bool($value) || null === $value) {
                $replacement[$match] = \var_export($value, true);
            } elseif (\is_resource($value)) {
                $replacement[$match] = \sprintf("[resource(%s)]", \get_resource_type($value));
            } elseif (\is_scalar($value)) {
                $replacement[$match] = (string) $value;
            }
        }

        return \strtr($string, $replacement);
    }

    /**
     * Adds additional context data to the level message
     *
     * @param string $key
     * @param callable $processor
     */
    public function addProcessor($key, callable $processor)
    {
        $this->processors[$key] = $processor;

        return $this;
    }

    /**
     * concrete writers
     */

    /**
     * @param string|resource id $stream
     * @param int $mask
     * @param int $interval
     * @param callable $formatter
     */
    public function streamWriter($stream = 'php://stdout', $levels = self::ALL, $interval = 1, callable $formatter = null)
    {
        if (!\is_resource($stream)) {
            $stream = @\fopen($stream, 'a');

            if (!\is_resource($stream)) {
                yield $this->close();
                throw new InvalidArgumentException(sprintf(
                    'The stream "%s" cannot be created or opened',
                    $stream
                ));
            }

            \stream_set_blocking($stream, false);
            $this->onClose(function () use ($stream) {
                return \fclose($stream);
            });
        }

        if ($interval > 1) {
            return $this->setWriter(function (array $messages) use ($stream) {
                return yield AsyncLogger::write($stream, \implode("\n", $messages) . "\n");
            }, $levels, $interval, $formatter);
        } else {
            return $this->setWriter(function ($message) use ($stream) {
                return yield AsyncLogger::write($stream, "$message\n");
            }, $levels, 1, $formatter);
        }
    }

    public function syslogWriter(
        $logOpts = \LOG_PID | \LOG_ODELAY | \LOG_CONS,
        $facility = \LOG_USER,
        $levels = self::ALL,
        callable $formatter = null
    ) {
        static $map = [
            self::DEBUG  => \LOG_DEBUG,  self::INFO      => \LOG_INFO,
            self::NOTICE => \LOG_NOTICE, self::WARNING   => \LOG_WARNING,
            self::ERROR  => \LOG_ERR,    self::CRITICAL  => \LOG_CRIT,
            self::ALERT  => \LOG_ALERT,  self::EMERGENCY => \LOG_EMERG,
        ];

        \openlog('', $logOpts, $facility);

        $this->onClose('closelog');

        foreach ($map as $level => $sysLevel) {
            if ($level & $levels) {
                $this->setWriter(function ($message) use ($sysLevel) {
                    return \syslog($sysLevel, $message);
                }, $level, 1, $formatter);
            }
        }
    }

    public function errorLogWriter($type = 0, $levels = self::ALL, callable $formatter = null)
    {
        static $types = [0 => true, 4 => true];

        if (isset($types[$type])) {
            return $this->setWriter(function ($message) use ($type) {
                return \error_log($message, $type);
            }, $levels, 1, $formatter);
        }
    }

    public function mailWriter(
        $to,
        $subject = '',
        array $headers = [],
        $levels = self::ALL,
        $interval = 1,
        callable $formatter = null
    ) {

        if (!\filter_var($to, \FILTER_VALIDATE_EMAIL)) {
            yield $this->close();
            throw new InvalidArgumentException(\sprintf('"%s" is an invalid email address', $to));
        }

        if ($interval > 1) {
            return $this->setWriter(function (array $messages) use ($to, $subject, $headers) {
                $mailer = @\mail($to, $subject, \implode("\n", $messages), \implode("\r\n", $headers));
                if (!$mailer) {
                    yield $this->close();
                    throw new \InvalidArgumentException(\error_get_last()['message']);
                }

                return $mailer;
            }, $levels, $interval, $formatter);
        } else {
            return $this->setWriter(function ($message) use ($to, $subject, $headers) {
                $mailer = @\mail($to, $subject, $message, \implode("\r\n", $headers));
                if (!$mailer) {
                    yield $this->close();
                    throw new InvalidArgumentException(\error_get_last()['message']);
                }

                return $mailer;
            }, $levels, 1, $formatter);
        }
    }

    public function arrayWriter(
        array &$array = null,
        $levels = self::ALL,
        $interval = 1,
        callable $formatter = null
    ) {
        if (!isset($array)) {
            $array = [];
        }

        if ($interval > 1) {
            return $this->setWriter(function (array $messages) use (&$array) {
                \array_push($array, ...$messages);
            }, $levels, $interval, $formatter);
        } else {
            return $this->setWriter(function ($message) use (&$array) {
                $array[] = $message;
            }, $levels, 1, $formatter);
        }
    }

    /**
     * concrete context processors
     */
    public function addUniqueId($prefix = '')
    {
        return $this->addProcessor('unique_id', function () use ($prefix) {
            return \uniqid($prefix);
        });
    }

    public function addPid()
    {
        return $this->addProcessor('pid', function () {
            return \getmypid();
        });
    }

    public function addTimestamp($micro = false)
    {
        return $this->addProcessor('timestamp', function () use ($micro) {
            return $micro ? \microtime(true) : \time();
        });
    }

    public function addMemoryUsage($format = null, $real = false, $peak = false)
    {
        if (isset($format) && !\preg_match('~^(K|M|G)?B$~', $format)) {
            yield $this->close();
            throw new \InvalidArgumentException("Unknown memory format: '$format'");
        }

        return $this->addProcessor('memory_usage', function () use ($format, $real, $peak) {
            $memory_usage = $peak ? \memory_get_peak_usage($real) : \memory_get_usage($real);
            switch (\strtoupper($format)) {
                case 'GB':
                    $memory_usage /= 1024;
                    // no break
                case 'MB':
                    $memory_usage /= 1024;
                    // no break
                case 'KB':
                    $memory_usage /= 1024;
                    // no break
            }

            return $format ? \sprintf("%.3f $format", $memory_usage) : $memory_usage;
        });
    }

    public function addPhpSapi()
    {
        return $this->addProcessor('php_sapi', function () {
            return \php_sapi_name();
        });
    }

    public function addPhpVersion()
    {
        return $this->addProcessor('php_version', function () {
            return \PHP_VERSION;
        });
    }
}
