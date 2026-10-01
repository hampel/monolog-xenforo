<?php namespace Tests\Feature;

use Hampel\Monolog\Helper\Log;
use Hampel\Monolog\Setup;
use Tests\TestCase;

/**
 * XenForo 2.4 is untested: the code runs on the Monolog 3 it bundles - the MONOLOG3=1 run proves
 * that - but nothing has run on 2.4 itself. So the install checks say so, and logging carries on.
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

	public function test_channels_still_log_on_xenforo_2_4()
	{
		\XF::$versionId = 2040011; // 2.4.0 Alpha 1

		$this->app()['monolog']->channel('myaddon')->error('still here');
		$this->app()['monolog']->newChannel('other')->error('and here');
		Log::error('and here too');

		$this->assertCount(3, $this->logLines());
	}

	public function test_installing_on_xenforo_2_4_warns_that_it_is_untested()
	{
		\XF::$versionId = 2040011;

		$errors = $warnings = [];
		$this->addOnSetup()->checkRequirements($errors, $warnings);

		$this->assertSame([], $errors);
		$this->assertCount(1, $warnings);
		$this->assertStringContainsString('not been tested on XenForo 2.4', $warnings[0]);
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
