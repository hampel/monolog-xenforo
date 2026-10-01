<?php namespace Tests\Unit;

use Hampel\Monolog\Option\EmailMinimumLogLevel;
use Hampel\Monolog\Option\EmailSubject;
use Hampel\Monolog\Option\FileMinimumLogLevel;
use Hampel\Monolog\Option\LogFile;
use Hampel\Monolog\Option\SendEmail;
use Tests\TestCase;

class OptionTest extends TestCase
{
	public function test_the_subject_replaces_the_board_token()
	{
		$this->setOptions([
			'monologEmailSubject' => 'Errors on {board}',
			'boardTitle' => 'Test Board',
		]);

		$this->assertSame('Errors on Test Board', EmailSubject::get());
	}

	public function test_an_empty_subject_falls_back_to_monolog()
	{
		$this->setOption('monologEmailSubject', '');

		$this->assertSame('Monolog', EmailSubject::get());
	}

	public function test_an_unset_file_level_falls_back_to_warning()
	{
		$this->setOption('monologFileMinimumLogLevel', 0);

		$this->assertSame(300, FileMinimumLogLevel::get());
	}

	public function test_an_unset_email_level_falls_back_to_error()
	{
		$this->setOption('monologEmailMinimumLogLevel', 0);

		$this->assertSame(400, EmailMinimumLogLevel::get());
	}

	/**
	 * A fresh install holds the option's default_value verbatim - XenForo copies it into
	 * option_value on insert without casting - so "disabled" arrives as the string "0" until an
	 * admin saves the options page and XenForo stores a real false.
	 */
	public function test_email_is_disabled_by_the_default_value_a_fresh_install_holds()
	{
		$this->setOption('monologSendEmail', ['enabled' => '0', 'email' => '']);

		$this->assertFalse(SendEmail::isEnabled());
	}

	public function test_the_log_file_is_disabled_by_a_stored_string_zero()
	{
		$this->setOption('monologLogFile', ['enabled' => '0', 'logfile' => 'monolog.log']);

		$this->assertFalse(LogFile::isEnabled());
	}

	/**
	 * The table replaces Logger::getLevels(), gone in Monolog 3. Checked against Monolog 2's own
	 * list while that is what the add-on bundles, so the two cannot drift.
	 */
	public function test_the_level_table_matches_monolog()
	{
		$fromMonolog = array_map('ucfirst', array_map('strtolower', array_flip(\Monolog\Logger::getLevels())));

		$this->assertSame($fromMonolog, FileMinimumLogLevel::LEVELS);
	}

	public function test_the_level_select_offers_every_level()
	{
		$option = $this->app()->finder('XF:Option')->whereId('monologFileMinimumLogLevel')->fetchOne();

		$html = FileMinimumLogLevel::renderSelect($option, [
			'inputName' => 'options[monologFileMinimumLogLevel]',
			'inputType' => 'select',
			'listedHtml' => '',
			'explainHtml' => '',
			'hintHtml' => '',
			'editLink' => '',
			'title' => 'Level',
		]);

		foreach (FileMinimumLogLevel::LEVELS AS $level => $label)
		{
			$this->assertStringContainsString("value=\"{$level}\"", (string) $html);
		}
	}

	public function test_the_email_address_is_empty_when_email_is_disabled()
	{
		$this->setOption('monologSendEmail', ['enabled' => false, 'email' => 'logs@example.com']);

		$this->assertSame('', SendEmail::getAddress());
	}
}
