<?php namespace Hampel\Monolog\Option;

use Monolog\Logger;
use XF\Option\AbstractOption;

class FileMinimumLogLevel extends AbstractOption
{
	public static function renderSelect(\XF\Entity\Option $option, array $htmlParams)
	{
		$levels = array_flip(Logger::getLevels());

		$value = $option['option_value'];
		$default = $option['default_value'];
		if (empty($value)) $value = $default;

		$choices = [];
		foreach ($levels AS $level => $name)
		{
			$choices[] = [
				'_type' => 'option',
				'label' => ucwords(strtolower($name)),
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
