Monolog logging implementation for XenForo 2.x
==============================================

This XenForo 2.x addon adds logging functionality using the Monolog library from https://github.com/Seldaek/monolog

By [Simon Hampel](https://xenforo.com/community/members/sim.4264/).

* [Addon: Monolog](https://xenforo.com/community/resources/monolog-logging-service.6080/)
* [Discussion and support: Monolog](https://xenforo.com/community/threads/monolog-logging-service.141187/)

Requirements
------------

This addon requires XenForo 2.2 or higher, and PHP 7.4 or higher

Installation
------------

Install as per normal addon installation.

Be sure to check the configuration settings.

There is a test routine in the admin control panel: go to `AdminCP > Tools > Checks and tests > Test Monolog` and click
the "Test" button to generate some test logging messages. Messages will appear in the log or other sources based on 
your configuration settings.

Usage
-----

By default, this addon logs to a file called `internal_data/monolog.log` - this is configurable.
It can also email records at or above a chosen level.

Ask for a channel named for your addon. You get a PSR-3 logger:

```php
$logger = \XF::app()->get('monolog')->channel('myaddon');
$logger->warning('a warning message', ['context' => 'foo']);
```

Type against `Psr\Log\LoggerInterface`, not a Monolog class - the Monolog version is an internal
detail of this addon. That also means the logger can be handed straight to any Composer package
that accepts a PSR-3 logger.

This addon is usually an optional dependency, so fall back to a null logger when it is not
installed - `Psr\Log\NullLogger` ships with XenForo:

```php
$container['myaddon.log'] = function (\XF\Container $c)
{
    return $c->offsetExists('monolog')
        ? $c['monolog']->channel('myaddon')
        : new \Psr\Log\NullLogger();
};
```

### Upgrading from 4.x

- `newChannel()` still works, as an alias for `channel()`. It is deprecated.
- `Hampel\Monolog\Helper\Log` still works, logging to the `xenforo` channel. It is deprecated.
- `logger()`, `default()`, `stream()` and `visitor()` have been removed. Custom handler stacks
  built from them are replaced by an extension point in a later 5.0 release.
