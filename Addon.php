<?php

class Monolog_Addon
{
	public static function autoloadPath()
	{
		return self::addonBasePath() . '/vendor/autoload.php';
	}

	public static function addonBasePath()
	{
		return __DIR__;
	}
}
