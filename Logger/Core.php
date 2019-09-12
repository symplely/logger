<?php

declare(strict_types = 1);

use Async\Logger\Logger;

if (!\function_exists('create_logger')) {
    function create_logger(string $name)
    {
        global $__logger__;

        if (empty($__logger__))
            $__logger__ = Logger::getLogger($name);

        return $__logger__;
    }
}
