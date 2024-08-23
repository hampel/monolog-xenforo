CHANGELOG
=========

4.1.0 (2024-08-23)
------------------

* support both XF 2.2 and XF 2.3

4.0.0 (2021-09-23)
------------------

* implement Monolog v2 and reset default date format back to what v1 used

3.1.1 (2020-08-29)
------------------

* removed call to \Swift_Mailer::newInstance for compatibility with Swiftmailer 6
* check that vendor folder exists to prevent breaking forum if we somehow didn't run composer install

3.1.0 (2019-09-30)
------------------

* updates for XF 2.1

3.0.0 (2018-08-13)
------------------

* change addon_id to Hampel/Monolog and add Hampel namespace to all classes

2.1.1 (2018-06-05)
------------------

* bump version_id to 2004 to avoid conflicts with XF 1.x compatible releases
* move subcontainer out of XF namespace
* bugfix - was using the wrong subcontainer name

2.1.0 (2018-01-17)
------------------

* added helper class for logging
* added support for emailing log messages
* added configuration options
* simplified logger test

2.0.0 (2018-01-16)
------------------

* XF 2.0 version
* now requires PHP 7.0
* now uses Monolog v1.23 or higher

1.1.0 (2015-03-24)
------------------

* made Monolog logging helper more robust
* cleaned up EOF lines

1.0.1 (2015-03-11)
------------------

* remove vendor directory from .gitignore - we want to check everything in

1.0.0 (2015-03-11)
------------------

* fix option validation monlogDefaultChannel - was using the wrong class name

0.3.0 (2015-03-09)
------------------

* back to default autoloading mechanism, but using an init_dependencies listener to include the vendor/autoload.php 
  file
* added some error checking

0.2.0 (2015-03-06)
------------------

* simplified things - handlers now managed at an administrative level, not required to be created by other addons

0.1.1 (2015-03-05)
------------------

* fixed typo in code event listener, moved to psr-0 structure for autoloading

0.1.0 (2015-03-05)
------------------

* added configuration options
