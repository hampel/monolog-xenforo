<?php namespace Monolog\Test;

use Monolog\Logger;

class LoggerTest extends AbstractTest
{
	public function run()
	{
		/** @var Logger $logger */
		$logger = $this->app['monolog']->default();

		$context = ['a' => 'foo', 'b' => 'bar', 'c' => 'baz'];

		$logger->debug('this is a debug message', $context);
		$logger->info('this is an info message');
		$logger->notice('this is a notice message');
		$logger->warning('this is a warning message');
		$logger->error('this is an error message');
		$logger->critical('this is a critical message');
		$logger->alert('this is an alert message');
		$logger->emergency('this is an emergency message');

		return true;
	}
}
