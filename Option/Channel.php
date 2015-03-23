<?php

class Monolog_Option_Channel
{
	public static function verifyOption(&$value, XenForo_DataWriter $dw, $fieldName)
	{
		if (!preg_match('/^[\d\w_-]+$/', $value))
		{
			$dw->error(new XenForo_Phrase('monolog_channels_regex'), $fieldName);
			return false;
		}

		return true;
	}

	public static function getDefault()
	{
		return XenForo_Application::getOptions()->monologDefaultChannel;
	}
}
