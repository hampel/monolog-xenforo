# Monolog logging service for XenForo add-ons

Gives every XenForo add-on a PSR-3 logger, backed by [Monolog](https://github.com/Seldaek/monolog),
writing to a log file and optionally emailing errors. It is for add-on developers who want logging
without bundling a logging library, and for forum owners whose other add-ons ask for it.

By [Simon Hampel](https://xenforo.com/community/members/sim.4264/).

- [Add-on: Monolog Logging Service](https://xenforo.com/community/resources/monolog-logging-service.6080/)
- [Discussion and support](https://xenforo.com/community/threads/monolog-logging-service.141187/)

## Requirements

- XenForo 2.2 or later
- PHP 7.4 or later

## Installation

Install it as you would any add-on, then review the options under *Options > Monolog*.

To check where messages go, open *Tools > Checks and tests > Test Monolog* in the admin control
panel and run the test. It writes one message at every level; which of them reach the log file or
your inbox depends on the options below.

## Configuration

The options, under *Options > Monolog*:

- **Send Logs to File** — on by default, writing `internal_data/monolog.log`. The path must stay
  inside `internal_data`; see below for anywhere else.
- **Log File Minimum Log Level** — the lowest level written to the file. Default: Warning.
- **Send Logs via Email** — off by default. Sends to the address given, or to the board's contact
  address if none is.
- **Email Minimum Log Level** — the lowest level emailed. Default: Error.
- **Email Subject** — `{board}` is replaced with the board title.
- **Email Deduplication Timeout** — a message already emailed within this many seconds is not sent
  again. Each request's messages arrive as one email.
- **Add Visitor Extra Data** — adds the user id and name to every message. On by default.
- **Add Web Extra Data** — adds the URL, IP address, HTTP method, server name, referrer and user
  agent. Off by default.

### Config.php

A server owner can set the log file and its format in `src/config.php`. These override the
options:

```php
$config['monolog'] = [
    'file' => '/var/log/xenforo/forum.log',   // absolute, relative to internal_data, or false
    'format' => 'json',                        // 'line' (the default) or 'json'
    'site' => 'myforum',                       // names this forum in JSON records
];
```

- **`file`** wins over *Send Logs to File*. An absolute path or a stream such as `php://stderr` is
  used as given; a relative path is inside `internal_data`; `false` turns file logging off.
- **`format`** set to `json` writes one Monolog JSON record per line, with stack traces for
  exceptions, ready for a log collector. Each record also carries `extra.site`, `extra.app`
  (`web`, `admin`, `api`, `cli` or `job`) and `extra.schema`, so a store holding several forums
  can tell them apart.
- **`site`** is what `extra.site` says. It defaults to the board URL's host; set it if that might
  change.

**A log file outside `internal_data` can only be set here**, never in the options. Any admin who
can edit options can change those, and a log file receives text your members can influence.

## Usage

Ask for a channel named for your add-on. You get a PSR-3 logger:

```php
$logger = \XF::app()->get('monolog')->channel('myaddon');
$logger->warning('Payment callback rejected', ['provider' => 'stripe', 'status' => 400]);
```

**Type against `Psr\Log\LoggerInterface`, not a Monolog class.** The Monolog version is an
internal detail of this add-on. It also means the logger can be passed straight to any Composer
package that accepts a PSR-3 logger.

This add-on is usually an optional dependency, so fall back to a null logger when it is not
installed. `Psr\Log\NullLogger` ships with XenForo:

```php
$container['myaddon.log'] = function (\XF\Container $c)
{
    return $c->offsetExists('monolog')
        ? $c['monolog']->channel('myaddon')
        : new \Psr\Log\NullLogger();
};
```

## Events

**Put what happened in the context as `event`**, a stable name, and keep the message for people:

```php
$logger->info('Newsletter sent', ['event' => 'newsletter.sent', 'newsletter_id' => 12]);
```

The message can be reworded freely. The event name is what a search, dashboard or alert matches
on, so treat it as fixed once it ships. Use `noun.verb`, lowercase. Put identifiers in the context
as well, never interpolated into the message, so each can be searched on its own.

## Extending

**Listen to the `hampel_monolog_setup` code event** to add, remove or replace handlers and
processors. It fires once per request, before the logger every channel shares is built:

```php
public static function monologSetup(\XF\App $app, array &$handlers, array &$processors)
{
    $handlers[] = new \Monolog\Handler\StreamHandler('php://stderr', \Monolog\Logger::ERROR);
}
```

These are Monolog objects, not PSR-3 ones, so this is the one place your code depends on the
Monolog version. XenForo 2.4 ships Monolog 3 and loads it ahead of this add-on's Monolog 2. A
handler or processor meant to work on both should leave its record parameter untyped, and assign
`extra` as a whole array rather than one key at a time.

## Upgrading from 4.x

- `newChannel()` still works, as an alias for `channel()`. It is deprecated.
- `Hampel\Monolog\Helper\Log` still works, logging to the `xenforo` channel. It is deprecated.
- `logger()`, `default()`, `stream()` and `visitor()` are gone. Build a custom handler stack with
  the `hampel_monolog_setup` code event instead.
- A log file path outside `internal_data`, entered in the options, is no longer used. Move it to
  `config.php`.

## Licence

MIT — see `LICENSE.md`.
