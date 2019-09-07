<?php
namespace Async\Tests;

use Async\Logger\Logger;
use Psr\Log\LogLevel;
use Psr\Log\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Provides a base test class for ensuring compliance with the LoggerInterface.
 *
 * Implementors can extend the class and implement abstract methods to run this
 * as part of their test suite.
 */
class LoggerTest extends TestCase
{
    protected $logger;
    protected $logs = [];
    protected $dest = 'stdout';
	/**
	 * @var string
	 */
	private $testFile = 'logger-test.log';

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * This must return the log messages in order.
     *
     * The simple formatting of the messages is: "<LOG LEVEL> <MESSAGE>".
     *
     * Example ->error('Foo') would yield "error Foo".
     *
     * @return string[]
     */
    public function getLogs()
    {
        return $this->logs;
    }

    protected function setUp(): void
    {
		if (file_exists(__DIR__ .\DS. $this->testFile)) {
			unlink(__DIR__ .\DS. $this->testFile);
        }

        $this->logger = new Logger("php-app");

        $this->logger->arrayWriter($this->logs, Logger::ALL, 1, function ($level, $message) {
            return "$level $message";
        });
    }

    protected function tearDown(): void
    {
		if (file_exists(__DIR__ .\DS. $this->testFile)) {
			unlink(__DIR__ .\DS. $this->testFile);
        }

        $this->logs = [];
        $this->logger->close();
    }

	public function testCreateInstance()
	{
        $log = new Logger("log-app");
		$log->streamWriter(__DIR__ .\DS. $this->testFile);
		$this->assertInstanceOf(Logger::class, $log);
		$this->assertFileExists(__DIR__ .\DS. $this->testFile);
		$log->close();
	}

    public function testThrowsInvalidArgumentExceptionWhenFileCannotBeCreated()
    {
        $log = new Logger("log-app");
        $this->expectException(InvalidArgumentException::class);
		$log->streamWriter('/', Logger::DEBUG);
    }

	public function testLogWithoutContext()
	{
        $log = new Logger("log-app");
		$log->streamWriter(__DIR__ .\DS. $this->testFile, Logger::DEBUG);
		$log->log(LogLevel::DEBUG, 'A log message');

		$content = file_get_contents(__DIR__ .\DS. $this->testFile);
		$this->assertRegExp('/[{^\[.+\] (\w+) (.+)?} DEBUG A log message]/', $content);
		$log->close();
    }

	public function testLogLevelInvalidArgumentException()
	{
        $log = new Logger("log-app");
		$log->streamWriter(__DIR__ .\DS. $this->testFile, Logger::DEBUG);
        $this->expectException(InvalidArgumentException::class);
		$log->log('LogLevel', 'A log message');
		$log->close();
	}

	public function testLogWithContext()
	{
        $log = new Logger("log-app");
		$log->streamWriter(__DIR__ .\DS. $this->testFile, Logger::WARNING, 2);
		$log->log(LogLevel::WARNING, 'Hello {name}', array('name' => 'World'));
		$log->log(LogLevel::WARNING, 'hi {name}', array('name' => 'Planet'));

		$content = file_get_contents(__DIR__ .\DS. $this->testFile);
		$this->assertRegExp('/[{^\[.+\] (\w+) (.+)?} WARNING Hello World]/', $content);
		$this->assertRegExp('/[{^\[.+\] (\w+) (.+)?} WARNING Hi Planet]/', $content);
		$log->close();
    }

	public function testLogEmergencyUniqid()
	{
        $log = new Logger("log-app");
		$log->streamWriter(__DIR__ .\DS. $this->testFile, Logger::EMERGENCY);
		$log->addUniqueId();
		$log->emergency('This is an emergency with {unique_id}');

        $content = file_get_contents(__DIR__ .\DS. $this->testFile);
        $re1='(\\[.*?\\])';	# Square Braces 1
        $re2='(\\s+)';	# White Space 1
        $re3='(\\(.*\\))';	# Round Braces 1
        $re4='(.)';	# Any Single Character 1
        $re5='(\\s+)';	# White Space 2
        $re6='((?:[a-z][a-z]+))';	# Word 1
        $re7='(\\s+)';	# White Space 3
        $re8='(.)';	# Any Single Character 2
        $re9='((?:[a-z][a-z]+))';	# Word 2
        $re10='(\\s+)';	# White Space 4
        $re11='((?:[a-z][a-z]+))';	# Word 3
        $re12='(\\s+)';	# White Space 5
        $re13='((?:[a-z][a-z]+))';	# Word 4
        $re14='(\\s+)';	# White Space 6
        $re15='((?:[a-z][a-z]+))';	# Word 5
        $re16='(\\s+)';	# White Space 7
        $re17='((?:[a-z][a-z]+))';	# Word 6
        $re18='(\\s+)';	# White Space 8
        $re19='.*?';	# Non-greedy match on filler
        $re20='((?:[a-z][a-z]*[0-9]+[a-z0-9]*))';	# Alphanum 1

		$this->assertRegExp(
            "/".$re1.$re2.$re3.$re4.$re5.$re6.$re7.$re8.$re9.$re10.$re11.$re12.$re13.$re14.$re15.$re16.$re17.$re18.$re19.$re20."/is", $content);
		$log->close();
    }

