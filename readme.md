# phpSleekDBAdmin

## What is phpSleekDBAdmin?

phpSleekDBAdmin is a web-based SleekDB database admin tool written in PHP. Following in the spirit of the flat-file system used by SleekDB, phpSleekDBAdmin consists of a single source file, phpsleekdbadmin.php.  The interface and user experience is comparable to that of phpLiteAdmin and phpMyAdmin.

## Requirements

-   a server with PHP >= 7.0.0 installed, supporting sessions
-   SleekDB database

## Installation

[Download newest release here](https://github.com/galanonym/phpsleekdbadmin/releases/download/v0.2.1/phpsleekdbadmin_v0.2.1.zip)

Extract into your webroot into /phpsleekdbadmin/ directory

## Configuration

1.  Rename `phpsleekdbadmin.config.sample.php` into `phpsleekdbadmin.config.php`
2.  Open `phpsleekdbadmin.config.php` (or `phpsleekdbadmin.php`) in
    a text editor.
3.  Specify the directory as the value of the `$directory` variable.
4.  Modify the `$password` variable to be the password used for gaining access
    to the phpSleekDBAdmin tool.
5.  Open a web browser and navigate to the `phpsleekdbadmin/phpsleekdbadmin.php` file. You will be prompted to enter a password. Use the same password you set in step 4.

## Screenshot

![alt text](https://github.com/galanonym/phpsleekdbadmin/blob/main/screenshot.png?raw=true)

## Changelog

0.2.1 (2022-01-21)
- feature - Install through .zip file, not composer 

0.2.0 (2022-01-20)
- feature - eval() is no longer used, input for every parameter and simple array parser is used instead
- feature - Added support for updateOrInsert() and updateOrInsertMany()
- issue #1 - Use microtime() directly

0.1.0 (2022-01-18)
- Initial release

