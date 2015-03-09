<?php

use Monolog\Logger;

class Monolog_Option_Loglevel
{
	public static function renderSelect(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		return self::_render('option_list_option_select', $view, $fieldPrefix, $preparedOption, $canEdit);
	}

	public static function get()
	{
		return XenForo_Application::getOptions()->monologLogLevel;
	}

	protected static function getLogLevelOptions($selectedLevel)
	{
		$options = [];

		foreach (Logger::getLevels() as $name => $value)
		{
			$options[] = [
				'label' => self::_renderPhrase($name),
				'value' => $value,
				'selected' => ($selectedLevel == $value)
			];
		}

		return $options;
	}

	protected static function _render($templateName, XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		$preparedOption['formatParams'] = self::getLogLevelOptions($preparedOption['option_value']);

		return XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal(
			$templateName, $view, $fieldPrefix, $preparedOption, $canEdit
		);
	}

	protected static function _renderPhrase($name)
	{
		$name = strtolower($name);
		return new XenForo_Phrase("monolog_loglevel_{$name}");
	}
}