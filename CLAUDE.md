# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Scope

This is one XenForo add-on — `Hampel/Monolog`, "Monolog Logging Service". It sits at
`src/addons/Hampel/Monolog/` inside a XenForo install and is its own git repository; the install
around it is not, and any sibling add-ons beside it are other repositories.

Where the install has an `AGENTS.md` at its root, read it first — it carries the XF conventions,
the `_output`/`_data` boundary, the `cmd.php` command signatures and the per-add-on git layout.
This file covers only what is specific to this add-on.

## What this add-on is

**A service for other add-ons, not a feature.** It registers a `monolog` sub-container on the XF
app so any add-on can log through Monolog without bundling it, and adds nothing to the public side
of a forum. `README.md` is the consumer-facing guide.

**The public API is one method returning a PSR-3 logger:**

```php
$logger = \XF::app()->get('monolog')->channel('myaddon');   // Psr\Log\LoggerInterface
```

**That return type is the contract, and nothing Monolog-specific is.** Consumers — and the Composer
packages they hand the logger to — type against `Psr\Log\LoggerInterface`, so the Monolog major
version stays an internal detail. Do not widen the API with methods that return Monolog classes.

Consumers treat the add-on as optional: none names it in `addon.json` `require`. So they check
`$container->offsetExists('monolog')` and fall back to `Psr\Log\NullLogger`, which XenForo core
ships. Anything that breaks that pattern breaks them.

Two 4.x entry points survive as deprecated shims, and `tests/` pins both:

- **`newChannel($name)`** — an alias for `channel()`. Every consumer written against 4.x calls it.
- **`Helper\Log`** — static PSR-3 methods on the `xenforo` channel, documented since 2.1.0.

Add-ons wanting more than a logger — their own handlers or processors — use the
`hampel_monolog_setup` code event, which is the one surface that exposes Monolog types.

The moving parts, and where each is wired:

| piece | registered by | does |
|---|---|---|
| `SubContainer/MonologApi.php` | `Listener::appSetup` (`app_setup`) as `$container['monolog']` | builds handlers, processors and the base logger from the options; hands out channels |
| `Handler/LazyHandler.php` | `MonologApi`, around the email stack | defers building a handler until a record reaches its level |
| `Handler/XenForoMailHandler.php` | inside that `LazyHandler` | sends records through XF's own `Mail`, on 2.2 and 2.3 alike |
| `Processor/VisitorProcessor.php` | `MonologApi`, behind `monologAddVisitorExtra` | adds `extra.visitor` |
| `Processor/ContextProcessor.php` | `MonologApi`, when the format is `json` | adds `extra.schema`, `extra.site` and `extra.app` |
| `Option/*.php` | the `callback` edit format and validation of every option in `_output/options/` | getters that read `config.php` first, then the option, then a default; and each option's renderer |
| `Config.php` | nothing — static calls | reads `$config['monolog']`, including level names |
| `XF/Admin/Controller/Tools.php` | class extension of `XF\Admin\Controller\Tools` | the ACP *Test Monolog* page |
| `Test/*.php` | `Listener::appAdminSetup` as the `monolog.test` factory | the routine that page runs |

**`Test/` is not the PHPUnit suite.** It is the ACP diagnostic behind *Tools > Checks and tests >
Test Monolog*, which writes one message at every level so an admin can see where they land. The
PHPUnit suite is `tests/`; `TESTING.md` says what it covers and what it cannot.

## How a channel is built

**One base logger per request, built from the options and never modified afterwards.** A channel
is `withName()` of it — a clone sharing the same handler and processor objects — cached by name, so
asking twice returns the same logger and every channel writes through one file handle and one
email buffer.

**The stack is assembled in one place, before the base `Logger` exists**: built-in handlers and
processors, then `config.php`'s, then `hampel_monolog_setup`. The event fires once, inside the
`logger` container closure, with the handler and processor arrays by reference —
`SetupEventTest` fails if it moves after construction. The id is prefixed because code event ids
are global and XenForo 2.4 core fires `monolog_setup`. There is no `xf-make` command for code
events: the definition is hand-written in `_output/code_events/`, and `_metadata.json`'s hash is
an md5 of the file.

**Never push a handler or processor onto the base logger or a channel.** 4.x did exactly that,
building its default logger by pushing handlers onto a shared instance, so whether a logger
carried them depended on what had already been resolved in the request, and records could be
written twice. Anything that needs to change the stack changes the list it is built from.

