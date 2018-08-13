<?php namespace Hampel\Monolog\Test;

use Hampel\Monolog\Helper\Log;
use Hampel\Monolog\SubContainer\MonologApi;

class LoggerTest extends AbstractTest
{
	public function run()
	{
		/** @var MonologApi $monolog */
		$monolog = $this->app->container('monolog');
		$logger = $monolog->logger('monolog-test');
		$stream = $monolog->stream();
		$logger->pushHandler($stream);
		$logger->info('this is an info message on the monolog-test channel');

		$context = ['a' => 'foo', 'b' => 'bar', 'c' => 'baz'];

		Log::debug('this is a debug message', $context);
		Log::info('this is an info message');
		Log::notice('this is a notice message');
		Log::warning('this is a warning message');
		Log::error('this is an error message');
		Log::critical('this is a critical message');
		Log::alert('this is an alert message');
		Log::emergency('this is an emergency message');

		return true;
	}
}