	public function testLogAlert()
	{
        $log = new Logger("log-app");
		$log->streamWriter(__DIR__ .\DS. $this->testFile, Logger::ALERT);
		$log->addPid();
		$log->alert('This is an alert with {pid}');

		$content = file_get_contents(__DIR__ .\DS. $this->testFile);
        $re1='(\\[.*?\\])';	# Square Braces 1
        $re2='(\\s+)';	# White Space 1
        $re3='(\\(.*\\))';	# Round Braces 1
        $re4='(.)';	# Any Single Character 1
        $re5='(\\s+)';	# White Space 2
        $re6='((?:[a-z][a-z]+))';	# Word 1
        $re7='(\\s+)';	# White Space 3
        $re8='(.)';	# Any Single Character 2
        $re9='((?:[a-z][a-z]+))';	# Word 2
        $re10='(\\s+)';	# White Space 4
        $re11='((?:[a-z][a-z]+))';	# Word 3
        $re12='(\\s+)';	# White Space 5
        $re13='((?:[a-z][a-z]+))';	# Word 4
        $re14='(\\s+)';	# White Space 6
        $re15='((?:[a-z][a-z]+))';	# Word 5
        $re16='(\\s+)';	# White Space 7
        $re17='((?:[a-z][a-z]+))';	# Word 6
        $re18='(\\s+)';	# White Space 8
        $re19='.*?';	# Non-greedy match on filler
        $re20='(\\d+)';	# Integer Number 1
		$this->assertRegExp(
            "/".$re1.$re2.$re3.$re4.$re5.$re6.$re7.$re8.$re9.$re10.$re11.$re12.$re13.$re14.$re15.$re16.$re17.$re18.$re19.$re20."/is", $content);
		$log->close();
    }

	public function testLogCritical()
	{
        $log = new Logger("log-app");
		$log->streamWriter(__DIR__ .\DS. $this->testFile, Logger::CRITICAL);
		$log->addTimestamp(true);
		$log->critical('This is a critical situation happened at {timestamp}');

		$content = file_get_contents(__DIR__ .\DS. $this->testFile);
        $re1='(\\[.*?\\])';	# Square Braces 1
        $re2='(\\s+)';	# White Space 1
        $re3='(\\(.*\\))';	# Round Braces 1
        $re4='(.)';	# Any Single Character 1
        $re5='(\\s+)';	# White Space 2
        $re6='((?:[a-z][a-z]+))';	# Word 1
        $re7='(\\s+)';	# White Space 3
        $re8='(.)';	# Any Single Character 2
        $re9='((?:[a-z][a-z]+))';	# Word 2
        $re10='(\\s+)';	# White Space 4
        $re11='((?:[a-z][a-z]+))';	# Word 3
        $re12='(\\s+)';	# White Space 5
        $re13='((?:[a-z][a-z0-9_]*))';	# Variable Name 1
        $re14='(\\s+)';	# White Space 6
        $re15='((?:[a-z][a-z]+))';	# Word 4
        $re16='(\\s+)';	# White Space 7
        $re17='((?:[a-z][a-z]+))';	# Word 5
        $re18='(\\s+)';	# White Space 8
        $re19='((?:[a-z][a-z]+))';	# Word 6
        $re20='(\\s+)';	# White Space 9
        $re21='((?:[a-z][a-z]+))';	# Word 7
        $re22='(\\s+)';	# White Space 10
        $re23='([+-]?\\d*\\.\\d+)(?![-+0-9\\.])';	# Float 1
		$this->assertRegExp(
            "/".$re1.$re2.$re3.$re4.$re5.$re6.$re7.$re8.$re9.$re10.$re11.$re12.$re13.$re14.$re15.$re16.$re17.$re18.$re19.$re20.$re21.$re22.$re23."/is", $content);
		$log->close();
	}

