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
	 * The table replaces Logger::getLevels(), gone in Monolog 3. Checked against whichever
	 * Monolog is loaded - v2's list, or v3's Level enum - so neither can drift from it.
	 */
	public function test_the_level_table_matches_monolog()
	{
		if (method_exists(\Monolog\Logger::class, 'getLevels'))
		{
			$fromMonolog = array_map('ucfirst', array_map('strtolower', array_flip(\Monolog\Logger::getLevels())));
		}
		else
		{
			$fromMonolog = [];
			foreach (\Monolog\Level::cases() AS $level)
			{
				$fromMonolog[$level->value] = $level->name;
			}
		}

		$this->assertSame($fromMonolog, FileMinimumLogLevel::LEVELS);
	}

	public static function unsafeLogFiles(): array
	{
		return [
			'absolute' => ['/var/www/html/shell.php'],
			'parent directory' => ['../../shell.php'],
			'parent directory inside' => ['logs/../../shell.php'],
			'windows absolute' => ['C:\\inetpub\\shell.php'],
			'backslash parent' => ['..\\shell.php'],
			'stream wrapper' => ['php://output'],
			'phar wrapper' => ['phar://internal_data/x.phar/shell.php'],
		];
	}

	/**
	 * The option is editable by any admin with option permission, and the file it names receives
	 * log lines that can carry user-supplied text - so it must stay inside internal_data. An
	 * absolute path is for config.php, which only the server owner controls.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('unsafeLogFiles')]
	public function test_the_option_refuses_a_path_outside_internal_data($file)
	{
		$option = $this->app()->finder('XF:Option')->whereId('monologLogFile')->fetchOne();
		$value = ['enabled' => true, 'logfile' => $file];

		$this->assertFalse(LogFile::verifyOption($value, $option));
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('unsafeLogFiles')]
	public function test_a_stored_path_outside_internal_data_is_not_used($file)
	{
		// a value saved before 5.0 never went through verifyOption()
		$this->setOption('monologLogFile', ['enabled' => true, 'logfile' => $file]);

		$this->assertSame('monolog.log', LogFile::getLogFile());
	}

	public function test_the_option_accepts_a_path_inside_internal_data()
	{
		$option = $this->app()->finder('XF:Option')->whereId('monologLogFile')->fetchOne();
		$value = ['enabled' => true, 'logfile' => 'logs/forum.log'];

		$this->assertTrue(LogFile::verifyOption($value, $option));
		$this->assertSame('logs/forum.log', $value['logfile']);
	}

	public function test_the_email_address_is_empty_when_email_is_disabled()
	{
		$this->setOption('monologSendEmail', ['enabled' => false, 'email' => 'logs@example.com']);

		$this->assertSame('', SendEmail::getAddress());
	}
}
