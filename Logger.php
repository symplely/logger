<?php
namespace def\Logger;

use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\InvalidArgumentException;

class Logger implements LoggerInterface
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

    public static function getLogger($name)
    {
        return isset(self::$loggers[$name]) ? self::$loggers[$name] : new self($name);
    }

    use LoggerTrait;

    private $name;

    private $handlers = [];

    private $defaultFormatter;

    private $processors = [];

    private $onClose = [];

    private $enabled = self::ALL;

    public function __construct($name)
    {
        $this->name = $name;

        $this->setDefaultFormatter(function ($levelname, $message) use ($name) {
            return sprintf("[%s] (%s): %-10s '%s'", date(DATE_RFC822), $name, strtoupper($levelname), $message);
        });

        if (isset(self::$loggers[$name])) {
            throw new InvalidArgumentException("Logger('$name') already defined");
        }

        self::$loggers[$name] = $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setDefaultFormatter(callable $formatter)
    {
        $this->defaultFormatter = $formatter;
    }

    public function disable($levels)
    {
        $this->enabled &= ~$levels;
    }

    public function enable($levels)
    {
        $this->enabled |= $levels;
    }

    public function levels(...$levelnames)
    {
        return array_reduce($levelnames, function ($levels, $levelname) {
            return $levels | (int) array_search($levelname, self::$levels, true);
        }, self::NULL);
    }

    public function setWriter(callable $writer, $levels = self::ALL, $interval = 1, callable $formatter = null)
    {
        if (!isset($formatter)) {
            $formatter = $this->defaultFormatter;
        }

        if ($interval > 1) {
            $this->handlers[] = function (
                $level,
                $message,
                array $context,
                $flush = false
            ) use (
                $writer,
                $formatter,
                $levels,
                $interval
            ) {
                static $buffer = [];

                if ($level & $levels) {
                    $buffer[] = $formatter(self::$levels[$level], $message, $context);
                }

                while (count($buffer) >= $interval) {
                    $writer(array_splice($buffer, 0, $interval));
                }

                if ($flush && !empty($buffer)) {
                    $writer(array_splice($buffer, 0));
                }
            };
        } else {
            $this->handlers[] = function ($level, $message, array $context) use ($writer, $formatter, $levels) {
                if (0 == ($level & $levels)) {
                    return;
                }

                $writer($formatter(self::$levels[$level], $message, $context));
            };
        }
    }

    public function flush()
    {
        foreach ($this->handlers as $handler) {
            $handler(self::NULL, null, [], true);
        }
    }

    public function log($levelname, $message, array $context = [])
    {
        if (false === $level = array_search($levelname, self::$levels, true)) {
            throw new InvalidArgumentException(sprintf("Unknown logger(%s) level name: '%s'", $this->name, $levelname));
        }

        if (0 == ($level & $this->enabled)) {
            return;
        }

        foreach ($this->processors as $key => $processor) {
            $context[$key] = $processor($context);
        }

        $message = self::interpolate((string) $message, $context);

        foreach ($this->handlers as $handler) {
            $handler($level, $message, $context);
        }
    }

    public function onClose(callable $command)
    {
        $this->onClose[] = $command;
    }

    public function close()
    {
        $this->flush();

        foreach ($this->onClose as $command) {
            $command();
        }

        unset(self::$loggers[$this->name]);
    }

    /**
     * TODO: improve
     */
    private static function interpolate($string, array $vars)
    {
        if (!preg_match_all("~\{([\w\.]+)\}~", $string, $matches, PREG_SET_ORDER)) {
            return $string;
        }

        $replacement = [];

        foreach ($matches as list($match, $key)) {
            if (!array_key_exists($key, $vars)) {
                continue;
            }

            $value = $vars[$key];

            if ($value instanceof \Exception) {
                $replacement[$match] = sprintf("%s(%s)", get_class($value), $value->getMessage());
            } elseif (is_object($value) && method_exists($value, "__toString")) {
                $replacement[$match] = $value->__toString();
            } elseif (is_bool($value) || null === $value) {
                $replacement[$match] = var_export($value, true);
            } elseif (is_scalar($value)) {
                $replacement[$match] = (string) $value;
            }
        }

        return strtr($string, $replacement);
    }

    /**
     * @param string $key
     * @param callable $processor
     */
    public function addContextProcessor($key, callable $processor)
    {
        $this->processors[$key] = $processor;
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
    public function setStreamWriter($stream, $levels = self::ALL, $interval = 1, callable $formatter = null)
    {
        if (!is_resource($stream)) {
            $stream = fopen($stream, 'a');

            $this->onClose(function () use ($stream) {
                return fclose($stream);
            });
        }

        if ($interval > 1) {
            return $this->setWiter(function (array $messages) use ($stream) {
                return fwrite($stream, implode("\n", $messages) . "\n");
            }, $levels, $interval, $formatter);
        } else {
            return $this->setWriter(function ($message) use ($stream) {
                return fwrite($stream, "$message\n");
            }, $levels, 1, $formatter);
        }
    }

    public function setSyslogWriter(
        $logopts = LOG_PID,
        $facility = LOG_USER,
        $levels = self::ALL,
        callable $formatter = null
    ) {
        static $map = [
            self::DEBUG  => LOG_DEBUG,  self::INFO      => LOG_INFO,
            self::NOTICE => LOG_NOTICE, self::WARNING   => LOG_WARNING,
            self::ERROR  => LOG_ERR,    self::CRITICAL  => LOG_CRIT,
            self::ALERT  => LOG_ALERT,  self::EMERGENCY => LOG_EMERG,
        ];

        openlog('', $logopts, $facility);

        $this->onClose('closelog');

        foreach ($map as $level => $syslevel) {
            if ($level & $levels) {
                $this->setWriter(function ($message) use ($syslevel) {
                    return syslog($syslevel, $message);
                }, $level, 1, $formatter);
            }
        }
    }

    public function setErrorLogWriter($type = 0, $levels = self::ALL, callable $formatter = null)
    {
        static $types = [0 => true, 4 => true];

        if (isset($types[$type])) {
            return $this->setWriter(function ($message) use ($type) {
                return \error_log($message, $type);
            }, $levels, 1, $formatter);
        }
    }

    public function setMailWriter(
        $to,
        $subject,
        array $headers = [],
        $levels = self::ALL,
        $interval = 1,
        callable $formatter = null
    ) {
        if ($interval > 1) {
            return $this->setWriter(function (array $messages) use ($to, $subject, $headers) {
                return mail($to, $subject, implode("\n", $messages), implode("\r\n", $headers));
            }, $levels, $interval, $formatter);
        } else {
            return $this->setWriter(function ($message) use ($to, $subject, $headers) {
                return mail($to, $subject, $message, implode("\r\n", $headers));
            }, $levels, 1, $formatter);
        }
    }

    public function setArrayWriter(
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
                array_push($array, ...$messages);
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
        return $this->addContextProcessor('unique_id', function () use ($prefix) {
            return uniqid($prefix);
        });
    }

    public function addPid()
    {
        return $this->addContextProcessor('pid', function () {
            return getmypid();
        });
    }

    public function addTimestamp($micro = false)
    {
        return $this->addContextProcessor('timestamp', function () use ($micro) {
            return $micro ? microtime(true) : time();
        });
    }

    public function addMemoryUsage($format = null, $real = false, $peak = false)
    {
        if (isset($format) && !preg_match('~^(K|M|G)?B$~', $format)) {
            throw new \InvalidArgumentException("Unknown memory format: '$format'");
        }

        return $this->addContextProcessor('memory_usage', function () use ($format, $real, $peak) {
            $memory_usage = $peak ? memory_get_peak_usage($real) : memory_get_usage($real);
            switch ($format) {
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

            return $format ? sprintf("%.3f $format", $memory_usage) : $memory_usage;
        });
    }

    public function addPhpSapi()
    {
        return $this->addContextProcessor('php_sapi', function () {
            return php_sapi_name();
        });
    }

    public function addPhpVersion()
    {
        return $this->addContextProcessor('php_version', function () {
            return PHP_VERSION;
        });
    }
}