	public function testLogError()
	{
        $log = new Logger("log-app");
		$log->streamWriter(__DIR__ .\DS. $this->testFile, Logger::ERROR);
		$log->error('This is an error');

		$content = file_get_contents(__DIR__ .\DS. $this->testFile);
		$this->assertRegExp(
			'/[{^\[.+\] (\w+) (.+)?} ERROR This is an error]/',
			$content
		);
		$log->close();
    }

	public function testLogWarning()
	{
        $log = new Logger("log-app");
		$log->streamWriter(__DIR__ .\DS. $this->testFile, Logger::WARNING);
		$log->warning('This is a warning');

		$content = file_get_contents(__DIR__ .\DS. $this->testFile);
		$this->assertRegExp(
			'/[{^\[.+\] (\w+) (.+)?} WARNING This is a warning]/',
			$content
		);
		$log->close();
    }

	public function testLogNotice()
	{
        $log = new Logger("log-app");
		$log->streamWriter(__DIR__ .\DS. $this->testFile, Logger::NOTICE);
		$log->notice('This is just a notice');

		$content = file_get_contents(__DIR__ .\DS. $this->testFile);
		$this->assertRegExp(
			'/[{^\[.+\] (\w+) (.+)?} NOTICE This is just a notice]/',
			$content
		);
		$log->close();
	}

	public function testLogInfo()
	{
        $log = new Logger("log-app");
		$log->streamWriter(__DIR__ .\DS. $this->testFile, Logger::INFO);
		$log->addMemoryUsage('MB');
		$log->addPhpSapi();
		$log->addPhpVersion();
		$log->info('This is an information memory usage {memory_usage}, sapi {php_sapi}, php {php_version}');

        $content = file_get_contents(__DIR__ .\DS. $this->testFile);
        $re1='(.)';	# Any Single Character 1
        $re2='((?:Tues|Thur|Thurs|Sun|Mon|Tue|Wed|Thu|Fri|Sat))';	# Day Of Week 1
        $re3='(.)';	# Any Single Character 2
        $re4='(.*?),';	# Command Seperated Values 1
        $re5='(.)';	# Any Single Character 3
        $re6='(.*?),';	# Command Seperated Values 2
        $re8='(\\s+)';	# White Space 1
        $re9='((?:[a-z][a-z]+))';	# Word 1
        $re10='(\\s+)';	# White Space 2
        $re11='((?:[0]?[1-9]|[1][012])[-:\\/.](?:(?:[0-2]?\\d{1})|(?:[3][01]{1}))[-:\\/.](?:(?:\\d{1}\\d{1})))(?![\\d])';	# MMDDYY 1
		$this->assertRegExp(
			"/".$re1.$re2.$re3.$re4.$re5.$re6.$re8.$re9.$re10.$re11."/is",
			$content
		);
		$log->close();
	}

	public function testLogDebug()
	{
        $log = new Logger("log-app");
		$log->streamWriter(__DIR__ .\DS. $this->testFile, Logger::DEBUG);
		$log->debug('This is a debug message');

		$content = file_get_contents(__DIR__ .\DS. $this->testFile);
		$this->assertRegExp(
			'/[{^\[.+\] (\w+) (.+)?} DEBUG This is a debug message]/',
			$content
		);
		$log->close();
	}

    public function testGetName()
    {
        $logger = new Logger('foo');
        $this->assertEquals('foo', $logger->getName());
        $this->assertInstanceOf(\Psr\Log\LoggerInterface::class, $logger->getLogger('foo'));
        $this->assertInstanceOf(Logger::class, $logger->getLogger('foo'));
        $logger->close();

    }

    public function testExceptionInContext()
    {
        $logger = $this->getLogger();
        $exceptionMsg = 'exceptional!';
        $exception = new \Exception($exceptionMsg);
        $input = 'foo %exception% foo';
        $context = ['exception' => $exception];
        $expected = 'foo ' . $exceptionMsg . ' foo';
        $logger->emergency($input, $context);
        $this->assertNotEmpty($expected, $this->getOnlyLoggedMessage());
        $logger->close();
    }

    public function testThrowsInvalidArgumentExceptionWhenNull()
    {
        $logger = $this->getLogger();
        $this->expectException(InvalidArgumentException::class);
        $logger->mailWriter(null);
        $logger->close();
    }

    public function testThrowsInvalidArgumentException()
    {
        $logger = $this->getLogger();
        $this->expectException(InvalidArgumentException::class);
        $logger->mailWriter('foo');
        $logger->close();
    }

