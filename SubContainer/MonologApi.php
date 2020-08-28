<?php namespace Hampel\Monolog\SubContainer;

use XF\Util\File;
use XF\Container;
use Hampel\Monolog\Option\LogFile;
use Hampel\Monolog\Option\SendEmail;
use Hampel\Monolog\Option\AddWebExtra;
use Hampel\Monolog\Option\EmailSubject;
use XF\SubContainer\AbstractSubContainer;
use Hampel\Monolog\Option\AddVisitorExtra;
use Hampel\Monolog\Option\FileMinimumLogLevel;
use Hampel\Monolog\Option\EmailMinimumLogLevel;
use Hampel\Monolog\Option\EmailDeduplicationTimeout;

class MonologApi extends AbstractSubContainer
{
	public function initialize()
	{
		$container = $this->container;

		$container['handler.stream'] = function(Container $c)
		{
			$logfile = LogFile::getLogFile();
			$logLevel = FileMinimumLogLevel::get();

			$internalDataDir = File::canonicalizePath($this->app->config('internalDataPath'));
			$handler = new \Monolog\Handler\StreamHandler("{$internalDataDir}/{$logfile}", $logLevel);
			return $handler;
		};

		$container['handler.swiftmailer'] = function(Container $c)
		{
			$tempDir = File::getTempDir();
			$subject = EmailSubject::get();
			$sendTo = SendEmail::getAddress();
			$logLevel = EmailMinimumLogLevel::get();
			$dedupTimeout = EmailDeduplicationTimeout::get();

			$message = $this->getSwiftMessage($subject, $sendTo);
			$swiftmailer = new \Swift_Mailer($this->app->mailer()->getDefaultTransport());

			$handler = new \Monolog\Handler\SwiftMailerHandler($swiftmailer, $message, $logLevel);

			return new \Monolog\Handler\DeduplicationHandler($handler, "{$tempDir}/monolog-dedup-swiftmailer.log", $logLevel, $dedupTimeout);
		};

		$container['processor.visitor'] = function (Container $c)
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

		$container['logger'] = function(Container $c)
		{
			return new \Monolog\Logger('xenforo');
		};

		$container['logger.default'] = function(Container $c)
		{
			$logger = $c['logger'];
			if (LogFile::isEnabled() && LogFile::getLogFile() !== '')
			{
				$logger->pushHandler($c['handler.stream']);
			}
			if (SendEmail::isEnabled())
			{
				$logger->pushHandler($c['handler.swiftmailer']);
			}
			if (AddVisitorExtra::get())
			{
				$logger->pushProcessor($c['processor.visitor']);
			}
			if (AddWebExtra::get())
			{
				$logger->pushProcessor(new \Monolog\Processor\WebProcessor());
			}
			return $logger;
		};
	}

	public function logger($name = '')
	{
		/** @var \Monolog\Logger $logger */
		$logger = $this->container('logger');
		if (!empty($name))
		{
			return $logger->withName($name);
		}
		return $logger;
	}

	public function newChannel($channel)
	{
		return $this->default()->withName($channel);
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

	public function getSwiftMessage($subject, $to = "", $from = "")
	{
		$message = new \Swift_Message($subject);
		$message->setTo(!empty($to) ? $to : $this->parent['options']['contactEmailAddress']);
		$message->setFrom(!empty($from) ? $from : $this->parent['options']['defaultEmailAddress']);

		return $message;
	}
}
