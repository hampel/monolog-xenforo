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

Everything can be set under *Options > Monolog*, or in `src/config.php`. **A setting in
`config.php` wins**: the options page then shows its value, marked *Set in config.php*, with no
field to change it.

### The options

- **Send Logs to File** — on by default, writing `internal_data/monolog.log`. The path must stay
  inside `internal_data`; anywhere else needs `config.php`.
- **Log File Minimum Log Level** — the lowest level written to the file. Default: Warning.
- **Log File Format** — *Line*, one readable line per record, or *JSON*, one Monolog JSON record
  per line with stack traces for exceptions, for a log collector. JSON records also carry
  `extra.site`, `extra.app` (`web`, `admin`, `api`, `cli` or `job`) and `extra.schema`, so a store
  holding several forums can tell them apart.
- **Send Logs via Email** — off by default. Sends to the address given, or to the board's contact
  address if none is.
- **Email Subject** — `{board}` is replaced with the board title.
- **Email Minimum Log Level** — the lowest level emailed. Default: Error. Keep it at Error or
  above if another add-on logs every email it sends: below that, each request that sends mail also
  sends one log email about it. That cannot loop — the record about the log email itself is never
  emailed — but on a busy forum it doubles the mail.
- **Email Deduplication Timeout** — a message already emailed within this many seconds is not sent
  again. Each request's messages arrive as one email.
- **Add Visitor Extra Data** — adds the user id and name to every message. On by default.
- **Add Web Extra Data** — adds the URL, IP address, HTTP method, server name, referrer and user
  agent. Off by default.
- **Site Name** — what JSON records carry in `extra.site`. Empty uses the board URL's host without
  a leading `www.`, so `www.example.com` becomes `example.com` and `forum.example.com` is kept. Set
  it if the domain might change.

### Config.php

Every option has a `config.php` equivalent. **Set only what you want to fix in place** — anything
left out stays on the options page:

```php
$config['monolog'] = [
    'file' => [                                 // false turns file logging off
        'path' => '/var/log/xenforo/forum.log', // absolute, or relative to internal_data
        'format' => 'json',                     // 'line' or 'json'
        'level' => 'warning',                   // a level name, or Monolog's number for it
    ],
    'email' => [                                // false turns email off
        'to' => 'admin@example.com',
        'level' => 'error',
        'subject' => 'Errors on {board}',
        'dedup' => 300,                         // seconds
    ],
    'visitor' => true,
    'web' => false,
    'site' => 'myforum',
];
```

- **`file.path` or `email.to` also switches that output on**, and `false` in place of the section
  switches it off — which also locks every option in that section on the options page, since none
  has any effect. `'file' => ['level' => 'error']` alone fixes the level and leaves the rest to the
  options page.
- **Levels** are `debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert` and
  `emergency`, in any case.
- **A log file outside `internal_data`, or a stream such as `php://stderr`, can only be set here.**
  Any admin who can edit options can change those, and a log file receives text your members can
  influence.
- **A value that cannot be used** — an unknown level or format — is ignored, and the option
  decides.

#### Handlers, processors and the formatter

`config.php` can also add to the stack, with no add-on — send errors to syslog, write to a log
collector, or format the log file differently:

```php
$config['monolog'] = [
    'file' => [
        'formatter' => function () {
            return new \Monolog\Formatter\LineFormatter("[%datetime%] %channel%.%level_name%: %message%\n");
        },
    ],
    'handlers' => [
        function () {
            return new \Monolog\Handler\SyslogHandler('forum', LOG_USER, \Monolog\Logger::ERROR);
        },
    ],
    'processors' => [
        function () { return new \Monolog\Processor\UidProcessor(); },
    ],
];
```

- **`handlers`** and **`processors`** are lists, added to the built-in file and email handlers and
  to the processors the options switch on.
- **`file.formatter`** replaces the log file's formatter, in place of `file.format`.

**Each entry is a function that returns the object, not the object itself** — the same as
XenForo's own `$config['fsAdapters']`. `config.php` is read before add-on classes can load, so
`new \Monolog\...` written directly there is a fatal error on every page. An entry that is not a
function, or returns the wrong kind of object, is skipped and reported in the server error log.

These are Monolog classes, so the same advice as for the code event applies: XenForo 2.4 loads its
own Monolog 3 in place of this add-on's Monolog 2. Monolog's built-in handlers construct the same
way on both; a processor of your own should leave its record parameter untyped.

## Command line

Two commands, run from the XenForo root:

```bash
php cmd.php monolog:config                   # what this forum is configured to log
php cmd.php monolog:validate                 # whether it can - and logs a record at every level
php cmd.php monolog:validate --unattended    # the same, without writing or sending anything
```

**`monolog:config` reads and prints, and changes nothing.** It shows every setting, where each
comes from — `config.php` or the options page — and the full path of the log file.

**`monolog:validate` exercises the real thing.** It warns if email is set below Warning, checks the
log file can be written, builds the handlers and anything `config.php` adds, then writes one record
at each of the eight levels to the `monolog-validate` channel. It counts what reached the log file
against the level you set, and reports what was emailed and to whom. Each check reports `[ ok ]`,
`[warn]`, `[fail]`, or a blank marker for a check that did not apply. It exits 1 if anything failed
and 0 otherwise — warnings included — so a deploy or a cron job can depend on it.

**Running it writes to your log and sends email**, if email is on — that is the proof both work.
Use `--unattended` where nobody is watching, such as a deploy step; it skips the level sweep and
still checks everything else.

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