    public function testConstructor()
    {
        $logger = $this->getLogger();
        $this->expectException(\InvalidArgumentException::class);
        $logger->mailWriter('foo@bar.com', null, ['Cc: some@somewhere.com']);
        $logger->info('Log me!');
        $logger->error('Log me too!');
        $logger->close();
    }

	public function testErrorLog()
	{
        \ini_set('error_log', $this->dest);
        $log = new Logger("log-app");
        $log->errorLogWriter();
		$log->debug('This is a debug message');

		$content = file_get_contents($this->dest);
		$this->assertRegExp(
			'/[{^\[.+\] (\w+) (.+)?} DEBUG This is a debug message]/',
			$content
		);
        $log->close();

        if (\file_exists($this->dest)) {
            unlink($this->dest);
        }
    }

    /**
     * @dataProvider provideLevelsAndMessages
     */
    public function testLogsAtAllLevels($level, $message)
    {
        $logger = $this->getLogger();
        $logger->{$level}($message, array('user' => 'Bob'));
        $logger->log($level, $message, array('user' => 'Bob'));

        $expected = array(
            $level.' message of level '.$level.' with context: Bob',
            $level.' message of level '.$level.' with context: Bob',
        );
        $this->assertEquals($expected, $this->getLogs());
    }

    public function provideLevelsAndMessages()
    {
        return array(
            LogLevel::EMERGENCY => array(LogLevel::EMERGENCY, 'message of level emergency with context: {user}'),
            LogLevel::ALERT => array(LogLevel::ALERT, 'message of level alert with context: {user}'),
            LogLevel::CRITICAL => array(LogLevel::CRITICAL, 'message of level critical with context: {user}'),
            LogLevel::ERROR => array(LogLevel::ERROR, 'message of level error with context: {user}'),
            LogLevel::WARNING => array(LogLevel::WARNING, 'message of level warning with context: {user}'),
            LogLevel::NOTICE => array(LogLevel::NOTICE, 'message of level notice with context: {user}'),
            LogLevel::INFO => array(LogLevel::INFO, 'message of level info with context: {user}'),
            LogLevel::DEBUG => array(LogLevel::DEBUG, 'message of level debug with context: {user}'),
        );
    }

    public function testThrowsOnInvalidLevel()
    {
        $this->expectException(InvalidArgumentException::class);
        $log = $this->getLogger();
        $logger = new Logger("php-app");
    }

    public function testContextReplacement()
    {
        $logger = $this->getLogger();
        $logger->info('{Message {nothing} {user} {foo.bar} a}', array('user' => 'Bob', 'foo.bar' => 'Bar'));

        $expected = array('info {Message {nothing} Bob Bar a}');
        $this->assertEquals($expected, $this->getLogs());
    }

    public function testObjectCastToString()
    {
        if (method_exists($this, 'createPartialMock')) {
            $dummy = $this->createPartialMock(DummyTest::class, array('__toString'));
        } else {
            $dummy = $this->getMock(DummyTest::class, array('__toString'));
        }
        $dummy->expects($this->once())
            ->method('__toString')
            ->will($this->returnValue('DUMMY'));

        $this->getLogger()->warning($dummy);

        $expected = array('warning DUMMY');
        $this->assertEquals($expected, $this->getLogs());
    }

    public function testContextCanContainAnything()
    {
        $closed = fopen('php://memory', 'r');
        fclose($closed);

        $context = array(
            'bool' => true,
            'null' => null,
            'string' => 'Foo',
            'int' => 0,
            'float' => 0.5,
            'nested' => array('with object' => new DummyTest),
            'object' => new \DateTime,
            'resource' => fopen('php://memory', 'r'),
            'closed' => $closed,
        );

        $this->getLogger()->warning('Crazy context data', $context);

        $expected = array('warning Crazy context data');
        $this->assertEquals($expected, $this->getLogs());
    }

    public function testContextExceptionKeyCanBeExceptionOrOtherValues()
    {
        $logger = $this->getLogger();
        $logger->warning('Random message', array('exception' => 'oops'));
        $logger->critical('Uncaught Exception!', array('exception' => new \LogicException('Fail')));

        $expected = array(
            'warning Random message',
            'critical Uncaught Exception!'
        );
        $this->assertEquals($expected, $this->getLogs());
    }

    protected function getOnlyLoggedMessage()
    {
        $loggedMessages = $this->getLogs();
        $this->assertCount(1, $loggedMessages);
        $loggedMessage = reset($loggedMessages);
        return $loggedMessage;
    }
}

class DummyTest
{
    public function __toString()
    {
    }
}