The stack, from `MonologApi::initialize()`:

- **file** — a `StreamHandler` at `file.path` if `config.php` sets one, otherwise
  `internalDataPath` plus the `monologLogFile` name; level from `FileMinimumLogLevel::get()`
  (default `WARNING`). Formatted by `file.formatter` if set, as JSON when `LogFormat::get()` is
  `json`, otherwise as a line with the date forced back to Monolog 1's `Y-m-d H:i:s`, because
  existing parsers expect it;
- **email** — a `LazyHandler` around a `DeduplicationHandler` around `XenForoMailHandler`, when
  `SendEmail::isEnabled()`. Level from `EmailMinimumLogLevel::get()` (default `ERROR`); the
  recipient falls back to the board's `contactEmailAddress`;
- **slack** — a `LazyHandler` around a `DeduplicationHandler` around `XenForoSlackHandler`, when
  `SlackWebhook::isEnabled()` (on, and an https webhook). It posts through XenForo's HTTP client
  with a 5-second timeout, not Monolog's `SlackWebhookHandler`, which uses raw cURL with no timeout
  and throws after five retries — a slow Slack would hang the page and an unreachable one break the
  caller. A failure goes to `\XF::logError()` **with the webhook replaced by `[webhook]`**: Guzzle
  puts the URL in its messages, and the URL is the credential. Nothing prints the webhook —
  `monolog:config` and the locked option show only that it is set;
- **per-channel levels** — when a section's `channels` in `config.php` lists any, its handler is
  built at the lowest of them and wrapped in a `ChannelLevelHandler`, which decides each record by
  its own channel. **Its `isHandling()` must pass the lowest level when the record has no
  channel**: Monolog 2's `Logger` asks with `['level' => $level]` alone, so the section level there
  drops a raised channel's records before `handle()` sees whose they are. Monolog 3 passes the
  whole record, so only the Monolog 2 suite catches it. Without `channels` nothing is wrapped;
- **processors** — `ContextProcessor` when the format is `json`; `RequestIdProcessor` when the
  format is `json` or *Add Request ID* is on; then Monolog's `WebProcessor` and `VisitorProcessor`,
  each behind its option. **A server-supplied request id is trusted only if it is shaped like an
  id** — a header value lands in the log, so a newline in one would forge a line.

**`ContextProcessor` runs for JSON output only, deliberately.** Its fields exist for a log store
holding several forums; a line log is one forum's file read by a person, where all three are
implied, and adding them would change every line existing readers parse. `extra.app` reports
`job` whenever `Job\Manager` is running one — read from its protected `runningJob` through a bound
closure, and only if something already built the manager. **Bump `ContextProcessor::SCHEMA` when
the meaning of an existing field changes**; adding a field does not need it.

**`$config['monolog']` in `config.php` wins over every option, one key per option.** The
layout is documented on `Config` and in the README: a `file` section (`path`, `format`, `level`,
`formatter`), an `email` section (`to`, `level`, `subject`, `dedup`), and `visitor`, `web` and
`site`, plus `handlers` and `processors`. **Read settings through the option classes' getters,
never through `\XF::options()` directly** — the getter is what puts `config.php` first, so a
direct read silently ignores the server owner. A value the getter cannot use is ignored, and the
option decides.

**An option `config.php` sets is locked on the options page, and the lock is a correctness
measure, not a nicety.** `OptionController::actionUpdate()` saves `false` for every option on the
page's `options_listed` list whose input sent nothing, so a merely disabled field would wipe the
stored value on every save. Every option therefore renders through
`AbstractConfigurableOption::renderOption()`: when locked it shows the value in force and *Set in
config.php*, with no input and **without `listedHtml`**, so the option is never listed; and its
`verifyOption()` keeps the stored value for a save that arrives some other way. `OptionsPageTest`
has a test for each layer and a save through the real controller that needs both removed to fail.
A new option must extend `AbstractConfigurableOption` and use the `callback` edit format, or it
reopens the trap.

**Switching a section off in `config.php` locks every option in it.** An option's `DEPENDS_ON`
names its section, `file`, `email` or `slack`; when `Config::enabled()` returns `false` for that
section the option renders as *Not used* and is locked the same way. Only `config.php` does this —
switching a section off on the options page leaves its options editable. A new option in one of
those sections must set `DEPENDS_ON`.

