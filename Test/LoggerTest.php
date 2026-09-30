<?php namespace Hampel\Monolog\Test;

use Hampel\Monolog\SubContainer\MonologApi;

/**
 * The ACP test: one message at every level, so an admin can see which reach the log file and
 * which are emailed.
 */
class LoggerTest extends AbstractTest
{
	public function run()
	{
		/** @var MonologApi $monolog */
		$monolog = $this->app->container('monolog');
		$logger = $monolog->channel('monolog-test');

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
