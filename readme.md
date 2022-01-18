# phpSleekDBAdmin

## What is phpSleekDBAdmin?

phpSleekDBAdmin is a web-based SleekDB database admin tool written in PHP. Following in the spirit of the flat-file system used by SleekDB, phpSleekDBAdmin consists of a single source file, phpsleekdbadmin.php.  The interface and user experience is comparable to that of phpLiteAdmin and phpMyAdmin.

## Requirements

-   a server with PHP >= 7.0.0 installed
-   SleekDB database

## Installation

Go to your webroot
```bash
cd /www/webroot
```

Clone the repo and install dependencies with composer
```bash
git clone git@github.com:galanonym/phpsleekdbadmin.git && cd phpsleekdbadmin && composer install
```

## Configuration

1.  Rename `phpsleekdbadmin.config.sample.php` into `phpsleekdbadmin.config.php`
2.  Open `phpsleekdbadmin.config.php` (or `phpsleekdbadmin.php`) in
    a text editor.
3.  Specify the directory as the value of the `$directory` variable. 
4.  Modify the `$password` variable to be the password used for gaining access
    to the phpSleekDBAdmin tool.
5.  Open a web browser and navigate to the `phpsleekdbadmin/phpsleekdbadmin.php` file. You will be prompted to enter a password. Use the same password you set in step 3.

## Screenshot

![alt text](https://github.com/galanonym/phpsleekdbadmin/blob/main/screenshot.png?raw=true)
