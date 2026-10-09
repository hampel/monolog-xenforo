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

	/**
	 * Tells Hampel/AdminApi which of this add-on's settings are secrets, so it can show the rest.
	 * On a forum without the Admin API nothing fires this, and nothing here depends on it.
	 *
	 * One secret, in two places: the Slack webhook, whose URL is the credential - whoever holds it
	 * can post to the channel. Everything else is a path, an address, a level, a format or a
	 * switch. `config.php`'s handlers, processors and formatter are closures, which the Admin API
	 * withholds as unrepresentable, so a key built into one cannot be read either way.
	 *
	 * @param array $declarations add-on id => ['options' => [...], 'config' => [...]]
	 */
	public static function adminApiRedaction(array &$declarations)
	{
		$declarations['Hampel/Monolog'] = [
			'options' => ['monologSlack.webhook'],
			'config' => ['monolog' => ['monolog.slack.webhook']],
		];
	}
}
