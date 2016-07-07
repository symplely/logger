<?php
namespace def\Logger;

use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\InvalidArgumentException;

class Logger implements LoggerInterface
{
    const DEBUG     = 0x01;
    const INFO      = 0x02;
    const NOTICE    = 0x04;
    const WARNING   = 0x08;
    const ERROR     = 0x10;
    const CRITICAL  = 0x20;
    const ALERT     = 0x40;
    const EMERGENCY = 0x80;

    private $levels = [
        LogLevel::DEBUG     => self::DEBUG,
        LogLevel::INFO      => self::INFO,
        LogLevel::NOTICE    => self::NOTICE,
        LogLevel::WARNING   => self::WARNING,
        LogLevel::ERROR     => self::ERROR,
        LogLevel::CRITICAL  => self::CRITICAL,
        LogLevel::ALERT     => self::ALERT,
        LogLevel::EMERGENCY => self::EMERGENCY,
    ];

    const ALL       = -1;
    const NULL      =  0;

    const PSR_ALL   = 0xff;

    private static $loggers = [];

    private static $registerLoggers = true;

    /**
     * @var string
     */
    private $channel;

    /**
     * @var callable
     */
    private $formatter;

    private $enabled = self::ALL;

    /**
     * @var callable[]
     */
    private $handlers = [];

    /**
     * @var callable[]
     */
    private $processors = [];

    /**
     * @var boolean
     */
    private $flush = false;

    /**
     * @var callable[]
     */
    private $after = [];


    use LoggerTrait;

    /**
     * @param boolean $registerLoggers
     */
    public static function setRegisterLoggers($registerLoggers = true)
    {
        self::$registerLoggers = $registerLoggers;
    }

    /**
     * @param string $channel
     */
    public static function getLogger($channel)
    {
        return isset(self::$loggers[$channel]) ? self::$loggers[$channel] : new self($channel);
    }

    public function __construct($channel, ...$levels)
    {
        $this->channel = $channel;

        $level = \max($this->levels);

        foreach (\array_map('strtolower', $levels) as $levelname) {
            if (!isset($this->levels[$levelname])) {
                $this->levels[$levelname] = $level <<= 1;
            }
        }

        $this->setDefaultFormatter(function ($levelname, $message, array $context) {
            return "{$this->channel}: [$levelname] '$message'";
        });

        if (self::$registerLoggers) {
            self::$loggers[$channel] = $this;
        }
    }

    public function setDefaultFormatter(callable $formatter)
    {
        $this->formatter = $formatter;
    }

    public function disable($mask)
    {
        $this->enabled &= ~$mask;
    }

    public function enable($mask)
    {
        $this->enabled |= $mask;
    }

    public function enabled($mask)
    {
        return $mask == ($this->enabled & $mask);
    }

    public function setHandler(callable $handler, callable $formatter = null, $mask = self::ALL, $interval = 1)
    {
        if (!isset($formatter)) {
            $formatter = $this->formatter;
        }

        if ($interval > 1) {
            $this->handlers[] = function (array $records) use ($handler, $formatter, $mask, $interval) {
                static $buffer = [];

                foreach ($records as list($level, $levelname, $message, $context)) {
                    if ($level & $mask == 0) {
                        continue;
                    }

                    $buffer[] = $formatter($levelname, $message, $context);
                }

                while (count($buffer) >= $interval) {
                    $handler(\array_splice($buffer, 0, $interval));
                }

                if ($this->flush && !empty($buffer)) {
                    $handler(\array_splice($buffer, 0));
                }
            };
        } else {
            $this->handlers[] = function (array $records) use ($handler, $formatter, $mask) {
                foreach ($records as list($level, $levelname, $message, $context)) {
                    if ($level & $mask) {
                        $handler($formatter($levelname, $message, $context));
                    }
                }
            };
        }
    }

    public function log($levelname, $message, array $context = [])
    {
        if (!isset($this->levels[$levelname])) {
            throw new InvalidArgumentException("Invalid '{$this->channel}' logger level name: {$levelname}");
        }

        return $this->message($this->levels[$levelname], $message, $context);
    }

    public function message($mask, $message, array $context = [])
    {
        foreach ($this->processors as $key => $processor) {
            $context[$key] = $processor($context);
        }

        $message = self::interpolate((string) $message, $context);

        $records = [];

        foreach ($this->levels as $levelname => $level) {
            if (($level & $this->enabled & $mask)) {
                $records[] = [$level, $levelname, $message, $context];
            }
        }

        if (!empty($records)) {
            foreach ($this->handlers as $handler) {
                $handler($records);
            }
        }
    }

    private static function interpolate($string, array $vars)
    {
        if (!preg_match_all('~\{(?<key>[\w\.]+)\}~', $string, $matches)) {
            return $string;
        }

        $replacement = [];

        foreach ($vars as $key => $value) {
            if (!\in_array($key, $matches['key'])) {
                continue;
            }

            if (\is_array($value) || (\is_object($value) && !\method_exists($value, '__toString'))) {
                continue;
            }

            $replacement['{' . $key . '}'] = (string) $value;
        }

        return \strtr($string, $replacement);
    }

