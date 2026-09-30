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
app so any add-on can log through Monolog v2 without bundling it, and adds nothing to the public
side of a forum. `README.md` is the consumer-facing usage guide; read it for the three calling
styles (`Helper\Log` statics, `newChannel()`, and building a custom handler stack).

The moving parts, and where each is wired:

| piece | registered by | does |
|---|---|---|
| `SubContainer/MonologApi.php` | `Listener::appSetup` (`app_setup` listener) as `$container['monolog']` | defines every handler, processor and logger as lazy container entries |
| `Helper/Log.php` | nothing — static calls | proxies PSR-3 methods to `logger.default` |
| `Option/*.php` | option `edit_format`/callbacks in `_output/options/` | each option has a static getter that supplies the default when unset |
| `XF/Admin/Controller/Tools.php` | class extension of `XF\Admin\Controller\Tools` | the ACP *Test Monolog* page (`actionTestMonolog`) |
| `Test/*.php` | `Listener::appAdminSetup` as the `monolog.test` factory | the routine the ACP test page runs |

**`Test/` is not the PHPUnit suite.** It is the ACP diagnostic behind *Tools > Checks and tests >
Test Monolog*, which writes one message at every level so an admin can see where they land. The
PHPUnit suite is `tests/`; `TESTING.md` says what it covers and what it cannot.

## How the default logger is assembled

**`logger.default` is built once per request from the options, then cached.** Its closure in
`MonologApi::initialize()` pushes, in order:

- the stream handler, if `monologLogFile` is enabled with a non-empty filename — the path is
  relative to `internalDataPath`, the date format is forced back to Monolog v1's `Y-m-d H:i:s`,
  and the level comes from `monologFileMinimumLogLevel` (default `WARNING`);
- a mailer handler wrapped in a `DeduplicationHandler`, if `monologSendEmail` is enabled;
- the visitor processor (`extra.visitor`) and Monolog's `WebProcessor`, each behind its own option.

**The mail handler forks on XF version, and both branches must keep working.** XF 2.3+ gets
`SymfonyMailerHandler` over `$app->mailer()->getDefaultTransport()`; XF 2.2 gets
`SwiftMailerHandler`. The same `\XF::$versionId >= 2030000` guard is in `Setup::postUpgrade()`.
Each branch keeps its own dedup store in the XF temp directory. Recipient and sender fall back to
the board's `contactEmailAddress` and `defaultEmailAddress`.

**`logger.default` mutates the shared `logger` entry rather than cloning it.** It takes
`$c['logger']` — the single cached `Monolog\Logger('xenforo')` — and pushes handlers onto it. So
`MonologApi::logger($name)` returns a clean, handler-free logger only if `default()` has not yet
been resolved in this request; afterwards, `withName()` clones a logger that already carries the
default handlers, and a caller following the README's custom-stack example gets the stream handler
twice. `LoggerTest` avoids it only because it calls `logger()` first.

**The visitor processor treats the record as an array.** That is Monolog v2's contract; v3 passes
an immutable `LogRecord`, so it is the first thing to rewrite when acting on the Monolog v3 TODO
in `MonologApi`.

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
