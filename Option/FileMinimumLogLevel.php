<?php namespace Hampel\Monolog\Option;

use Monolog\Logger;
use XF\Option\AbstractOption;

class FileMinimumLogLevel extends AbstractOption
{
	/**
	 * The PSR-3 levels by Monolog's numeric value, which is what the level options store.
	 *
	 * Spelled out rather than read from Logger::getLevels(), which Monolog 3 removed - and
	 * XenForo 2.4 bundles Monolog 3, so on 2.4 that call is a fatal error on the options page.
	 */
	public const LEVELS = [
		100 => 'Debug',
		200 => 'Info',
		250 => 'Notice',
		300 => 'Warning',
		400 => 'Error',
		500 => 'Critical',
		550 => 'Alert',
		600 => 'Emergency',
	];

	/**
	 * Renders the select for both level options, file and email.
	 */
	public static function renderSelect(\XF\Entity\Option $option, array $htmlParams)
	{
		$value = $option['option_value'];
		if (empty($value))
		{
			$value = $option['default_value'];
		}

		$choices = [];
		foreach (self::LEVELS AS $level => $label)
		{
			$choices[] = [
				'_type' => 'option',
				'label' => $label,
				'value' => $level,
			];
		}

		return self::getTemplater()->formSelectRow(
			self::getControlOptions($option, $htmlParams, $value), $choices, self::getRowOptions($option, $htmlParams)
		);
	}

	public static function get()
	{
		$logLevel = \XF::options()->monologFileMinimumLogLevel;
		if (empty($logLevel)) $logLevel = Logger::WARNING;

		return $logLevel;
	}
}
