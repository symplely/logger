<?php
namespace def\Logger\Test;

use def\Logger\Logger;
use Psr\Log\Test\LoggerInterfaceTest;

class LoggerTest extends LoggerInterfaceTest
{
    protected $logger;
    protected $logs = [];

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

        $this->logger->setArrayWriter($this->logs, Logger::ALL, 1, function ($level, $message) {
            return "$level $message";
        });
    }

    protected function tearDown()
    {
        $this->logs = [];
        $this->logger->close();
    }
}
