<?php namespace Hampel\Monolog\Option;

use Hampel\Monolog\Config;
use XF\Entity\Option;

/**
 * Adds the request's URL, IP address and more to every record - `web` in config.php can set it.
 */
class AddWebExtra extends AbstractConfigurableOption
{
	public static function get()
	{
		return Config::bool('web') ?? (bool) \XF::options()->monologAddWebExtra;
	}

	public static function lockedValue(): ?string
	{
		$value = Config::bool('web');

		return $value === null ? null : (string) \XF::phrase($value ? 'enabled' : 'disabled');
	}

	protected static function renderControl(Option $option, array $htmlParams): string
	{
		return static::renderOnOff($option, $htmlParams);
	}
}
