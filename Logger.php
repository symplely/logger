<?php
namespace def\Logger;

use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\InvalidArgumentException;

class Logger implements LoggerInterface
{
	use LoggerTrait;

	const DEBUG     = 0x01;
	const INFO      = 0x02;
	const NOTICE    = 0x04;
	const WARNING   = 0x08;
	const ERROR     = 0x10;
	const CRITICAL  = 0x20;
	const ALERT     = 0x40;
	const EMERGENCY = 0x80;

	const ALL       = -1;
	const NULL      =  0;

	const PSR_ALL   = 0xff;

	const RECORD_LEVELNAME = 0;
	const RECORD_MESSAGE   = 1;
	const RECORD_CONTEXT   = 2;
	const RECORD_LEVEL     = 3;

	protected $levels = [
		LogLevel::DEBUG     => self::DEBUG,
		LogLevel::INFO      => self::INFO,
		LogLevel::NOTICE    => self::NOTICE,
		LogLevel::WARNING   => self::WARNING,
		LogLevel::ERROR     => self::ERROR,
		LogLevel::CRITICAL  => self::CRITICAL,
		LogLevel::ALERT     => self::ALERT,
		LogLevel::EMERGENCY => self::EMERGENCY,
	];

	protected $enabled = self::ALL;

	protected $channel;

	protected $formatter;

	protected $handlers = [];

	protected $processors = [];

	protected $flush = false;

	protected $after = []; // on destruct events

	public function __construct($channel, ...$levels)
	{
		$this->channel = $channel;

		$level = \max($this->levels + [0]);  // max current levels key

		$levels = \array_map('strtolower', $levels);

		foreach($levels as $levelname) if(!isset($this->levels[$levelname])) {
			$this->levels[$levelname] = $level <<= 1 ?: ($level = 1);
		}

		$this->setDefaultFormatter(function($levelname, $message, array $context = []) {
			return "{$this->channel}: [$levelname] '$message'";
		});
	}

	public function disable($mask = self::ALL)
	{
		$this->enabled &= ~$mask;	
	}

	public function enable($mask = self::ALL)
	{
		$this->enabled |= $mask;
	}

	public function enabled($mask = self::ALL)
	{
		return $mask == ($this->enabled & $mask);
	}

	public function setDefaultFormatter(callable $formatter)
	{
		$this->formatter = $formatter;
	}

	public function setHandler(callable $handler, callable $formatter = null, $mask = self::ALL, $interval = 1)
	{
		if(!isset($formatter))
			$formatter = $this->formatter;

		if($interval > 1)
			$this->handlers[] = function(array $records) use($handler, $formatter, $mask, $interval) {
				static $buffer = [];

				$records = \array_filter($records, function(array $record) use($mask) {
					return $mask & $record[self::RECORD_LEVEL];
				});

				$buffer  = \array_merge($buffer, \array_map(function(array $record) use($formatter) {
					return $formatter($record[self::RECORD_LEVELNAME], $record[self::RECORD_MESSAGE], $record[self::RECORD_CONTEXT]);
				}, $records));

				while(count($buffer) >= $interval)
					$handler(\array_splice($buffer, 0, $interval));

				if($this->flush && !empty($buffer))
					$handler(\array_splice($buffer, 0));
			};
		else
			$this->handlers[] = function(array $records) use($handler, $formatter, $mask) {
				foreach($records as list($levelname, $message, $context, $level)) if($mask & $level) {
					$handler($formatter($levelname, $message, $context));
				};
			};
	}

	public function bindLogger(LoggerInterface $logger, $mask = self::PSR_ALL)
	{
		$this->handlers[] = function(array $records) use($logger, $mask) {
			foreach($records as list($levelname, $message, $context, $level)) if($level == ($mask & $level)) {
				$logger->log($levelname, $message, $context);
			}
		};
	}

	public function log($levelname, $message, array $context = [])
	{
		if(!isset($this->levels[$levelname]))
			throw new InvalidArgumentException("Invalid '{$this->channel}' logger level name: {$levelname}");

		return $this->message($this->levels[$levelname], $message, $context);
	}

	public function message($mask, $message, array $context = [])
	{
		foreach($this->processors as $key => $processor) {
			$context[$key] = $processor($context, $key);
		}

		$message = static::interpolate((string) $message, $context);

		$records = [];

		foreach($this->levels as $levelname => $level) if(($mask & $level) && $this->enabled($level)) {
			$records[] = [$levelname, $message, $context, $level];
		};

		if(!empty($records))
			foreach($this->handlers as $handler) {
				$handler($records);
			}
	}

	public function __call($levelname, array $args)
	{
		return $this->log($levelname, \array_shift($args), (array) \array_shift($args));
	}

	protected static function interpolate($message, array $context)
	{
		if(!\preg_match_all('~\{(?<key>[\w\.]+)\}~', $message, $matches)) {
			return $message;
		}

		$replacement = [];

		foreach($context as $key => $value) if(\in_array($key, $matches['key'])) {
			if(\is_array($value) || ( \is_object($value) && !\method_exists($value, '__toString') )) {
				continue;
			}

			$replacement['{' . $key . '}'] = (string) $value; 
		}

		return \strtr($message, $replacement);
	}

