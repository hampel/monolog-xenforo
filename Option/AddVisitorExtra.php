<?php namespace Hampel\Monolog\Option;

use Hampel\Monolog\Config;
use XF\Entity\Option;

/**
 * Adds the visitor to every record - `visitor` in config.php can set it.
 */
class AddVisitorExtra extends AbstractConfigurableOption
{
	public static function get()
	{
		return Config::bool('visitor') ?? (bool) \XF::options()->monologAddVisitorExtra;
	}

	public static function lockedValue(): ?string
	{
		$value = Config::bool('visitor');

		return $value === null ? null : (string) \XF::phrase($value ? 'enabled' : 'disabled');
	}

	protected static function renderControl(Option $option, array $htmlParams): string
	{
		return static::renderOnOff($option, $htmlParams);
	}
}
