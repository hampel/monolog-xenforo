<?php namespace Tests\Feature;

use Hampel\Monolog\Helper\Log;
use Hampel\Monolog\Setup;
use Hampel\Monolog\SubContainer\MonologApi;
use Tests\TestCase;

/**
 * A XenForo newer than this release was tested on: the code runs on Monolog 2 and 3 - the
 * MONOLOG3=1 run proves that - but nothing has run on that XenForo itself. So the install checks
 * say so, and logging carries on.
 */
class UntestedXenForoTest extends TestCase
{
	/** @var int */
	private $versionId;

	protected function setUp(): void
	{
		parent::setUp();

		$this->useTemporaryInternalData();
		$this->setOption('monologAddVisitorExtra', false);
		$this->versionId = \XF::$versionId;
	}

	protected function tearDown(): void
	{
		\XF::$versionId = $this->versionId;

		parent::tearDown();
	}

	public function test_channels_still_log_on_an_untested_xenforo()
	{
		\XF::$versionId = MonologApi::UNTESTED_FROM + 11; // its first alpha

		$this->app()['monolog']->channel('myaddon')->error('still here');
		$this->app()['monolog']->newChannel('other')->error('and here');
		Log::error('and here too');

		$this->assertCount(3, $this->logLines());
	}

	public function test_installing_on_an_untested_xenforo_warns()
	{
		\XF::$versionId = MonologApi::UNTESTED_FROM + 11;

		$errors = $warnings = [];
		$this->addOnSetup()->checkRequirements($errors, $warnings);

		$this->assertSame([], $errors);
		$this->assertCount(1, $warnings);
		$this->assertStringContainsString('not been tested on the version of XenForo you are running',
			$warnings[0]);
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
