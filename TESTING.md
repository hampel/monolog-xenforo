# TESTING

What the automated suite covers, what it cannot, and what to check by hand.

## Running it

From the add-on root, inside a XenForo install:

```bash
composer install
vendor/bin/phpunit
```

Check the exit code with the command unpiped: `vendor/bin/phpunit >/dev/null 2>&1; echo $?`.

## What the suite covers

- **`tests/Feature/NewChannelTest.php`** — the `monolog` container key and `newChannel()`, which
  is what consuming add-ons call: records reach the log file under the channel's name, the file
  level and file options are respected, and the visitor processor follows its option.
- **`tests/Feature/HelperLogTest.php`** — the `Helper\Log` static facade, every level.
- **`tests/Feature/EmailTest.php`** — the email handler: level, recipient and its fallback,
  subject, one email per request, and deduplication across requests.
- **`tests/Unit/OptionTest.php`** — the option getters' fallbacks and the `{board}` token.

**Each test points `internalDataPath` at a directory of its own.** The log file lives there, and so
does XenForo's temp directory, which holds the email deduplication store. A shared store would
make an email test fail because an earlier test or run had already sent the same message.

## What it cannot cover

- **The XenForo 2.2 email path.** `fakesMail()` swaps in a Symfony Mailer transport, which only
  XenForo 2.3 uses, so the suite exercises 2.3 alone. Check 2.2 by hand: enable email on a 2.2
  forum, write an `ERROR` record, and confirm one email arrives.
- **The ACP test page** — *Tools > Checks and tests > Test Monolog*. Run it and read the log.
- **The declared PHP floor.** Nothing here runs PHP below 8.3; lint the release zip on a PHP 7.4
  instance.

## Fragile points

- **Email is sent when the handlers close**, at the end of the request, not when the record is
  written. A test, or any code, that never closes the logger sends nothing.
- **Building a channel builds the email handler, which calls `XF\App::mailer()`.** An add-on whose
  mail transport logs to a channel while being constructed recurses; SparkPostMail works around
  this with a lazy logger.
