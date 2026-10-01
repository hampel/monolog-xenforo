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

### Naming events

Put what happened in the context as `event`, a stable name, and keep the message for people:

```php
$logger->info('Newsletter sent', ['event' => 'newsletter.sent', 'newsletter_id' => 12, 'count' => 340]);
```

The message can be reworded freely; the event name is what a search, dashboard or alert matches
on, so treat it as fixed once it ships. Use `noun.verb`, lowercase. Put identifiers in the context
too, never interpolated into the message, so each one can be searched on its own.

### Configuring in config.php

The options in the admin control panel cover most forums. A server owner can override the log
file, or switch to JSON, in `src/config.php`:

```php
$config['monolog'] = [
    'file' => '/var/log/xenforo/forum.log',   // absolute, relative to internal_data, or false
    'format' => 'json',                        // 'line' (the default) or 'json'
    'site' => 'myforum',                       // names this forum in JSON records
];
```

- `file` set here wins over the log file option. An absolute path, or a stream such as
  `php://stderr`, is used as given; a relative path is inside `internal_data`; `false` turns file
  logging off.
- The log file option itself only accepts a path inside `internal_data`, because any admin who can
  edit options can change it. Paths anywhere else belong here.
- `json` writes one Monolog JSON record per line, with stack traces for exceptions - suitable for
  shipping to a log collector. Each record also carries `extra.site`, `extra.app` (`web`, `admin`,
  `api`, `cli` or `job`) and `extra.schema`, so a store holding several forums can tell them apart.
- `site` is what `extra.site` says. It defaults to the board URL's host; set it if that may change.

### Adding handlers and processors

Listen to the `hampel_monolog_setup` code event. It fires once per request, before the logger
every channel shares is built, with that logger's handlers and processors:

```php
public static function monologSetup(\XF\App $app, array &$handlers, array &$processors)
{
    $handlers[] = new \Monolog\Handler\StreamHandler('php://stderr', \Monolog\Logger::ERROR);
}
```

These are Monolog objects rather than PSR-3 ones, so this is the one place your code depends on
the Monolog version. XenForo 2.4 ships Monolog 3 and loads it ahead of this addon's Monolog 2: a
custom handler or processor that should work on both leaves its record parameter untyped, and
assigns `extra` as a whole array rather than one key at a time.

### Upgrading from 4.x

- `newChannel()` still works, as an alias for `channel()`. It is deprecated.
- `Hampel\Monolog\Helper\Log` still works, logging to the `xenforo` channel. It is deprecated.
- `logger()`, `default()`, `stream()` and `visitor()` have been removed. Build a custom handler
  stack with the `hampel_monolog_setup` code event instead.
