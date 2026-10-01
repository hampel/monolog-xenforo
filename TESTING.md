# TESTING

What the automated suite covers, what it cannot, and what to check by hand.

## Running it

From the add-on root, inside a XenForo install:

```bash
composer install
vendor/bin/phpunit
```

Check the exit code with the command unpiped: `vendor/bin/phpunit >/dev/null 2>&1; echo $?`.

**Then run it again against Monolog 3**, which XenForo 2.4 bundles and loads ahead of this
add-on's own copy:

```bash
composer install -d tests/monolog3
MONOLOG3=1 vendor/bin/phpunit
```

Both runs must pass. `tests/Feature/Monolog3SimulationTest.php` proves the second one really
loaded Monolog 3 and psr/log 3.

## What the suite covers

- **`tests/Feature/ChannelTest.php`** — `channel()`, the 5.0 API: a PSR-3 logger, cached by name,
  and every record written exactly once however many channels came before it.
- **`tests/Feature/NewChannelTest.php`** — the 4.x contract consuming add-ons still use: the
  `monolog` container key and `newChannel()`, the file level and file options, and the visitor
  processor.
- **`tests/Feature/ConfigTest.php`** — `$config['monolog']`: an absolute, relative or disabled
  file overriding the option, and the JSON format, one record per line with stack traces.
- **`tests/Feature/ContextProcessorTest.php`** — `extra.schema`, `site` and `app` on JSON records
  only, the configured site, each app type, and a running job.
- **`tests/Feature/SetupEventTest.php`** — `hampel_monolog_setup`: fired once, before the shared
  logger is built, and able to add or remove handlers and processors.
- **`tests/Feature/ToolsControllerTest.php`** — the ACP *Test Monolog* page: its permission, its
  rendering, and one message written at every level.
- **`tests/Feature/HelperLogTest.php`** — the deprecated `Helper\Log` facade, every level.
- **`tests/Feature/EmailTest.php`** — the email stack: level, recipient and its fallback, subject,
  one email per request, and deduplication across requests.
- **`tests/Feature/LazyMailTest.php`** — that neither creating a channel nor writing below the email
  level builds XenForo's mailer.
- **`tests/Unit/XenForoMailHandlerTest.php`** — the mail handler alone, including its guard against
  a transport that logs while sending.
- **`tests/Unit/OptionTest.php`** — the option getters' fallbacks, the `{board}` token, the
  string `"0"` a fresh install stores for a disabled option, and the log file option refusing any
  path outside `internal_data`, on save and when an older stored value is read.

**Each test points `internalDataPath` at a directory of its own.** The log file lives there, and so
does XenForo's temp directory, which holds the email deduplication store. A shared store would
make an email test fail because an earlier test or run had already sent the same message.

## What it cannot cover

- **Email on XenForo 2.2.** `XenForoMailHandler` goes through XF's `Mail` on both versions, but
  underneath it is SwiftMailer on 2.2 and Symfony Mailer on 2.3, and `fakesMail()` swaps in a
  Symfony Mailer transport — so the suite exercises 2.3 alone. Check 2.2 by hand: enable email
  on a 2.2 forum, write an `ERROR` record, and confirm one email arrives.
- **The declared PHP floor.** Nothing here runs PHP below 8.3; lint the release zip on a PHP 7.4
  instance.

## Fragile points

- **Email is sent when the handlers close**, at the end of the request, not when the record is
  written. A test, or any code, that never closes the logger sends nothing.
- **Nothing may build the mailer while a channel is being created.** A mail add-on that asks for a
  channel from `mailer_transport_setup` recursed in 4.x. `LazyMailTest` fails if the email handler
  is ever built eagerly again.
- **A fresh install stores on/off options as the strings `"1"` and `"0"`**, until an admin saves the
  options page. An `isEnabled()` that tests `!== false` treats `"0"` as on; 4.x shipped exactly
  that, and emailed errors from every fresh install.
