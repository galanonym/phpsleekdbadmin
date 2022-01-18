<?php
// This is sample configuration file

// You can configure phpsleekdbadmin in one of 2 ways:
// 1. Rename phpsleekdbadmin.config.sample.php to phpsleekdbadmin.config.php and change parameters in there.
//    You can set only your custom settings in phpsleekdbadmin.config.php. All other settings will be set to defaults.
// 2. Change parameters directly in main phpsleekdbadmin.php file

/* ---- Config ---- */

// Password to gain access
$password = 'admin';

// Directory relative to this file to search for databases
$directory = '../database';

// Set default number of rows to show
$limit_default = 30;

// Max depth of document
$max_depth = 10;

// Reduce string characters by a number bigger than 10
$max_string_length = 200;
