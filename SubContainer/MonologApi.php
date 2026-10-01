<?php namespace Hampel\Monolog\SubContainer;

use Hampel\Monolog\Handler\LazyHandler;
use Hampel\Monolog\Handler\XenForoMailHandler;
use Hampel\Monolog\Option\AddVisitorExtra;
use Hampel\Monolog\Option\AddWebExtra;
use Hampel\Monolog\Option\EmailDeduplicationTimeout;
use Hampel\Monolog\Option\EmailMinimumLogLevel;
use Hampel\Monolog\Option\EmailSubject;
use Hampel\Monolog\Option\FileMinimumLogLevel;
use Hampel\Monolog\Option\LogFile;
use Hampel\Monolog\Option\SendEmail;
use Hampel\Monolog\Processor\VisitorProcessor;
use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\DeduplicationHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\WebProcessor;
use Psr\Log\LoggerInterface;
use XF\Container;
use XF\SubContainer\AbstractSubContainer;
use XF\Util\File;

/**
 * The `monolog` service. Consumers ask it for a channel and get a PSR-3 logger:
 *
 *     $logger = \XF::app()->get('monolog')->channel('myaddon');
 *
 * Every channel shares one set of handlers and processors, built once from the options into a
 * base logger that is never modified afterwards. A channel is that logger under another name.
 */
class MonologApi extends AbstractSubContainer
{
	/** @var LoggerInterface[] channels already handed out, by name */
	private array $channels = [];

	public function initialize()
	{
		$container = $this->container;

		$container['handler.file'] = function (Container $c)
		{
			$file = $this->logFile();
			if ($file === null)
			{
				return null;
			}

			$handler = new StreamHandler($file, FileMinimumLogLevel::get());
			$handler->setFormatter($c['formatter.file']);

			return $handler;
		};

		$container['formatter.file'] = function (Container $c)
		{
			if (($this->config()['format'] ?? 'line') === 'json')
			{
				// one record per line, so line-oriented collectors can ship it; Monolog's own
				// field names, so any tool can read it; and stack traces as a field
				return new JsonFormatter(JsonFormatter::BATCH_MODE_NEWLINES, true, false, true);
			}

			// Monolog 2 changed the default date format; this is the one 1.x wrote, and the one
			// anything parsing these files already expects
			return new LineFormatter(null, 'Y-m-d H:i:s');
		};

		$container['handler.email'] = function (Container $c)
		{
			if (!SendEmail::isEnabled())
			{
				return null;
			}

			$level = EmailMinimumLogLevel::get();

			// built on the first record at $level, never when a channel is created - see LazyHandler
			return new LazyHandler(function () use ($level)
			{
				$handler = new XenForoMailHandler(
					$this->app->mailer(),
					SendEmail::getAddress(),
					EmailSubject::get(),
					$level
				);

				// buffers the request's records into one email, sent at shutdown, and skips any
				// already sent within the timeout
				return new DeduplicationHandler(
					$handler,
					File::getTempDir() . '/monolog-dedup-email.log',
					$level,
					EmailDeduplicationTimeout::get()
				);
			}, $level);
		};

		$container['handlers'] = function (Container $c)
		{
			return array_values(array_filter([$c['handler.file'], $c['handler.email']]));
		};

		$container['processors'] = function (Container $c)
		{
			$processors = [];

			// Monolog runs processors in array order
			if (AddWebExtra::get())
			{
				$processors[] = new WebProcessor();
			}
			if (AddVisitorExtra::get())
			{
				$processors[] = new VisitorProcessor();
			}

			return $processors;
		};

		$container['logger'] = function (Container $c)
		{
			return new Logger('xenforo', $c['handlers'], $c['processors']);
		};
	}

	/**
	 * `$config['monolog']` from config.php - the server owner's settings, which win over the
	 * options. Recognised keys:
	 *
	 * - `file`: the log file. Absolute (or a stream such as `php://stderr`) is used as given;
	 *   relative is inside internal_data; `false` turns file logging off. Unset, the
	 *   `monologLogFile` option decides.
	 * - `format`: `line` (the default) or `json`, one Monolog JSON record per line.
	 */
	protected function config(): array
	{
		$config = $this->app->config('monolog');

		return is_array($config) ? $config : [];
	}

	/**
	 * The full path of the log file, or null for no file logging.
	 */
	protected function logFile(): ?string
	{
		$config = $this->config();

		if (array_key_exists('file', $config))
		{
			$file = $config['file'];
			if (!is_string($file) || $file === '')
			{
				return null;
			}
		}
		else
		{
			$file = LogFile::getLogFile();
			if ($file === '')
			{
				return null;
			}
		}

		// absolute paths and stream wrappers come back unchanged
		return File::canonicalizePath($file, File::canonicalizePath($this->app->config('internalDataPath')));
	}

	/**
	 * A logger for one channel - by convention the add-on's own short name.
	 *
	 * The return type is the contract: type against Psr\Log\LoggerInterface, never against a
	 * Monolog class, which is an implementation detail and will change major version.
	 */
	public function channel(string $name): LoggerInterface
	{
		if (!isset($this->channels[$name]))
		{
			/** @var Logger $logger */
			$logger = $this->container('logger');
			$this->channels[$name] = $logger->withName($name);
		}

		return $this->channels[$name];
	}

	/**
	 * Whether this release has been tested on the running XenForo.
	 *
	 * XenForo 2.4 bundles Monolog 3, and its class loader is consulted before any add-on's, so
	 * there every Monolog class this add-on names resolves to core's v3. The handlers and
	 * processors are written to run on both, and `MONOLOG3=1 vendor/bin/phpunit` proves it - but
	 * nothing has run on XenForo 2.4 itself, so the install checks say so.
	 */
	public static function isTestedOnThisXenForo(): bool
	{
		return \XF::$versionId < 2040000;
	}

	/**
	 * @deprecated 5.0.0 use channel(), which this now calls
	 *
	 * @param string $channel
	 *
	 * @return LoggerInterface
	 */
	public function newChannel($channel)
	{
		return $this->channel((string) $channel);
	}
}
