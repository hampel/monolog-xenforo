<?php namespace Hampel\Monolog;

use XF\App;
use XF\Container;
use Hampel\Monolog\SubContainer\MonologApi;

class Listener
{
	public static function appSetup(App $app)
	{
		$container = $app->container();

		$container['monolog'] = function(Container $c) use ($app)
		{
			$class = $app->extendClass(MonologApi::class);
			return new $class($c, $app);
		};
	}

	public static function appAdminSetup(App $app)
	{
		$container = $app->container();

		$container->factory('monolog.test', function($class, array $params, Container $c) use ($app)
		{
			$class = \XF::stringToClass($class, '\%s\Test\%s');
			$class = $app->extendClass($class);

			array_unshift($params, $app);

			return $c->createObject($class, $params, true);
		}, false);
	}
}