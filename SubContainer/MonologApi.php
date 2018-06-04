<?php namespace Monolog\SubContainer;

use XF\Util\File;
use Monolog\Logger;
use Monolog\Option\LogFile;
use Monolog\Option\SendEmail;
use Monolog\Option\AddWebExtra;
use Monolog\Option\EmailSubject;
use Monolog\Handler\StreamHandler;
use Monolog\Option\AddVisitorExtra;
use Monolog\Processor\WebProcessor;
use Monolog\Option\FileMinimumLogLevel;
use Monolog\Handler\SwiftMailerHandler;
use Monolog\Option\EmailMinimumLogLevel;
use Monolog\Handler\DeduplicationHandler;
use XF\SubContainer\AbstractSubContainer;
use Monolog\Option\EmailDeduplicationTimeout;

class MonologApi extends AbstractSubContainer
{
	/** @var Logger */
	protected $monolog;

	public function initialize()
	{
		$container = $this->container;

		$container['handler.stream'] = function($c)
		{
			$logfile = LogFile::getLogFile();
			$logLevel = FileMinimumLogLevel::get();

			$internalDataDir = File::canonicalizePath($this->app->config('internalDataPath'));
			$handler = new StreamHandler("{$internalDataDir}/{$logfile}", $logLevel);
			return $handler;
		};

		$container['handler.swiftmailer'] = function($c)
		{
			$tempDir = File::getTempDir();
			$subject = EmailSubject::get();
			$sendTo = SendEmail::getAddress();
			$logLevel = EmailMinimumLogLevel::get();
			$dedupTimeout = EmailDeduplicationTimeout::get();

			$message = $this->getSwiftMessage($subject, $sendTo);
			$swiftmailer = \Swift_Mailer::newInstance($this->app->mailer()->getDefaultTransport());

			$handler = new SwiftMailerHandler($swiftmailer, $message, $logLevel);

			return new DeduplicationHandler($handler, "{$tempDir}/monolog-dedup-swiftmailer.log", $logLevel, $dedupTimeout);
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
				$logger->pushProcessor(new WebProcessor());
			}
			return $logger;
		};
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