	public function addContextProcessor($key, callable $processor)
	{
		$this->processors[$key] = $processor;
	}

	public function after(callable $command)
	{
		$this->after[] = $command;
	}

	public function flush()
	{
		$this->flush = true;	
		foreach($this->handlers as $handler) $handler([]);
		$this->flush = false;
	}

	public function __destruct()
	{
		$this->flush();

		foreach($this->after as $command) {
			$command();
		}
	}

	public function setStreamHandler($stream, callable $formatter = null, $mask = self::ALL, $interval = 1)
	{
		if(!\is_resource($stream)) {
			$stream = \fopen($stream, 'a');

			$this->after(function() use($stream) {
				return \fclose($stream);
			});
		}

		if($interval > 1)
			return $this->setHandler(function(array $messages) use($stream) {
				return \fwrite($stream, implode("\n", $messages) . "\n");
			}, $formatter, $mask, $interval);

		return $this->setHandler(function($message) use($stream) {
			return \fwrite($stream, "$message\n");
		}, $formatter, $mask);
	}

	public function setSyslogHandler($logopts = \LOG_PID, $facility = \LOG_USER, callable $formatter = null, $mask = self::PSR_ALL)
	{
		static $levels = [
			LogLevel::DEBUG  => \LOG_DEBUG,  LogLevel::INFO      => \LOG_INFO,
			LogLevel::NOTICE => \LOG_NOTICE, LogLevel::WARNING   => \LOG_WARNING,
			LogLevel::ERROR  => \LOG_ERR,    LogLevel::CRITICAL  => \LOG_CRIT,
			LogLevel::ALERT  => \LOG_ALERT,  LogLevel::EMERGENCY => \LOG_EMERG,
		];

		\openlog('', $logopts, $facility);

		$this->after('closelog');

		$mask &= self::PSR_ALL;

		foreach($this->levels as $levelname => $level) if($level & $mask) {
			$this->setHandler(function($message) use($levelname, $levels) {
				return \syslog($levels[$levelname], $message);
			}, $formatter, $level);
		}
	}

	public function setErrorLogHandler($type = 0, callable $formatter = null, $mask = self::ALL)
	{
		static $types = [0 => true, 4 => true];

		if(isset($types[$type]))
			return $this->setHandler(function($message) use($type) {
				return \error_log($message, $type);
			}, $formatter, $mask);
	}

	public function setMailHandler($to, $subject, array $headers = [], callable $formatter = null, $mask = self::ALL, $interval = 1)
	{
		if($interval > 1)
			return $this->setHandler(function(array $messages) use($to, $subject, $headers) {
				return \mail($to, $subject, implode("\n", $messages), implode("\r\n", $headers));
			}, $formatter, $mask, $interval);

		return $this->setHandler(function($message) use($to, $subject, $headers) {
			return \mail($to, $subject, $message, implode("\r\n", $headers));
		}, $formatter, $mask);
	}

	public function setArrayHandler(array &$array = null, callable $formatter = null, $mask = self::ALL, $interval = 1)
	{
		if(!isset($array)) {
			$array = [];
		}

		if($interval > 1)
			return $this->setHandler(function(array $messages) use(&$array) {
				$array = \array_merge($array, $messages);
			}, $formatter, $mask, $interval);

		return $this->setHandler(function($message) use(&$array) {
			$array[] = $message;
		}, $formatter, $mask);
	}

	public function addTag($tag)
	{
		return $this->addContextProcessor('tag', function() use($tag) {
			return $tag;
		});
	}

	public function addUniqueId($prefix = '')
	{
		return $this->addContextProcessor('unique_id', function() use($prefix) {
			return \uniqid($prefix);
		});
	}

	public function addPid()
	{
		return $this->addContextProcessor('pid', function() {
			return \getmypid();
		});
	}

	public function addTimestamp($micro = false)
	{
		return $this->addContextProcessor('timestamp', function() use($micro) {
			return $micro ? \microtime(true) : \time();
		});
	}

	public function addMemoryUsage($format = null, $real = false, $peak = false)
	{
		if(isset($format) && !preg_match('~^(K|M|G)?B$~', $format))
			throw new \InvalidArgumentException("Unknown memory format: '$format'");

		return $this->addContextProcessor('memory_usage', function() use($format, $real, $peak) {
			$memory_usage = $real ? \memory_get_peak_usage($real) : \memory_get_usage($real);
			switch($format) {
				case 'GB': $memory_usage /= 1024;
				case 'MB': $memory_usage /= 1024;
				case 'KB': $memory_usage /= 1024;
			}

			return $format ? \sprintf("%.3f $format", $memory_usage) : $memory_usage;
		});
	}

	public function addPhpSapi()
	{
		return $this->addContextProcessor('php_sapi', function() {
			return \PHP_SAPI;
		});
	}

	public function addPhpVersion()
	{
		return $this->addContextProcessor('php_version', function() {
			return \PHP_VERSION;
		});
	}
}
