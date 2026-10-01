<?php namespace Hampel\Monolog\Option;

use Monolog\Logger;

/**
 * The lowest level posted to Slack - `slack.level` in config.php can set it.
 */
class SlackMinimumLogLevel extends FileMinimumLogLevel
{
	protected const DEPENDS_ON = 'slack';
	protected const SECTION = 'slack';
	protected const OPTION = 'monologSlackMinimumLogLevel';
	protected const DEFAULT_LEVEL = Logger::ERROR;
}