`handlers`, `processors` and `file.formatter` are **callables** — `config.php` is read before
`XF\App::setup()` registers this add-on's autoloader, so an object built there is a "class not
found" fatal, which is also why `$config['fsAdapters']` takes callables. `MonologApi::fromConfig()`
calls each one when the logger is built and skips, with `\XF::logError()`, any entry that is not
callable or builds the wrong type, so a `config.php` mistake cannot take down every page that
logs.

**The log file option must stay inside `internal_data`.** Any admin with option permission can
set it, and the file receives log lines that can carry user-supplied text — so a path into the
web root is a way to plant a script. `LogFile::isInsideInternalData()` refuses leading slashes,
drive letters, stream wrappers and `..` segments, both in `verifyOption()` on save and in
`getLogFile()` on read, since a value saved before 5.0 was never checked. Anywhere else is
`config.php`'s job, because only the server owner controls that file. Do not loosen this to make
an absolute path work from the options page.

**The email handler must not be built when a channel is.** Building it needs `$app->mailer()`,
which fires `mailer_transport_setup`; a mail add-on answering that event by asking for its own
channel re-entered channel construction and recursed until the stack ran out in 4.x.
`LazyHandler` checks the level without building, so the mailer is touched only by a record that
will be emailed. `LazyMailTest` pins it with `isCached('mailer')`.

**The request's records go out as one email, at shutdown.** `DeduplicationHandler` is a buffer: it
flushes when closed — `register_shutdown_function` in production, an explicit `close()` in tests —
and skips any record already sent within `monologEmailDeduplicationTimeout`, using a store in XF's
temp directory. A record logged *during* the send lands in a buffer that is then cleared, so it
reaches the file but not the email.

**A transport that logs every send cannot loop the email handler, for two reasons.** The buffer
takes its batch before sending and is cleared afterwards, so the record about the log email itself
is never emailed — at most one log email per request. And the guard below stops the loop even
unbuffered. `LazyMailTest` covers the Debug-level case, and fails if the buffer is removed.

**`XenForoMailHandler` also refuses to send while it is sending.** That static guard is what stops
a failing transport that logs its failure at `ERROR` from feeding back into itself when the handler
is used unbuffered; `XenForoMailHandlerTest` proves it without the buffer in the way.

