<?php namespace Tests\Feature;

use Monolog\Handler\TestHandler;
use Tests\TestCase;

/**
 * hampel_monolog_setup: the extension point for adding, removing or replacing handlers and
 * processors, fired once before the base logger every channel shares is built.
 */
class SetupEventTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->useTemporaryInternalData();
		$this->setOption('monologAddVisitorExtra', false);
	}

	public function test_the_event_fires_once_however_many_channels_are_used()
	{
		$this->fakesEvents();

		$monolog = $this->app()['monolog'];
		$monolog->channel('first')->warning('one');
		$monolog->channel('second')->warning('two');

		$this->assertEventFiredTimes('hampel_monolog_setup', 1);
	}

	public function test_a_listener_can_add_a_handler()
	{
		$handler = new TestHandler();
		$this->app()->extension()->addListener('hampel_monolog_setup',
			function (\XF\App $app, array &$handlers, array &$processors) use ($handler)
			{
				$handlers[] = $handler;
			}
		);

		$this->app()['monolog']->channel('myaddon')->warning('to both');

		$this->assertTrue($handler->hasWarningThatContains('to both'));
		$this->assertCount(1, $this->logLines(), 'the default file handler is still there');
	}

	public function test_a_listener_can_remove_the_default_handlers()
	{
		$this->app()->extension()->addListener('hampel_monolog_setup',
			function (\XF\App $app, array &$handlers, array &$processors)
			{
				$handlers = [];
			}
		);

		$this->app()['monolog']->channel('myaddon')->error('nowhere');

		$this->assertSame([], $this->logLines());
	}

	public function test_a_listener_can_add_a_processor()
	{
		$this->setOption('monologAddRequestId', false);

		$this->app()->extension()->addListener('hampel_monolog_setup',
			function (\XF\App $app, array &$handlers, array &$processors)
			{
				$processors[] = function ($record)
				{
					$extra = $record['extra'];
					$extra['added'] = 'by a listener';
					$record['extra'] = $extra;

					return $record;
				};
			}
		);

		$this->app()['monolog']->channel('myaddon')->warning('processed');

		$this->assertStringContainsString('{"added":"by a listener"}', $this->logLines()[0]);
	}

	public function test_a_listener_receives_the_app()
	{
		$received = null;
		$this->app()->extension()->addListener('hampel_monolog_setup',
			function (\XF\App $app, array &$handlers, array &$processors) use (&$received)
			{
				$received = $app;
			}
		);

		$this->app()['monolog']->channel('myaddon');

		$this->assertSame($this->app(), $received);
	}
}
