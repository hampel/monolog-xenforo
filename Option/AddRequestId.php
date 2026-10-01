<?php namespace Hampel\Monolog\Option;

use Hampel\Monolog\Config;
use XF\Entity\Option;

/**
 * Adds a request id to every line-format record - `request_id` in config.php can set it. JSON
 * records always carry one.
 */
class AddRequestId extends AbstractConfigurableOption
{
	public static function get()
	{
		return Config::bool('request_id') ?? (bool) \XF::options()->monologAddRequestId;
	}

	public static function lockedValue(): ?string
	{
		$value = Config::bool('request_id');

		return $value === null ? null : (string) \XF::phrase($value ? 'enabled' : 'disabled');
	}

	protected static function renderControl(Option $option, array $htmlParams): string
	{
		return static::renderOnOff($option, $htmlParams);
	}
}