**Every handler and processor here must run on Monolog 2 and Monolog 3.** XenForo 2.4 bundles
Monolog 3, and its class loader is consulted before any add-on's, so on 2.4 every `Monolog\` and
`Psr\Log\` class resolves to core's copy whatever this add-on bundles. Monolog 2 passes records as
arrays and Monolog 3 as `LogRecord` objects, so:

- **leave record parameters untyped** — an untyped parameter satisfies both `array $record` and
  `LogRecord $record`; either type alone is a fatal declaration error under the other version;
- **read and assign `extra` whole** (`$extra = $record['extra']; … $record['extra'] = $extra;`) —
  `LogRecord` allows setting `extra`, but most of its fields are read-only;
- **use nothing Monolog 3 removed** — `Logger::getLevels()` is gone, which is why the level select
  carries its own table.

**Run the suite against Monolog 3 before committing any change to a handler or processor:**

```bash
composer install -d tests/monolog3      # first time; its vendor/ is gitignored
MONOLOG3=1 vendor/bin/phpunit
```

`tests/TestCase.php` repoints XenForo's own class loader at `tests/monolog3/vendor` for that run,
which is the order 2.4 will load them in. `Monolog3SimulationTest` fails if the switch is set and
the classes still come from anywhere else, so a green run is not a silent fallback to v2.

## Composer and the version floors

**`vendor/` is gitignored and required at runtime.** Resolving anything from the `monolog`
sub-container, and the log-level option callbacks that call `Monolog\Logger`, fail without it, so
`Setup::checkRequirements()` refuses to install when it is missing. Composer autoloading reaches
XF through `addon.json`'s `composer_autoload`. After a clone, from the add-on root:

```bash
composer install
```

**The floors are XenForo 2.2.0 and PHP 7.4, declared identically in three places** —
`addon.json` `require`, `composer.json` `require.php`, and the README. They drift independently
because nothing checks one against another, so change all three together or none.

The PHP floor is derived, not chosen. XF 2.2 enforces 7.0, Monolog 2 requires 7.2, and 7.4 is the
lowest PHP a XenForo instance has been built and tested on for these add-ons — declaring 7.2 would
promise something never run. **Raising it is a support decision, not a tidy-up**: it strands users
on older PHP at the previous major.

**`config.platform.php` is `8.3`, not the 7.4 floor, and `psr/log` is capped at `^1.1` to
compensate.** The pin governs the whole solve, and `hampel/xenforo-test-framework` requires PHP
8.3, so no pin at the floor can install the dev tools. So the runtime tree is constrained
explicitly instead: `monolog/monolog` `^2` resolves to a release needing 7.2, and the `psr/log`
cap holds it at the 1.x XenForo itself ships — without it the solve picks psr/log 3, which never
loads (core's copy wins) but makes the lock misstate what runs. `composer.json` cannot carry a
comment, so this paragraph is the record; after any `composer update`, re-check that every
runtime package still accepts 7.4:

```bash
python3 -c "
import json; l=json.load(open('composer.lock'))
for p in l['packages']: print(f\"  {p['name']:25} {p['version']:8} {p.get('require',{}).get('php','-')}\")"
```

**Monolog 3 cannot be used while XenForo bundles psr/log 1.** XF appends add-on autoloaders after
its own, so core's `Psr\Log\LoggerInterface` always wins, and Monolog 3's typed methods cannot
implement v1's untyped ones — a fatal error on first use. Monolog 2 accepts psr/log 1, 2 or 3.

## Commands

Run from the XenForo install root:

```bash
php cmd.php xf-dev:import --addon=Hampel/Monolog       # after hand-editing anything in _output/
php cmd.php xf-addon:build-release Hampel/Monolog      # release zip into _releases/
```

Options, the option group, phrases, the admin navigation entry, the admin template, the class
extension and both listeners all live in `_output/` and are edited there, then imported.

Tests run from the add-on root, against the XenForo install around it, on
`hampel/xenforo-test-framework`:

```bash
composer install                                  # first time; vendor/ is gitignored
vendor/bin/phpunit                                # whole suite
vendor/bin/phpunit --testsuite Feature            # one suite
vendor/bin/phpunit --filter EmailTest             # one class
```

**`monolog:config` and `monolog:validate`** (`Cli/Command/`) follow the convention the house uses
for `app:config` and `app:validate`: config reads and prints, validate exercises the real thing,
four outcomes (`[ ok ]`, `[warn]`, `[fail]`, and a blank `[    ]` for a check that did not apply),
exit 1 only on a failure, no check may end the run, and `--unattended` skips only the sends — the
level sweep — while the checks that are facts about the configuration run above it.

**The sweep counts only its own records** — `monolog-validate` channel, exact message, in the line
or JSON layout — because another record can quote one. A mail transport that logs its
transmissions writes the emailed copy, run id and all, into the same file; on a production forum
that read as "9 of 8". A line that mentions the run is reported, and a wrong count prints them.

**Extend `monolog:validate` whenever logging gains a new dependency on the environment.** The
rendering is `Cli/RendersReport.php`, a copy of `hampel/console-report`'s layout, because that
package needs PHP 8.3 and XenForo ships its own `symfony/console`.

**Two traps, both hit writing them, both fatal for every command in `cmd.php`, not only these.**
XenForo loads every add-on's command classes to list them, so a class that cannot load stops the
whole CLI:

- `xf-make:cli-command` scaffolds `extends XF\Cli\Command\AbstractCommand`, which does not exist on
  XenForo 2.2. Extend Symfony's `Command` directly, and return `0` / `1` rather than
  `Command::SUCCESS`, which XenForo 2.2's console fork may lack.
- A private helper named `run()` collides with `Command::run()`, which is public. The tests load
  both command classes through `XF\Cli\Runner::isValidCommandClass()` to catch either.

**Check the exit code unpiped** — `vendor/bin/phpunit >/dev/null 2>&1; echo $?` — because a
pipeline reports its last command's status, and `| tail` reports 0 whatever happened.

## Release packaging

**`build.json` runs `composer install --no-dev` in `_build/upload/…` only**, which strips the dev
dependencies from what ships. It must not also run in the add-on directory itself — that would
delete PHPUnit from the working copy on every build. It then `rm`s `phpunit.xml`, `tests/`, the
PHPUnit cache and the dev-only markdown, and `mv`s every remaining root `*.md` to the zip root.

**That `mv` promotes, it does not exclude.** Any root markdown file not removed by an earlier
`rm -fv` step ships at the top of the release zip — which includes this file and
`CLAUDE.local.md`. Both belong in the `rm` step, and in `.gitattributes` `export-ignore` for
`git archive`. The version bump, tagging and zip checks are in the install's release conventions
rather than here.
