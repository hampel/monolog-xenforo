Monolog logging implementation for XenForo 2.0
==============================================

This XenForo 2.0 addon adds logging functionality using the Monolog library from https://github.com/Seldaek/monolog

By [Simon Hampel](https://twitter.com/SimonHampel).

Requirements
------------

This addon requires PHP 7.0 or higher and only works on XenForo 2.0.x 

Installation
------------

Install as per normal addon installation.

Be sure to check the configuration settings.

There is a test routine in the admin control panel: go to `AdminCP > Tools > Checks and tests > Test Monolog` and click
the "Test" button to generate some test logging messages. Messages will appear in the log or other sources based on 
your configuration settings.

Usage
-----

By default, this addon will log events to a file called `internal_data/monolog.log` - this is configurable.

To use the default logging facility, do the following in your addon code:

	:::php
	use Hampel\Monolog\Helper\Log;
	Log::info('an info message', ['context' => 'foo']);
	Log::error('an error message', ['data' => 'bar']);

However, it is recommended that you create your own channel for your addon to make it easier to filter log entries:

	:::php
	$logger = \XF::app()->get('monolog')->newChannel('myaddon');
	$logger->warning('a warning message', ['context' => 'foo']);

Refer to the documentation for more detailed
[usage instructions for Monolog](https://github.com/Seldaek/monolog/blob/master/doc/01-usage.md).

You can create your own handler stack to customise how things are logged:

	:::php
	use Monolog\Logger;
	
	$monolog = \XF::app()->get('monolog');
	$streamhandler = $monolog->stream(); 	// return our default stream handler for logging to a file 
										 		  			// (or create your own!)
	
	/** @var \Monolog\Logger $logger */
	$logger = $monolog->logger('myaddon');
	$logger->pushHandler($streamhandler); // push our stream handler onto the handler stack
	// you can apply any other customisations you like here as well by adding custom handlers, formatters or processors
	
	$logger->critical('a critical message', ['context' => 'foo']);

Refer to
[Handlers, Formatters and Processors](https://github.com/Seldaek/monolog/blob/master/doc/02-handlers-formatters-processors.md)
for more information.