    /**
     * @param string $key
     * @param callable $processor
     */
    public function addContextProcessor($key, callable $processor)
    {
        $this->processors[$key] = $processor;
    }

    public function after(callable $command)
    {
        $this->after[] = $command;
    }

    /**
     * flushes local messages buffers
     */
    public function flush()
    {
        $this->flush = true;

        foreach ($this->handlers as $handler) {
            $handler([]);
        }

        $this->flush = false;
    }

    public function close()
    {
        $this->flush();

        foreach ($this->after as $command) {
            $command();
        }
    }

    /**
     * concrete handlers
     */

    /**
     * @param string|resource id $stream
     * @param callable $formatter
     * @param int $mask
     * @param int $interval
     */
    public function setStreamHandler($stream, callable $formatter = null, $mask = self::ALL, $interval = 1)
    {
        if (!\is_resource($stream)) {
            $stream = \fopen($stream, 'a');

            $this->after(function () use ($stream) {
                return \fclose($stream);
            });
        }

        if ($interval > 1) {
            return $this->setHandler(function (array $messages) use ($stream) {
                return \fwrite($stream, implode("\n", $messages) . "\n");
            }, $formatter, $mask, $interval);
        } else {
            return $this->setHandler(function ($message) use ($stream) {
                return \fwrite($stream, "$message\n");
            }, $formatter, $mask);
        }
    }

    public function setSyslogHandler(
        $logopts = \LOG_PID,
        $facility = \LOG_USER,
        callable $formatter = null,
        $mask = self::PSR_ALL
    ) {
        static $levels = [
            self::DEBUG  => \LOG_DEBUG,  self::INFO      => \LOG_INFO,
            self::NOTICE => \LOG_NOTICE, self::WARNING   => \LOG_WARNING,
            self::ERROR  => \LOG_ERR,    self::CRITICAL  => \LOG_CRIT,
            self::ALERT  => \LOG_ALERT,  self::EMERGENCY => \LOG_EMERG,
        ];

        \openlog('', $logopts, $facility);

        $this->after('closelog');

        $mask &= self::PSR_ALL;

        foreach ($this->levels as $level) {
            if ($level & $mask) {
                $this->setHandler(function ($message) use ($level, $levels) {
                    return \syslog($levels[$level], $message);
                }, $formatter, $level);
            }
        }
    }

    public function setErrorLogHandler($type = 0, callable $formatter = null, $mask = self::ALL)
    {
        static $types = [0 => true, 4 => true];

        if (isset($types[$type])) {
            return $this->setHandler(function ($message) use ($type) {
                return \error_log($message, $type);
            }, $formatter, $mask);
        }
    }

    public function setMailHandler(
        $to,
        $subject,
        array $headers = [],
        callable $formatter = null,
        $mask = self::ALL,
        $interval = 1
    ) {
        if ($interval > 1) {
            return $this->setHandler(function (array $messages) use ($to, $subject, $headers) {
                return \mail($to, $subject, implode("\n", $messages), implode("\r\n", $headers));
            }, $formatter, $mask, $interval);
        } else {
            return $this->setHandler(function ($message) use ($to, $subject, $headers) {
                return \mail($to, $subject, $message, implode("\r\n", $headers));
            }, $formatter, $mask);
        }
    }

    public function setArrayHandler(array &$array = null, callable $formatter = null, $mask = self::ALL, $interval = 1)
    {
        if (!isset($array)) {
            $array = [];
        }

        if ($interval > 1) {
            return $this->setHandler(function (array $messages) use (&$array) {
                $array = \array_merge($array, $messages);
            }, $formatter, $mask, $interval);
        } else {
            return $this->setHandler(function ($message) use (&$array) {
                $array[] = $message;
            }, $formatter, $mask);
        }
    }

    /**
     * concrete context processors
     */

    public function addUniqueId($prefix = '')
    {
        return $this->addContextProcessor('unique_id', function () use ($prefix) {
            return \uniqid($prefix);
        });
    }

    public function addPid()
    {
        return $this->addContextProcessor('pid', function () {
            return \getmypid();
        });
    }

    public function addTimestamp($micro = false)
    {
        return $this->addContextProcessor('timestamp', function () use ($micro) {
            return $micro ? \microtime(true) : \time();
        });
    }

    public function addMemoryUsage($format = null, $real = false, $peak = false)
    {
        if (isset($format) && !preg_match('~^(K|M|G)?B$~', $format)) {
            throw new \InvalidArgumentException("Unknown memory format: '$format'");
        }

        return $this->addContextProcessor('memory_usage', function () use ($format, $real, $peak) {
            $memory_usage = $peak ? \memory_get_peak_usage($real) : \memory_get_usage($real);
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

            return $format ? \sprintf("%.3f $format", $memory_usage) : $memory_usage;
        });
    }

    public function addPhpSapi()
    {
        return $this->addContextProcessor('php_sapi', function () {
            return \PHP_SAPI;
        });
    }

    public function addPhpVersion()
    {
        return $this->addContextProcessor('php_version', function () {
            return \PHP_VERSION;
        });
    }
}
