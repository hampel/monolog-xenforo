<?php namespace Monolog\Test;

use Monolog\Helper\Log;

class LoggerTest extends AbstractTest
{
	public function run()
	{
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
