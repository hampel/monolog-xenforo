<?php namespace Tests\Feature;

use Tests\TestCase;

/**
 * $config['monolog'] in config.php - the server owner's settings, which win over the options.
 */
class ConfigTest extends TestCase
{
	/** @var string */
	private $internalData;

	protected function setUp(): void
	{
		parent::setUp();

		$this->internalData = $this->useTemporaryInternalData();
		$this->setOption('monologAddVisitorExtra', false);
	}

	public function test_without_a_config_block_the_option_decides()
	{
		$this->app()['monolog']->channel('myaddon')->error('from the option');

		$this->assertCount(1, $this->logLines('monolog.log'));
	}

	public function test_a_configured_absolute_file_is_used_as_given()
	{
		$this->setConfig('monolog', ['file' => $this->internalData . '/absolute/app.log']);

		$this->app()['monolog']->channel('myaddon')->error('to an absolute path');

		$this->assertSame([], $this->logLines('monolog.log'));
		$this->assertCount(1, $this->logLines('absolute/app.log'));
	}

	public function test_a_configured_relative_file_is_inside_internal_data()
	{
		$this->setConfig('monolog', ['file' => 'logs/app.log']);

		$this->app()['monolog']->channel('myaddon')->error('relative');

		$this->assertCount(1, $this->logLines('logs/app.log'));
	}

	public function test_the_configured_file_wins_over_a_disabled_option()
	{
		$this->setOption('monologLogFile', ['enabled' => false, 'logfile' => 'monolog.log']);
		$this->setConfig('monolog', ['file' => 'app.log']);

		$this->app()['monolog']->channel('myaddon')->error('config wins');

		$this->assertCount(1, $this->logLines('app.log'));
	}

	public function test_a_configured_false_turns_the_file_off()
	{
		$this->setConfig('monolog', ['file' => false]);

		$this->app()['monolog']->channel('myaddon')->error('nowhere');

		$this->assertSame([], $this->logLines('monolog.log'));
	}

	public function test_json_format_writes_one_monolog_record_per_line()
	{
		$this->setConfig('monolog', ['format' => 'json']);
		$this->setOption('monologAddVisitorExtra', true);

		$channel = $this->app()['monolog']->channel('myaddon');
		$channel->warning('first', ['count' => 3]);
		$channel->error('second');

		$lines = $this->logLines();
		$this->assertCount(2, $lines);

		$record = json_decode($lines[0], true);
		$this->assertIsArray($record, 'each line is a JSON object on its own');
		$this->assertSame('first', $record['message']);
		$this->assertSame(['count' => 3], $record['context']);
		$this->assertSame('WARNING', $record['level_name']);
		$this->assertSame(300, $record['level']);
		$this->assertSame('myaddon', $record['channel']);
		$this->assertArrayHasKey('datetime', $record);
		$this->assertArrayHasKey('visitor', $record['extra']);
	}

	public function test_an_exception_in_json_carries_its_stack_trace()
	{
		$this->setConfig('monolog', ['format' => 'json']);

		$this->app()['monolog']->channel('myaddon')->error('failed', ['exception' => new \RuntimeException('boom')]);

		$record = json_decode($this->logLines()[0], true);
		$this->assertArrayHasKey('trace', $record['context']['exception']);
	}

	public function test_an_unknown_format_falls_back_to_line()
	{
		$this->setConfig('monolog', ['format' => 'yaml']);

		$this->app()['monolog']->channel('myaddon')->error('still written');

		$this->assertStringContainsString('] myaddon.ERROR: still written', $this->logLines()[0]);
	}
}
