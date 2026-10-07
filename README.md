# Monolog logging service for XenForo add-ons

Gives every XenForo add-on a PSR-3 logger, backed by [Monolog](https://github.com/Seldaek/monolog),
writing to a log file and optionally sending errors by email or to Slack. It is for add-on
developers who want logging without bundling a logging library, and for forum owners whose other
add-ons ask for it.

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
  again. Each request's messages arrive as one email. *The same message* means the same channel,
  level and text — and, where the record carries one, the same exception or `fingerprint`; see
  *Events*.
- **Post to Slack** — off by default. Posts to a Slack channel through an [incoming
  webhook](https://api.slack.com/messaging/webhooks): create one in your Slack workspace and paste
  its URL here. Each request's messages arrive as one message, and a message already posted within
  five minutes is not posted again. **The webhook URL is a credential** — anyone holding it can
  post to that channel — so consider setting it in `config.php` instead, which keeps it out of the
  database and off the options page.
- **Slack Minimum Log Level** — the lowest level posted. Default: Error.
- **Add Request ID** — adds an id to every record, the same for every record from one request, so
  everything a request logged can be found from any one of its lines. Uses the web server's request
  id where it provides one — Apache's `UNIQUE_ID`, or an `X-Request-ID` header from a proxy — so a
  line can be matched to the access log. On by default; JSON records always carry it.
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
        'channels' => ['myaddon' => 'debug'],   // a level for one channel, not every channel
    ],
    'email' => [                                // false turns email off
        'to' => 'admin@example.com',
        'level' => 'error',
        'subject' => 'Errors on {board}',
        'dedup' => 300,                         // seconds
        'channels' => ['myaddon' => 'warning'],
    ],
    'slack' => [                                // false turns Slack off
        'webhook' => 'https://hooks.slack.com/services/...',
        'level' => 'error',
        'dedup' => 300,                         // seconds
    ],
    'request_id' => true,
    'visitor' => true,
    'web' => false,
    'site' => 'myforum',
];
```

- **`file.path`, `email.to` or `slack.webhook` also switches that output on**, and `false` in place
  of the section switches it off — which also locks every option in that section on the options
  page, since none has any effect. `'file' => ['level' => 'error']` alone fixes the level and leaves
  the rest to the options page.
- **Levels** are `debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert` and
  `emergency`, in any case.
- **`channels` sets a level for one channel** — by convention, one add-on — raising or lowering it
  from the section's level. Every other channel keeps the section's level. It is only here, not on
  the options page: it is for a trial, and is best left in a file someone has to edit to undo.
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

- **`handlers`** and **`processors`** are lists, added to the built-in file, email and Slack
  handlers and to the processors the options switch on.
- **`file.formatter`** replaces the log file's formatter, in place of `file.format`.

**Each entry is a function that returns the object, not the object itself** — the same as
XenForo's own `$config['fsAdapters']`. `config.php` is read before add-on classes can load, so
`new \Monolog\...` written directly there is a fatal error on every page. An entry that is not a
function, or returns the wrong kind of object, is skipped and reported in the server error log.

These are Monolog classes, so the same advice as for the code event applies: XenForo 2.4 loads its
own Monolog 3 in place of this add-on's Monolog 2. Monolog's built-in handlers construct the same
way on both; a processor of your own should leave its record parameter untyped.

### Choosing log levels

- **Log file: Warning**, the default, records what went wrong or nearly did. **Info** adds a line
  when routine work completes — useful to confirm scheduled jobs ran, at the cost of a larger file.
- **Email and Slack: Error.** Both are for things someone should fix. Below Warning they carry
  routine records, and every request that logs anything sends a message.
- **Debug is for tracing one problem, briefly.** Turn it on, reproduce the problem, turn it back
  off.

**The levels on the options page apply to every add-on that logs through this one, not only the
one you are investigating.** At Debug, some add-ons record personal data or the content of the
emails they send. To trace one add-on, raise only its channel in `config.php`, and leave the rest
where they were:

```php
$config['monolog'] = ['file' => ['channels' => ['myaddon' => 'debug']]];
```

Check what lands in the log file before leaving Debug on, and delete the file when the
investigation is over.

## Command line

Two commands, run from the XenForo root:

```bash
php cmd.php monolog:config                   # what this forum is configured to log
php cmd.php monolog:validate                 # whether it can - and logs a record at every level
php cmd.php monolog:validate --unattended    # the same, without writing or sending anything
php cmd.php monolog:validate --unattended --strict    # for a monitor: a warning exits 2
```

**`monolog:config` reads and prints, and changes nothing.** It shows every setting, where each
comes from — `config.php` or the options page — and the full path of the log file. The Slack
webhook is shown only as set, with its host: the URL is a credential.

**`monolog:validate` exercises the real thing.** It warns if email or Slack is set below Warning,
checks the log file can be written, and that the record of what has already been sent can be —
without it every repeat is sent again — builds the handlers and anything `config.php` adds, then
writes one record at each of the eight levels to the `monolog-validate` channel. It counts what
reached the log file against the level you set, and reports what was emailed and posted. A post
Slack refuses — a deleted or revoked webhook — or cannot be reached appears in the server error log,
and fails the run. Each check reports `[ ok ]`, `[warn]`, `[fail]`, or a blank marker for a check
that did not apply. With the log file, email and Slack all off it warns that records go nowhere.

**Running it writes to your log, sends email and posts to Slack**, for whichever is on — that is
the proof each works. Use `--unattended` where nobody is watching, such as a deploy step; it skips
the level sweep and still checks everything else.

**It exits 1 if anything failed and 0 otherwise — warnings included** — so a deploy or a cron job
can depend on it. `--strict` makes a warning exit 2, for a monitor that should hear of one:

| | all fine | warnings only | any failure |
|---|---|---|---|
| `monolog:validate` | 0 | 0 | 1 |
| `monolog:validate --strict` | 0 | 2 | 1 |

`--strict` changes only the exit code, so pair it with `--unattended` for anything that runs on a
schedule. **This is not the monitoring-plugin numbering**, where 1 is a warning and 2 is critical:
here 1 always means failed. Map the values explicitly in whatever reads them.

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

**Name the channel once, in lowercase, and keep it.** It is how a forum owner finds your add-on's
lines in the log, and how they raise or lower its level alone in `config.php`. Ask for it wherever
you need it: the same name returns the same logger, and creating one builds nothing until a
record is written.

### Supporting 4.x and 5.x

**`channel()` arrived in 5.0; 4.x has only `newChannel()`.** If your add-on calls `channel()` and a
forum installs it before upgrading this add-on, every page that logs fails with an undefined
method. Until you can require 5.0, check for it:

```php
$container['myaddon.log'] = function (\XF\Container $c)
{
    if (!$c->offsetExists('monolog'))
    {
        return new \Psr\Log\NullLogger();
    }

    $monolog = $c['monolog'];

    // channel() arrived in 5.0; newChannel() is the 4.x name, deprecated in 5.x
    return method_exists($monolog, 'channel') ? $monolog->channel('myaddon') : $monolog->newChannel('myaddon');
};
```

Or require 5.0 outright, if your add-on cannot work without logging:

```json
"require": {
    "Hampel/Monolog": [5000070, "Monolog Logging Service 5.0.0+"]
}
```

- **A wrapper class that null-checks the logger before every call is no longer needed.** The
  container above always returns a logger, so call it directly.
- **If your code implements PSR-3 itself** — a class implementing `Psr\Log\LoggerInterface`,
  extending `AbstractLogger`, or a trait using `LoggerTrait` — **declare `log()` with a `: void`
  return type.** XenForo 2.4 bundles a newer psr/log whose `log()` is typed, and an implementation
  without the return type is a fatal error when its class loads. The return type works on today's
  XenForo too.
- **A workaround for 4.x building XenForo's mailer whenever a channel was created** — which
  recursed for a mail add-on that logs — can go once you require 5.0. In 5.x the mailer is built
  only when a record is emailed. Keep it while 4.x is still supported.

### Choosing a level

Pick the level by what someone reading the line should do:

| level | the line says | the reader should |
|---|---|---|
| `debug` | what happened, step by step | read it only while tracing a problem |
| `info` | a unit of work finished, with its counts | confirm things ran |
| `notice` | something normal but noteworthy | glance at it |
| `warning` | something went wrong and was handled | look at it this week |
| `error` | a unit of work failed | fix it soon |
| `critical` | a component is unavailable — an external API, say | fix it today |
| `alert` | the site is down, or data will be lost | act now |
| `emergency` | the system is unusable | everyone |

- **`error` is per unit of work, not per line of code.** Log a failure once, where it is handled,
  with the exception as `['exception' => $e]`. That records its class, message, file and line, and
  in the JSON format its stack trace too.
- **`warning` means handled.** If the outcome is still correct, it is a warning at most; if it is
  wrong, it is an error.
- **Nothing per page view above `debug`.** Code on the request path runs on every request.
  Summarise instead: one `info` line from a job, with the counts.

**Never log these, at any level — `debug` included:**

- passwords, API keys, tokens, session IDs, cookies and `Authorization` headers;
- links that act as credentials — password-reset, email-confirmation, unsubscribe and login links;
- message bodies — emails, private conversations, posts, form submissions;
- whole request or response payloads — log the method, path, status and duration instead.

Prefer an identifier to personal data: a `user_id` rather than an email address. If a line must
carry personal data, say so in your add-on's documentation, so whoever turns its level up knows.
Redact at the call site; nothing downstream can reliably tell a token from an ID.

Before adding a log call, ask:

1. What should a reader do on seeing it? That is its level.
2. Does any value in it appear on the list above? Log its ID instead.
3. Can it fire on every page view? Then it is `debug` at most, and probably a counter instead.
4. Is the message a fixed string, with everything that varies in the context? See *Events*.
5. Is an exception passed once, as `exception`, where it is handled?
6. If it carries personal data, is that written down?

## Events

**Put what happened in the context as `event`**, a stable name, and keep the message for people:

```php
$logger->info('Newsletter sent', ['event' => 'newsletter.sent', 'newsletter_id' => 12]);
```

The message can be reworded freely. The event name is what a search, dashboard or alert matches
on, so treat it as fixed once it ships. Use `noun.verb`, lowercase. Put identifiers in the context
as well, never interpolated into the message, so each can be searched on its own.

**Add a `fingerprint` when one fixed message stands for many different occurrences.** Email and
Slack skip a record already sent within the deduplication timeout, and a fixed message makes every
occurrence look like the last. The fingerprint is whatever tells them apart:

```php
$logger->error('Import failed', ['event' => 'import.failed', 'fingerprint' => "feed:{$feedId}"]);
```

Without it, feed 7 failing a minute after feed 3 is a repeat, and nobody is told. A record that
carries an `exception` needs no fingerprint: its class, file and line are used. Keep the
fingerprint coarse — an id that changes on every record, such as a timestamp, turns the
protection off.

**Mark a record you send on purpose with `'probe' => true`** — a test page, a validate command,
anything that logs at Error to prove that Error arrives. This add-on's own do: *Test Monolog* and
`monolog:validate` both carry it. An alert over the log can then leave every probe out with one
filter, whichever add-on sent it.

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

For an add-on that logs through this one, see *Supporting 4.x and 5.x* under *Usage*.

- `newChannel()` still works, as an alias for `channel()`. It is deprecated.
- `Hampel\Monolog\Helper\Log` still works, logging to the `xenforo` channel. It is deprecated.
- `logger()`, `default()`, `stream()` and `visitor()` are gone. Build a custom handler stack with
  the `hampel_monolog_setup` code event instead.
- A log file path outside `internal_data`, entered in the options, is no longer used. Move it to
  `config.php`.

## Licence

MIT — see `LICENSE.md`.
