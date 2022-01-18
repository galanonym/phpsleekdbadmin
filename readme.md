# phpSleekDBAdmin

## What is phpSleekDBAdmin?

phpSleekDBAdmin is a web-based SleekDB database admin tool written in PHP. Following in the spirit of the flat-file system used by SleekDB, phpSleekDBAdmin consists of a single source file, phpliteadmin.php, that is dropped into a directory on a server and then visited in a browser.  The interface and user experience is comparable to that of phpLiteAdmin and phpMyAdmin.

## Requirements

-   a server with PHP >= 7.0.0 installed
-   SleekDB database

## Installation

Go to your webroot
```bash
cd /www/webroot
```

Clone the repo
```bash
git clone git@github.com:galanonym/phpSleekDBAdmin.git
```

Install dependencies
```bash
composer install
```
