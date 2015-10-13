<?php
namespace def\Logger;

interface LoggerAwareInterface
{
	public function setLogger(Logger $logger);
}
