<?php namespace Tests\Feature;

use Tests\TestCase;

/**
 * Proves the MONOLOG3=1 run is really running against Monolog 3 and psr/log 3. Without it, a
 * simulation that silently fell back to the bundled Monolog 2 would pass and prove nothing.
 */
class Monolog3SimulationTest extends TestCase
{
	public function test_the_classes_come_from_the_versions_the_run_asked_for()
	{
		$monolog = (new \ReflectionClass(\Monolog\Logger::class))->getFileName();
		$psrLog = (new \ReflectionClass(\Psr\Log\LoggerInterface::class))->getFileName();

		if (self::simulatingMonolog3())
		{
			$this->assertStringContainsString('/tests/monolog3/vendor/', $monolog);
			$this->assertStringContainsString('/tests/monolog3/vendor/', $psrLog);
			$this->assertTrue(enum_exists(\Monolog\Level::class), 'Monolog 3 has the Level enum');
		}
		else
		{
			$this->assertStringNotContainsString('/tests/monolog3/', $monolog);
			$this->assertFalse(class_exists(\Monolog\Level::class), 'Monolog 2 has no Level enum');
		}
	}
}
