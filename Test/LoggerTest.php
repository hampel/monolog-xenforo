<?php namespace Monolog\Test;

use Monolog\Logger;

class LoggerTest extends AbstractTest
{
	public function run()
	{
		/** @var Logger $logger */
		$logger = $this->app['monolog']->default();

		$context = ['a' => 'foo', 'b' => 'bar', 'c' => 'baz'];

		$result[0] = $logger->debug('this is a debug message', $context);
		$result[1] = $logger->info('this is an info message');
		$result[2] = $logger->notice('this is a notice message');
		$result[3] = $logger->warning('this is a warning message');
		$result[4] = $logger->error('this is an error message');
		$result[5] = $logger->critical('this is a critical message');
		$result[6] = $logger->alert('this is an alert message');
		$result[7] = $logger->emergency('this is an emergency message');

		$success = true;
		foreach ($result as $r)
		{
			if (!$r) $success = false;
		}

		if ($success)
		{
			$this->successMessage(\XF::phrase('monolog_log_successfully_processed'));
			return true;
		}
		else
		{
			$this->errorMessage(\XF::phrase('monolog_log_could_not_be_processed'));
			return false;
		}
	}
}
