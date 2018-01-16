CHANGELOG
=========

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
