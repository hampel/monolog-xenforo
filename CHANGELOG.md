# CHANGELOG

## 5.0.2 (2026-10-08)

- new: `monolog:validate --strict` exits 2 when there are warnings and no failures — for a monitor;
  1 still means a failure, and without the flag nothing changes
- `monolog:validate` warns when the log file, email and Slack are all off, where it used to report
  the empty logger as fine
- `monolog:validate` checks that email and Slack can record what they have sent, and warns if not
  — without that record every repeat is sent again
- every record `monolog:validate` and the *Test Monolog* page write carries `probe: true` in its
  context, so an alert over the log can leave them out

## 5.0.1 (2026-10-04)

- fix: email and Slack treated two different errors with the same message as a repeat, and
  suppressed the second — a repeat now also has to match the channel and, where the record has
  one, the exception or a `fingerprint` in its context
- every message this add-on writes to the server error log now begins `Monolog: `

## 5.0.0 (2026-10-02)

- new: `channel()` returns a PSR-3 `Psr\Log\LoggerInterface` for an add-on's own log channel —
  type against that interface, not a Monolog class
- deprecated: `newChannel()`, now an alias for `channel()`, and the static
  `Hampel\Monolog\Helper\Log`
- removed: `logger()`, `default()`, `stream()` and `visitor()` — add or replace handlers and
  processors with the new `hampel_monolog_setup` code event instead
- new: post log records to Slack through an incoming webhook — one message per request, sent with
  a short timeout, and a failed post never reaches the add-on that was logging
- new: every option can be set in `$config['monolog']` in `config.php` — `file`, `email` and
  `slack` sections, `request_id`, `visitor`, `web` and `site` — and the options page shows it as
  set there; `handlers`, `processors` and a file `formatter` add to the stack with no add-on
- new: a minimum level per channel in `config.php`, so one add-on can be traced at Debug without
  turning up every other
- new: *Log File Format*, *Site Name* and *Add Request ID* options; every record carries a request
  id, the web server's where it provides one
- new: `monolog:config` and `monolog:validate` CLI commands — what is configured, and whether it
  works, with `--unattended` for a deploy gate
- new: JSON records carry `extra.site`, `extra.app` and `extra.schema`
- new: README guidance on choosing log levels, and on supporting 4.x and 5.x from another add-on
- security: the log file option only accepts a path inside `internal_data` — a path outside it is
  ignored, and belongs in `config.php`
- fix: email alerts were switched on by a fresh install until the options were saved — such
  installs stop emailing until email is enabled
- fix: creating a log channel no longer builds XenForo's mailer, which could recurse with a mail
  add-on that logs
- email is sent through XenForo's own mail system, on 2.2 and 2.3 alike
- runs on Monolog 3 as well as 2, so it should work on XenForo 2.4, which bundles Monolog 3 — not
  yet tested there
- requires XenForo 2.2 and PHP 7.4

## 4.1.2 (2025-12-12)

- run enqueuePostUpgradeCleanUp during upgrades if we're running XF2.3+

## 4.1.1 (2024-10-16)

- latest composer dependencies

## 4.1.0 (2024-08-23)

- support both XF 2.2 and XF 2.3

## 4.0.0 (2021-09-23)

- implement Monolog v2 and reset default date format back to what v1 used

## 3.1.1 (2020-08-29)

- removed call to \Swift_Mailer::newInstance for compatibility with Swiftmailer 6
- check that vendor folder exists to prevent breaking forum if we somehow didn't run composer install

## 3.1.0 (2019-09-30)

- updates for XF 2.1

## 3.0.0 (2018-08-13)

- change addon_id to Hampel/Monolog and add Hampel namespace to all classes

## 2.1.1 (2018-06-05)

- bump version_id to 2004 to avoid conflicts with XF 1.x compatible releases
- move subcontainer out of XF namespace
- bugfix - was using the wrong subcontainer name

## 2.1.0 (2018-01-17)

- added helper class for logging
- added support for emailing log messages
- added configuration options
- simplified logger test

## 2.0.0 (2018-01-16)

- XF 2.0 version
- now requires PHP 7.0
- now uses Monolog v1.23 or higher

## 1.1.0 (2015-03-24)

- made Monolog logging helper more robust
- cleaned up EOF lines

## 1.0.1 (2015-03-11)

- remove vendor directory from .gitignore - we want to check everything in

## 1.0.0 (2015-03-11)

- fix option validation monlogDefaultChannel - was using the wrong class name

## 0.3.0 (2015-03-09)

- back to default autoloading mechanism, but using an init_dependencies listener to include the vendor/autoload.php 
  file
- added some error checking

## 0.2.0 (2015-03-06)

- simplified things - handlers now managed at an administrative level, not required to be created by other addons

## 0.1.1 (2015-03-05)

- fixed typo in code event listener, moved to psr-0 structure for autoloading

## 0.1.0 (2015-03-05)

- added configuration options
