<?php namespace Tests\Unit;

use Hampel\Monolog\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConfigReaderTest extends TestCase
{
	public static function levels(): array
	{
		return [
			'lower-case name' => ['warning', 300],
			'upper-case name' => ['ERROR', 400],
			'padded name' => [' critical ', 500],
			'number' => [250, 250],
			'numeric string' => ['600', 600],
			'unknown name' => ['loud', null],
			'unknown number' => [350, null],
			'not a level at all' => [[], null],
		];
	}

	#[DataProvider('levels')]
	public function test_a_level_is_read_by_name_or_number($value, $expected)
	{
		$this->assertSame($expected, Config::toLevel($value));
	}

	public static function sections(): array
	{
		return [
			'absent' => [null, null],
			'false turns it off' => [false, false],
			'true turns it on' => [true, true],
			'a target turns it on' => [['path' => 'x.log'], true],
			'other keys leave it to the option' => [['level' => 'error'], null],
			'an empty target leaves it to the option' => [['path' => ''], null],
		];
	}

	#[DataProvider('sections')]
	public function test_a_section_is_on_off_or_left_to_the_option($file, $expected)
	{
		if ($file !== null)
		{
			$this->setConfig('monolog', ['file' => $file]);
		}

		$this->assertSame($expected, Config::enabled('file'));
	}

	public function test_email_is_turned_on_by_a_recipient()
	{
		$this->setConfig('monolog', ['email' => ['to' => 'a@example.com']]);

		$this->assertTrue(Config::enabled('email'));
	}

	public function test_a_missing_block_reads_as_empty()
	{
		$this->assertSame([], Config::all());
		$this->assertNull(Config::get('file', 'level'));
	}
}
