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

		// probe: these are sent on purpose, at every level, and an alert over the log should be able
		// to leave all of them out with one filter - whatever channel a probe happens to use
		$probe = ['probe' => true];

		$logger->debug('this is a debug message', $probe + ['a' => 'foo', 'b' => 'bar', 'c' => 'baz']);
		$logger->info('this is an info message', $probe);
		$logger->notice('this is a notice message', $probe);
		$logger->warning('this is a warning message', $probe);
		$logger->error('this is an error message', $probe);
		$logger->critical('this is a critical message', $probe);
		$logger->alert('this is an alert message', $probe);
		$logger->emergency('this is an emergency message', $probe);

		return true;
	}
}
