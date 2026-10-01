<?php namespace Tests\Feature;

use Hampel\Monolog\Processor\ContextProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * extra.schema, extra.site and extra.app - what a log store holding several forums needs to
 * tell their records apart. Added to JSON output only: a line log is one forum's file, read by a
 * person, where all three are implied.
 */
class ContextProcessorTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->useTemporaryInternalData();
		$this->setOptions([
			'monologAddVisitorExtra' => false,
			'boardUrl' => 'https://forum.example.com/community',
		]);
	}

	public function test_json_records_carry_schema_site_and_app()
	{
		$this->setConfig('monolog', ['file' => ['format' => 'json']]);

		$this->app()['monolog']->channel('myaddon')->error('where was this');

		$extra = json_decode($this->logLines()[0], true)['extra'];
		$this->assertSame(ContextProcessor::SCHEMA, $extra['schema']);
		$this->assertSame('forum.example.com', $extra['site']);
		$this->assertArrayHasKey('app', $extra);
	}

	public function test_a_configured_site_wins_over_the_board_url()
	{
		$this->setConfig('monolog', ['file' => ['format' => 'json'], 'site' => 'examplecom']);

		$this->app()['monolog']->channel('myaddon')->error('renamed');

		$this->assertSame('examplecom', json_decode($this->logLines()[0], true)['extra']['site']);
	}

	public static function boardUrls(): array
	{
		return [
			'leading www is dropped' => ['https://www.somersoft.com/', 'somersoft.com'],
			'any other subdomain is kept' => ['https://members.rebaa.com.au/', 'members.rebaa.com.au'],
			'a bare domain is unchanged' => ['https://example.com/forum', 'example.com'],
			'www in any case' => ['https://WWW.Example.com/', 'Example.com'],
			'a port is not part of the name' => ['http://www.example.com:8080/', 'example.com'],
			'www only as a whole label' => ['https://wwwexample.com/', 'wwwexample.com'],
			'www further in is kept' => ['https://forum.www.example.com/', 'forum.www.example.com'],
		];
	}

	#[DataProvider('boardUrls')]
	public function test_the_default_site_is_the_board_host_without_a_leading_www($url, $expected)
	{
		$this->assertSame($expected, ContextProcessor::siteFromBoardUrl($url));
	}

	public function test_line_records_are_unchanged()
	{
		$this->setOption('monologAddRequestId', false);

		$this->app()['monolog']->channel('myaddon')->error('as before');

		$this->assertStringEndsWith('myaddon.ERROR: as before [] []', $this->logLines()[0]);
	}

	public static function apps(): array
	{
		return [
			'public' => [\XF\Pub\App::class, 'web'],
			'admin' => [\XF\Admin\App::class, 'admin'],
			'api' => [\XF\Api\App::class, 'api'],
			'cli' => [\XF\Cli\App::class, 'cli'],
		];
	}

	#[DataProvider('apps')]
	public function test_each_xenforo_app_is_named($class, $expected)
	{
		$this->assertSame($expected, ContextProcessor::appTypeFor(\Mockery::mock($class)));
	}

	public function test_an_unknown_app_is_other()
	{
		$this->assertSame('other', ContextProcessor::appTypeFor(new \stdClass()));
	}

	public function test_a_record_written_while_a_job_runs_is_from_a_job()
	{
		$jobs = $this->app()->jobManager();
		(function () { $this->runningJob = ['job_id' => 1, 'execute_class' => 'XF:Cron']; })->call($jobs);

		$this->assertSame('job', ContextProcessor::appType($this->app()));

		(function () { $this->runningJob = null; })->call($jobs);
		$this->assertNotSame('job', ContextProcessor::appType($this->app()));
	}

	public function test_asking_for_the_app_type_does_not_build_the_job_manager()
	{
		$this->app()->container()->decache('job.manager');

		ContextProcessor::appType($this->app());

		$this->assertFalse($this->app()->container()->isCached('job.manager'));
	}
}
