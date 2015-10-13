<?php
namespace def\Logger\Test;

use def\Logger\Logger;
use Psr\Log\Test\LoggerInterfaceTest;

class LoggerTest extends LoggerInterfaceTest
{
	protected $logger, $logs = [];

	public function getLogger()
	{
		return $this->logger;
	}

	public function getLogs()
	{
		return $this->logs;
	}

	protected function setUp()
	{
		$this->logger = new Logger("php-app");

		$this->logger->setArrayHandler($this->logs, function($levelname, $message) {
			return "$levelname $message";
		});
	}

	protected function tearDown()
	{
		$this->logs = [];
	}
}
