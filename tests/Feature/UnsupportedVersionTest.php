<?php namespace Tests\Feature;

use Hampel\Monolog\Setup;
use Psr\Log\NullLogger;
use Tests\TestCase;

/**
 * XenForo 2.4 is untested: the code runs on the Monolog 3 it bundles (the MONOLOG3=1 run proves
 * that), but nothing has run on 2.4 itself. Until a release that has, channels there log nothing
 * rather than risk the forum.
 */
class UnsupportedVersionTest extends TestCase
{
	/** @var int */
	private $versionId;

	protected function setUp(): void
	{
		parent::setUp();

		$this->useTemporaryInternalData();
		$this->versionId = \XF::$versionId;
	}

	protected function tearDown(): void
	{
		\XF::$versionId = $this->versionId;

		parent::tearDown();
	}

	public function test_a_channel_on_xenforo_2_4_is_a_null_logger()
	{
		\XF::$versionId = 2040011; // 2.4.0 Alpha 1

		$channel = $this->app()['monolog']->channel('myaddon');
		$channel->error('nowhere to go');

		$this->assertInstanceOf(NullLogger::class, $channel);
		$this->assertSame([], $this->logLines());
	}

	public function test_the_deprecated_entry_points_follow_the_guard()
	{
		\XF::$versionId = 2040011;

		$this->assertInstanceOf(NullLogger::class, $this->app()['monolog']->newChannel('myaddon'));
		$this->assertInstanceOf(NullLogger::class, \Hampel\Monolog\Helper\Log::getMonolog());
	}

	public function test_a_channel_on_xenforo_2_3_is_not_a_null_logger()
	{
		$this->assertNotInstanceOf(NullLogger::class, $this->app()['monolog']->channel('myaddon'));
	}

	public function test_installing_on_xenforo_2_4_warns_that_logging_is_off()
	{
		\XF::$versionId = 2040011;

		$errors = $warnings = [];
		$this->addOnSetup()->checkRequirements($errors, $warnings);

		$this->assertCount(1, $warnings);
		$this->assertStringContainsString('XenForo 2.4', $warnings[0]);
	}

	public function test_installing_on_xenforo_2_3_does_not_warn()
	{
		$errors = $warnings = [];
		$this->addOnSetup()->checkRequirements($errors, $warnings);

		$this->assertSame([], $warnings);
	}

	private function addOnSetup(): Setup
	{
		return new Setup($this->app()->addOnManager()->getById('Hampel/Monolog'), $this->app());
	}
}
