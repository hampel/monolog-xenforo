<?php namespace Monolog\XF\SubContainer;

use XF\Util\File;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\WebProcessor;
use XF\SubContainer\AbstractSubContainer;

class MonologApi extends AbstractSubContainer
{
	/** @var Logger */
	protected $monolog;

	public function initialize()
	{
		$container = $this->container;

		$container['handler.stream'] = function($c)
		{
			$internalDataDir = File::canonicalizePath($this->app->config('internalDataPath'));
			$handler = new StreamHandler($internalDataDir.'/monolog.log', Logger::DEBUG);
			return $handler;
		};

		$container['processor.visitor'] = function ($c)
		{
			return function($record)
			{
				$visitor = \XF::visitor();

				$record['extra']['visitor'] = [
					'userid' => $visitor->user_id,
					'username' => $visitor->username,
				];

				return $record;
			};
		};

		$container['logger.default'] = function($c)
		{
			$logger = new Logger('xenforo');
			$logger->pushHandler($c['handler.stream']);
			$logger->pushProcessor($c['processor.visitor']);
			$logger->pushProcessor(new WebProcessor());
			return $logger;
		};
	}

	public function newChannel($channel)
	{
		$mono = $this->container['default'];
		return $mono->withName($channel);
	}

	/** Logger */
	public function default()
	{
		return $this->container('logger.default');
	}

	public function stream()
	{
		return $this->container('handler.stream');
	}

	public function visitor()
	{
		return $this->container('processor.visitor');
	}
}
