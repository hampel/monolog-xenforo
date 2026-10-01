<?php namespace Hampel\Monolog\Processor;

use Monolog\Processor\ProcessorInterface;

/**
 * Adds what a log store holding several forums needs to tell their records apart:
 *
 * - `extra.schema` - bumped when the meaning of a field changes, so a query written against
 *   these records keeps working against later ones;
 * - `extra.site` - which forum: `$config['monolog']['site']` if set, otherwise the board URL's host
 *   without a leading `www.`;
 * - `extra.app` - what kind of execution wrote it: web, admin, api, cli, or job.
 *
 * Written for Monolog 2 and 3 alike - see VisitorProcessor.
 *
 * @param array|\Monolog\LogRecord $record
 *
 * @return array|\Monolog\LogRecord
 */
class ContextProcessor implements ProcessorInterface
{
	public const SCHEMA = 1;

	private string $site;

	public function __construct(string $site)
	{
		$this->site = $site;
	}

	public function __invoke($record)
	{
		$extra = $record['extra'];
		$extra['schema'] = self::SCHEMA;
		$extra['site'] = $this->site;
		$extra['app'] = self::appType(\XF::app());
		$record['extra'] = $extra;

		return $record;
	}

	/**
	 * A job runs inside whichever app picked it up - a web request's job runner, or the CLI - so
	 * it is checked first. The job manager is only consulted if something already built it:
	 * nothing is running a job in a request that has not.
	 */
	public static function appType(\XF\App $app): string
	{
		$container = $app->container();
		if ($container->isCached('job.manager') && self::isRunningJob($app->jobManager()))
		{
			return 'job';
		}

		return self::appTypeFor($app);
	}

	/**
	 * @param object $app
	 */
	public static function appTypeFor($app): string
	{
		if ($app instanceof \XF\Cli\App) return 'cli';
		if ($app instanceof \XF\Api\App) return 'api';
		if ($app instanceof \XF\Admin\App) return 'admin';
		if ($app instanceof \XF\Pub\App) return 'web';

		return 'other';
	}

	/**
	 * Job\Manager keeps the job it is running in a protected property with no accessor, on 2.2
	 * and 2.3 alike.
	 */
	private static function isRunningJob(\XF\Job\Manager $manager): bool
	{
		return (bool) (function ()
		{
			return $this->runningJob;
		})->call($manager);
	}

	/**
	 * The site name for a board URL: its host without a leading `www.`, which is what tells forums
	 * apart in a shared store.
	 */
	public static function siteFromBoardUrl(string $boardUrl): string
	{
		$host = (string) parse_url($boardUrl, PHP_URL_HOST);

		// www.example.com and example.com are the same forum; any other subdomain names a different one
		return (string) preg_replace('/^www\./i', '', $host);
	}
}
