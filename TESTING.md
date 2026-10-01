# TESTING

What this add-on touches, what breaks quietly, what the automated suite settles, and what still
needs a person.

## Surfaces

**Code event listeners**

- `app_setup` — registers the `monolog` sub-container, which consuming add-ons check for with
  `offsetExists('monolog')`. Nothing else in the add-on runs until something asks it for a
  channel.
- `app_admin_setup` — registers the `monolog.test` factory the ACP test page resolves through.

**Code events defined**

- `hampel_monolog_setup` — fired once per request, before the shared logger is built, with its
  handlers and processors by reference. Other add-ons listen to it.

**Class extensions**

- `XF\Admin\Controller\Tools` — adds `actionTestMonolog()`, the *Test Monolog* page, gated on the
  `option` admin permission. This is a commonly extended controller, so on a real forum this
  add-on is usually one link in a chain of extensions.

**Other artifacts**

- Admin navigation: *Test Monolog* under *Checks and tests*, and its template
  `monolog_tools_test_monolog`.
- Options: eight, in the `monolog` group. The log file option validates on save.
- No template modifications, crons, routes, permissions or schema changes.

## Fragile points

- **Email is sent when the handlers close**, at the end of the request, not when the record is
  written. A test, or any code, that never closes the logger sends nothing.
- **Nothing may build the mailer while a channel is being created.** A mail add-on that asks for a
  channel from `mailer_transport_setup` recursed in 4.x. `LazyMailTest` fails if the email handler
  is ever built eagerly again.
- **A fresh install stores on/off options as the strings `"1"` and `"0"`**, until an admin saves
  the options page. An `isEnabled()` that tests `!== false` treats `"0"` as on; 4.x shipped exactly
  that, and emailed errors from every fresh install.
- **Every `Monolog\` and `Psr\Log\` class may come from XenForo, not from this add-on.** XenForo
  2.4 bundles Monolog 3 and its class loader is consulted first. A handler or processor with a
  typed record parameter is a fatal error on one version or the other.
- **A `build.json` `exec` step cannot fail the build.** If its `composer install --no-dev` fails,
  XenForo still produces a release zip, with no Monolog in it.

## Automated

From the add-on root, inside a XenForo install:

```bash
composer install
vendor/bin/phpunit
```

**Then run it again against Monolog 3**, the version XenForo 2.4 loads ahead of this add-on's own:

```bash
composer install -d tests/monolog3
MONOLOG3=1 vendor/bin/phpunit
```

Both runs must pass. Check the exit code with the command unpiped —
`vendor/bin/phpunit >/dev/null 2>&1; echo $?` — because a pipeline reports its last command's
status. `tests/Feature/Monolog3SimulationTest.php` proves the second run really loaded Monolog 3
and psr/log 3.

**Each test points `internalDataPath` at a directory of its own.** The log file lives there, and so
does XenForo's temp directory, which holds the email deduplication store. A shared store would
make an email test fail because an earlier test or run had already sent the same message.

What the suite covers:

- **`tests/Feature/ChannelTest.php`** — `channel()`: a PSR-3 logger, cached by name, and every
  record written exactly once however many channels came before it.
- **`tests/Feature/NewChannelTest.php`** — the 4.x contract consuming add-ons still use: the
  `monolog` container key and `newChannel()`, the file level and file options, and the visitor
  processor.
- **`tests/Feature/ConfigTest.php`** — `$config['monolog']`: an absolute, relative or disabled
  file overriding the option, and the JSON format, one record per line with stack traces.
- **`tests/Feature/ConfigStackTest.php`** — `handlers`, `processors` and `formatter` from
  `config.php`: added to the stack, built only with the logger, seen by the setup event, and a bad
  entry skipped and reported rather than thrown.
- **`tests/Feature/ContextProcessorTest.php`** — `extra.schema`, `site` and `app` on JSON records
  only, the configured site, each app type, and a running job.
- **`tests/Feature/SetupEventTest.php`** — `hampel_monolog_setup`: fired once, before the shared
  logger is built, and able to add or remove handlers and processors.
- **`tests/Feature/ToolsControllerTest.php`** — the ACP *Test Monolog* page: its permission, its
  rendering, and one message written at every level.
- **`tests/Feature/HelperLogTest.php`** — the deprecated `Helper\Log` facade, every level.
- **`tests/Feature/EmailTest.php`** — the email stack: level, recipient and its fallback, subject,
  one email per request, and deduplication across requests.
- **`tests/Feature/LazyMailTest.php`** — that neither creating a channel nor writing below the
  email level builds XenForo's mailer.
- **`tests/Feature/UntestedXenForoTest.php`** — that channels still log on XenForo 2.4 or later,
  and the install checks warn there.
- **`tests/Unit/XenForoMailHandlerTest.php`** — the mail handler alone, including its guard against
  a transport that logs while sending.
- **`tests/Unit/OptionTest.php`** — the option getters' fallbacks, the `{board}` token, the string
  `"0"` a fresh install stores for a disabled option, the level table against Monolog's own, and
  the log file option refusing any path outside `internal_data`, on save and when an older stored
  value is read.

## Needs a human

- **Unzip the release and confirm `vendor/` holds `monolog/monolog` and `psr/log` and nothing
  else** — no PHPUnit, no Mockery. See the fragile point on `exec` steps; nothing else catches a
  zip built without its dependencies.
- **Upgrade from the last published version on a fresh install**, not a development one, then run
  the job queue (`php cmd.php xf:run-jobs`) and check *Logs > Server error log*. An upgrade that
  reports success proves only that the files were extracted; the post-upgrade clean-up job runs
  afterwards.
- **Email on XenForo 2.2.** `XenForoMailHandler` goes through XenForo's `Mail` on both versions,
  but underneath it is SwiftMailer on 2.2 and Symfony Mailer on 2.3, and the suite's fake mail
  transport is Symfony Mailer's — so only 2.3 is exercised. Enable email on a 2.2 forum, run the
  *Test Monolog* page, and confirm one email arrives.
- **The PHP 7.4 floor.** The suite runs on PHP 8.3 only. Lint the release zip on a PHP 7.4 install.
- **After any `composer update`, confirm every runtime package still accepts PHP 7.4.** The
  platform pin is 8.3 so the test framework installs, which means Composer no longer stops a
  runtime package rising above the floor:

  ```bash
  python3 -c "
  import json; l=json.load(open('composer.lock'))
  for p in l['packages']: print(f\"  {p['name']:25} {p['version']:8} {p.get('require',{}).get('php','-')}\")"
  ```
