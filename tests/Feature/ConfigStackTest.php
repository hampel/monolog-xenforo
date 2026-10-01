<?php namespace Tests\Feature;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\TestHandler;
use Tests\TestCase;

/**
 * handlers, processors and formatter in $config['monolog']: the server owner's way to add to the
 * stack without an add-on, in the same callable style as $config['fsAdapters'].
 */
class ConfigStackTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->useTemporaryInternalData();
		$this->setOption('monologAddVisitorExtra', false);
		$this->fakesErrors();
	}

	public function test_a_configured_handler_receives_records_alongside_the_defaults()
	{
		$handler = new TestHandler();
		$this->setConfig('monolog', ['handlers' => [function () use ($handler) { return $handler; }]]);

		$this->app()['monolog']->channel('myaddon')->warning('to both');

		$this->assertTrue($handler->hasWarningThatContains('to both'));
		$this->assertCount(1, $this->logLines(), 'the log file still receives it');
	}

	public function test_a_configured_processor_runs()
	{
		$this->setOption('monologAddRequestId', false);

		$this->setConfig('monolog', ['processors' => [function ()
		{
			return function ($record)
			{
				$extra = $record['extra'];
				$extra['from_config'] = true;
				$record['extra'] = $extra;

				return $record;
			};
		}]]);

		$this->app()['monolog']->channel('myaddon')->warning('processed');

		$this->assertStringContainsString('{"from_config":true}', $this->logLines()[0]);
	}

	public function test_a_configured_formatter_replaces_the_log_file_format()
	{
		$this->setConfig('monolog', [
			'file' => [
				'format' => 'json',
				'formatter' => function () { return new LineFormatter("%channel%|%message%\n"); },
			],
		]);

		$this->app()['monolog']->channel('myaddon')->warning('custom');

		$this->assertSame(['myaddon|custom'], $this->logLines());
	}

	public function test_the_factories_are_not_called_until_the_logger_is_built()
	{
		$called = false;
		$this->setConfig('monolog', ['handlers' => [function () use (&$called)
		{
			$called = true;
			return new TestHandler();
		}]]);

		$monolog = $this->app()['monolog'];
		$this->assertFalse($called, 'not when config.php is read, before add-on classes can load');

		$monolog->channel('myaddon');
		$this->assertTrue($called);
	}

	public function test_listeners_to_the_setup_event_see_the_configured_handlers()
	{
		$configured = new TestHandler();
		$seen = null;
		$this->setConfig('monolog', ['handlers' => [function () use ($configured) { return $configured; }]]);
		$this->app()->extension()->addListener('hampel_monolog_setup',
			function (\XF\App $app, array &$handlers, array &$processors) use (&$seen)
			{
				$seen = $handlers;
			}
		);

		$this->app()['monolog']->channel('myaddon');

		$this->assertContains($configured, $seen);
	}

	public static function badEntries(): array
	{
		return [
			'not callable' => ['handlers', 'not a function'],
			'handler factory returns the wrong type' => ['handlers', function () { return new \stdClass(); }],
			'processor factory returns something not callable' => ['processors', function () { return 'nope'; }],
			'formatter factory returns the wrong type' => ['file.formatter', function () { return null; }],
		];
	}

	/**
	 * A config.php mistake must not take logging - or every page that logs - down with it: the
	 * entry is skipped, the rest of the stack works, and the server error log says why.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('badEntries')]
	public function test_a_bad_entry_is_skipped_and_reported($key, $entry)
	{
		$this->setConfig('monolog', $key === 'file.formatter' ? ['file' => ['formatter' => $entry]] : [$key => [$entry]]);

		$this->app()['monolog']->channel('myaddon')->warning('still logged');

		$this->assertCount(1, $this->logLines());
		$errors = array_values($this->getErrors());
		$this->assertCount(1, $errors);
		$where = "\$config['monolog']['" . str_replace('.', "']['", $key) . "']";
		$this->assertStringContainsString($where, $errors[0]['message']);
	}
}
