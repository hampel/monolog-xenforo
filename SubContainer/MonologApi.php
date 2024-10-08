<?php namespace Hampel\Monolog\SubContainer;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\DeduplicationHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SwiftMailerHandler;
use Monolog\Handler\SymfonyMailerHandler;
use Monolog\Processor\WebProcessor;
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
			// default date format has changed in Monolog 2
			$dateFormat = "Y-m-d H:i:s";
			$formatter = new LineFormatter(null, $dateFormat);

			$logfile = LogFile::getLogFile();
			$logLevel = FileMinimumLogLevel::get();

			$internalDataDir = File::canonicalizePath($this->app->config('internalDataPath'));
			$handler = new StreamHandler("{$internalDataDir}/{$logfile}", $logLevel);
			$handler->setFormatter($formatter);
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

            $handler = new SwiftMailerHandler($swiftmailer, $message, $logLevel);

            return new DeduplicationHandler($handler, "{$tempDir}/monolog-dedup-swiftmailer.log", $logLevel, $dedupTimeout);
        };

		$container['handler.symfonymailer'] = function(Container $c)
		{
			$tempDir = File::getTempDir();
			$subject = EmailSubject::get();
			$sendTo = SendEmail::getAddress();
			$logLevel = EmailMinimumLogLevel::get();
			$dedupTimeout = EmailDeduplicationTimeout::get();

			$message = $this->getMessage($sendTo)->subject($subject);

			$symfonymailer = $this->app->mailer()->getDefaultTransport();

            $handler = new SymfonyMailerHandler($symfonymailer, $message, $logLevel);

			return new DeduplicationHandler($handler, "{$tempDir}/monolog-dedup-symfonymailer.log", $logLevel, $dedupTimeout);
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
                if (\XF::$versionId >= 2030000)
                {
                    $logger->pushHandler($c['handler.symfonymailer']);
                }
                else
                {
                    $logger->pushHandler($c['handler.swiftmailer']);
                }

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

    public function getMessage($to = "", $from = "")
    {
        $message = $this->app->mailer()
            ->newMail()
            ->setTo(!empty($to) ? $to : $this->parent['options']['contactEmailAddress'])
            ->setFrom(!empty($from) ? $from : $this->parent['options']['defaultEmailAddress']);

        return $message->getSendableEmail();
    }
}
