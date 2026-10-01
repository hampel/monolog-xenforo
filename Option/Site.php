<?php namespace Hampel\Monolog\Option;

use Hampel\Monolog\Config;
use Hampel\Monolog\Processor\ContextProcessor;
use XF\Entity\Option;

/**
 * The name JSON records carry in `extra.site` - `site` in config.php can set it. Empty means the
 * board URL's host.
 */
class Site extends AbstractConfigurableOption
{
	public static function get(): string
	{
		$site = Config::string('site') ?? (string) (\XF::options()->monologSite ?? '');

		return $site !== '' ? $site : ContextProcessor::siteFromBoardUrl((string) \XF::options()->boardUrl);
	}

	public static function lockedValue(): ?string
	{
		return Config::string('site');
	}

	protected static function renderControl(Option $option, array $htmlParams): string
	{
		return static::renderTextBox($option, $htmlParams);
	}
}
