<?php
//	Project: phpSleekDBAdmin (https://kalis.no)
//	Version: 0.1.0
//	Summary: PHP-based admin tool to manage SQLite2 and SQLite3 databases on the web
//	Last updated: 2022-01-18
//	Developers:
//	   Matteus Kalis (post [-at-] kalis [-dot-] no)
//
//	Copyright (C) 2022, phpSleekDBAdmin
//
//	This program is free software: you can redistribute it and/or modify
//	it under the terms of the GNU General Public License as published by
//	the Free Software Foundation, either version 3 of the License, or
//	(at your option) any later version.
//
//	This program is distributed in the hope that it will be useful,
//	but WITHOUT ANY WARRANTY; without even the implied warranty of
//	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//	GNU General Public License for more details.
//
//	You should have received a copy of the GNU General Public License
//	along with this program.  If not, see <https://www.gnu.org/licenses/>.
//
//	////////////////////////////////////////////////////////////////////////

// You can configure phpsleekdbadmin in one of 2 ways:
// 1. Rename phpsleekdbadmin.config.sample.php to phpsleekdbadmin.config.php and change parameters in there.
//    You can set only your custom settings in phpsleekdbadmin.config.php. All other settings will be set to defaults.
// 2. Change parameters directly in main phpsleekdbadmin.php file

/* ---- Config ---- */

// Password to gain access
$password = 'admin';

// Directory relative to this file to search for databases (if false, manually list databases in the $databases variable)
$directory = '../database';

// Set default number of rows to show
$limit_default = 30;

// Max depth of document
$max_depth = 10;

// Reduce string characters by a number bigger than 10
$max_string_length = 200;

//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
//There is no reason for the average user to edit anything below this comment
//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\VarDumper;

// Load optional configuration file
$config_filename = './phpsleekdbadmin.config.php';
if (is_readable($config_filename)) {
  include_once $config_filename;
}

// Ensure directory seperator
if ($directory[strlen($directory)-1] != '/') {
  $directory .= '/';
}

start();
function start() {
  authenticate();

  setup_vardumper();

  $stores = scan_stores();

  $html = '';
  $html .= render_view_browse($html);
  $html .= render_view_query($html);
  $html .= render_view_drop($html);
  render_html($stores, $html);
}

function authenticate() {
  global $password;

  session_start();

  if (isset($_GET['logout'])) {
    unset($_SESSION['logged_in_phpsleekadmin']);
  }

  if ($password) {
    if (isset($_POST['password'])) {
      if ($_POST['password'] === $password) {
        $_SESSION['logged_in_phpsleekadmin'] = true;
      }
    }
    if (!isset($_SESSION['logged_in_phpsleekadmin'])) {
      render_view_login();
      exit();
    }
  }
}

function setup_vardumper() {
  VarDumper::setHandler(function ($var) {
    global $max_depth;
    global $max_string_length;

    $cloner = new VarCloner();
    $dumper = 'cli' === PHP_SAPI ? new CliDumper() : new HtmlDumper();

    $dumper->setStyles([
      'default' => 'background-color:#1d1f21; color:#c5c8c6; line-height:1.2em; font:12px Menlo, Monaco, Consolas, monospace; word-wrap: break-word; white-space: pre-wrap; position:relative; z-index:99999; word-break: break-all',
      'num' => 'font-weight:bold; color:#c66',
      'const' => 'font-weight:bold',
      'str' => 'color:#b5bd68',
      'note' => 'color:#f0c674',
      'ref' => 'color:#A0A0A0',
      'public' => 'color:#FFFFFF',
      'protected' => 'color:#FFFFFF',
      'private' => 'color:#FFFFFF',
      'meta' => 'color:#B729D9',
      'key' => 'color:#81a2be',
      'index' => 'color:#f0c674',
      'ellipsis' => 'color:#FF8400',
      'ns' => 'user-select:none;',
      ]
    );

    $dumper->dump($cloner->cloneVar($var), null, [
        'maxDepth' => $max_depth,
        'maxStringLength' => $max_string_length,
    ]);
  });
}


function scan_stores() {
  global $directory;

  if (!is_dir($directory)) {
    die('Not valid database directory.');
  }

  $directories = scandir($directory);

  $stores = [];
  foreach($directories as $store) {
    if (is_dir($directory . $store) AND $store !== '.' AND $store !== '..') {
      array_push($stores, $store);
    }
  }

  return $stores;
}

function render_view_browse() {
  global $directory;
  global $limit_default;

  if (!isset($_GET['store'])) {
    return '';
  }

  $store = $_GET['store'];

  if (!isset($_GET['action'])) {
    return '';
  }

  $action = $_GET['action'];

  if ($action !== 'view_browse') {
    return '';
  }

  $limit = $_GET['limit'] ?? $limit_default;
  $offset = $_GET['offset'] ?? 0;
  $order = $_GET['order'] ?? 'ASC';
  $order_by = $_GET['order_by'] ?? '_id';

  $db_store = new \SleekDB\Store($store, $directory, ['timeout' => false]);

  $queryTimer = new MicroTimer();
  $data = $db_store->findAll([$order_by => $order], $limit, $offset);
  $count = $db_store->count();
  $queryTimer->stop();

  ob_start();
    ?>
      <span><?php echo $directory; ?></span> → <span><?php echo $store; ?></span>
      <div class="seperator"></div>
      <div class="seperator"></div>
      <b>Browse</b>
      <span> | </span>
      <a href="?store=<?php echo urlencode($store); ?>&action=view_query"><b>Query</b></a>
      <span> | </span>
      <a href="?store=<?php echo urlencode($store); ?>&action=view_drop"><b>Drop</b></a>
      <div class="seperator"></div>
      <div class="seperator"></div>

      <form method="GET">
        <input type="hidden" name="store" value="<?php echo $store; ?>">
        <input type="hidden" name="action" value="<?php echo $action; ?>">

        <button type="submit">Show:</button>
        <input class="narrow" type="text" name="limit" value="<?php echo $limit; ?>">
        <span> document(s) starting from record #</span>
        <input class="narrow" type="text" name="offset" value="<?php echo $offset; ?>">
        <span> sorted by</span>
        <input class="narrow" type="text" name="order_by" value="<?php echo $order_by; ?>">
        <select name="order">
        <option value="ASC" <?php if ($order === 'ASC') { ?>selected<?php } ?>>ASC</option>
          <option value="DESC" <?php if ($order === 'DESC') { ?>selected<?php } ?>>DESC</option>
        </select>
      </form>

      <div class="seperator"></div>
      <div class="seperator"></div>
      <?php if ($count === 0 OR $offset > $count) { ?>
        <p><b>There are no documents in the store for the range you selected, Total: <?php echo $count; ?></b></p>
      <?php } else { ?>
        <p><b>Showing documents <?php echo $offset; ?> - <?php if ($offset + $limit <= $count) { echo $offset + $limit; } else { echo $count; } ?>, Total: <?php echo $count; ?> (Query took <?php echo $queryTimer; ?> sec)</b></p>
      <?php } ?>
      <div class="seperator"></div>
      <code style="font-size: 11px;">$store-&gt;findAll(["<?php echo $order_by; ?>" =&gt; "<?php echo $order; ?>"], <?php echo $limit; ?>, <?php echo $offset; ?>)</code>
      <div class="seperator"></div>

      <style>
        pre.sf-dump {
          margin-bottom: -5px;
        }
      </style>
      <pre style="padding-left: 10px; background-color: #1d1f21; border-radius: 3px;"><!--
        --><?php dump($data); ?><!--
        --><script>
          // Expand all by default
          var compacted = document.querySelectorAll('.sf-dump-compact');
          for (var i = 0; i < compacted.length; i++) {
            compacted[i].className = 'sf-dump-expanded';
          }
        </script><!--
      --></pre>
    <?php
  return ob_get_clean();
}

function render_view_query() {
  global $directory;
  global $limit_default;

  if (!isset($_GET['store'])) {
    return '';
  }

  $store = $_GET['store'];

  if (!isset($_GET['action'])) {
    return '';
  }

  $action = $_GET['action'];

  if ($action !== 'view_query') {
    return '';
  }

  $query = $_GET['query'] ?? '';

  $query = str_replace(';', '', $query);

  $count = 0;
  $data = [];

  $queryTimer = new MicroTimer();

  if ($query) {
    $db_store = new \SleekDB\Store($store, $directory, ['timeout' => false]);

    try {
      $data = @eval('return $db_store->' . $query . ';');
    } catch (Throwable $e) {
      $data = $e->getMessage();
    }

    if (isset($data[0]) AND is_array($data[0])) {
      $count = count($data);
    } else {
      $count = 1;
    }
  }

  $queryTimer->stop();

  ob_start();
    ?>
      <span><?php echo $directory; ?></span> → <span><?php echo $store; ?></span>
      <div class="seperator"></div>
      <div class="seperator"></div>
      <a href="?store=<?php echo urlencode($store); ?>&action=view_browse&limit=<?php echo $limit_default; ?>&offset=0&order=ASC&order_by=_id"><b>Browse</b></a>
      <span> | </span>
      <b>Query</b>
      <span> | </span>
      <a href="?store=<?php echo urlencode($store); ?>&action=view_drop"><b>Drop</b></a>
      <div class="seperator"></div>
      <div class="seperator"></div>
      <p><b>Run query on store &apos;<?php echo $store; ?>&apos;</b><p>

      <form method="GET" action="?store=<?php echo urlencode($store); ?>&action=<?php echo urlencode($action); ?>">
        <input type="hidden" name="store" value="<?php echo $store; ?>">
        <input type="hidden" name="action" value="<?php echo $action; ?>">
        <div class="seperator"></div>

        <div data-help class="help"></div>

        <div class="display-flex">
          <div style="flex: initial;">
            <code><span style="vertical-align: -3px; line-height: 27px; font-size: 14px; padding-right: 5px;">$<?php echo $store; ?>-&gt;</span></code>
          </div>
          <div style="flex: 1;">
            <input data-input style="width: 100%;" type="text" name="query" value="<?php if ($query) { echo htmlspecialchars($query); } ?>">
          </div>
          <div style="flex: initial;">
            <select data-select>
              <option value="">... functions</option>

              <option
                value="findAll([], <?php echo $limit_default; ?>)"
                data-help-text="function findAll(array $orderBy = null, int $limit = null, int $offset = null): array"
                data-help-example='findAll(["name" => "asc"], 30, 0)'
                <?php if (str_contains($query, 'findAll(')) { ?>selected<?php } ?>
              >findAll()</option>

              <option
                value="findById()"
                data-help-text="function findById(int|string $id): array|null"
                data-help-example='findById(1);'
                <?php if (str_contains($query, 'findById(')) { ?>selected<?php } ?>
              >findById()</option>

              <option
                value="findBy([], <?php echo $limit_default; ?>)"
                data-help-text="function findBy(array $criteria, array $orderBy = null, int $limit = null, int $offset = null): array"
                data-help-example='findBy(["author", "=", "John"], ["title" => "asc"], 30, 0);'
                <?php if (str_contains($query, 'findBy(')) { ?>selected<?php } ?>
              >findBy()</option>

              <option
                value="findOneBy([])"
                data-help-text="function findOneBy(array $criteria): array|null"
                data-help-example='findOneBy(["author", "=", "Mike"]);'
                <?php if (str_contains($query, 'findOneBy(')) { ?>selected<?php } ?>
              >findOneBy()</option>

              <option
                value="count()"
                data-help-text="function count(): int"
                data-help-example='count()'
                <?php if (str_contains($query, 'count(')) { ?>selected<?php } ?>
              >count()</option>

              <option
                value="insert([])"
                data-help-text="function insert(array $data): array"
                data-help-example='insert(["name" => "Josh", "age" => 23, "city" => "london"]);'
                <?php if (str_contains($query, 'insert(')) { ?>selected<?php } ?>
              >insert()</option>

              <option
                value="insertMany()"
                data-help-text="function insertMany(array $data): array"
                data-help-example='insertMany([ ["name" => "Josh", "age" => 23], ["name" => "Mike", "age" => 19], ... ]);'
                <?php if (str_contains($query, 'insertMany(')) { ?>selected<?php } ?>
              >insertMany()</option>

              <option
                value="updateById()"
                data-help-text="function updateById(int|string $id, array $updatable): array|false"
                data-help-example='updateById(24, [ "address.street" => "first street" ]);'
                <?php if (str_contains($query, 'updateById(')) { ?>selected<?php } ?>
              >updateById()</option>

              <option
                value="update()"
                data-help-text="function update(array $updatable): bool;"
                data-help-example='update([ ["_id" => 12, "title" => "SleekDB rocks!", ...], ["_id" => 13, "title" => "Multiple Updates", ...], ... ])'
                <?php if (str_contains($query, 'update(')) { ?>selected<?php } ?>
              >update()</option>

              <option
                value="removeFieldsById()"
                data-help-text="function removeFieldsById(int|string $id, array $fieldsToRemove): array|false"
                data-help-example='removeFieldsById(24, [ "name", "age" ]);'
                <?php if (str_contains($query, 'removeFieldsById(')) { ?>selected<?php } ?>
              >removeFieldsById()</option>

              <option
                value="deleteBy([])"
                data-help-text="function deleteBy(array $criteria, int $returnOption = Query::DELETE_RETURN_BOOL): array|bool|int"
                data-help-example='deleteBy(["name", "=", "Joshua Edwards"]);'
                <?php if (str_contains($query, 'deleteBy(')) { ?>selected<?php } ?>
              >deleteBy()</option>

              <option
                value="deleteById()"
                data-help-text="function deleteById(int|string $id): bool"
                data-help-example='deleteById(12);'
                <?php if (str_contains($query, 'deleteById(')) { ?>selected<?php } ?>
              >deleteById()</option>
            </select>
          </div>
          <div style="flex: initial;">
            <button type="submit">Go</button>
          </div>
        </div>
      </form>

      <div class="seperator"></div>
      <div class="seperator"></div>
      <?php if ($count === 0) { ?>
        <p><b>No results.</b></p>
      <?php } else { ?>
      <p><b>$<?php echo $store; ?>-><?php echo htmlspecialchars($query); ?>:</b></p>
      <?php } ?>

      <style>
        pre.sf-dump {
          margin-bottom: -5px;
        }
      </style>
      <pre style="padding-left: 10px; background-color: #1d1f21; border-radius: 3px;"><!--
        --><?php dump($data); ?><!--
        --><script>
          // Expand all by default
          var compacted = document.querySelectorAll('.sf-dump-compact');
          for (var i = 0; i < compacted.length; i++) {
            compacted[i].className = 'sf-dump-expanded';
          }
        </script><!--
      --></pre>

      <p><b>Showing <?php echo $count; ?> document(s). (Query took <?php echo $queryTimer; ?> sec)</b></p>

      <script>
        'use strict';
        var query = '<?php echo urlencode($query); ?>';
        query = decodeURIComponent(query);
        query = query.replace('+', ' ');

        $(document).ready(function() { showHelp(query, true) });
        $('[data-select]').on('change', function() { showHelp(query, false); });

        function showHelp(query, isFirstLoad) {
          var $option = $('[data-select]').find('option:selected');
          if ($option.val() === '') { return; }
          if (query && isFirstLoad) {
            $('[data-input]').val(query);
          }
          if (!isFirstLoad) {
            $('[data-input]').val($option.val());
          }
          $('[data-input]').caretTo('(', true);
          if ($('[data-input]').val().includes('[')) {
            $('[data-input]').caretTo('[', true);
          }
          var text = $option.attr('data-help-text');
          var example = $option.attr('data-help-example');
          if (text && example) {
            $('[data-help]').html('<small><b>Description:</b></small><br>' + text + '<br><br><small><b>Example:</b></small><br>' + example);
            $('[data-help]').show();
          } else {
            $('[data-help]').hide();
          }
        }
      </script>
    <?php
  return ob_get_clean();
}

function render_view_drop() {
  global $directory;
  global $limit_default;

  if (!isset($_GET['store'])) {
    return '';
  }

  $store = $_GET['store'];

  if (!isset($_GET['action'])) {
    return '';
  }

  $action = $_GET['action'];

  if ($action !== 'view_drop') {
    return '';
  }

  if (isset($_POST['drop'])) {
    $db_store = new \SleekDB\Store($store, $directory, ['timeout' => false]);
    $db_store->deleteStore();
    header('Location: phpsleekdbadmin.php');
    die();
  }

  ob_start();
    ?>
      <span><?php echo $directory; ?></span> → <span><?php echo $store; ?></span>
      <div class="seperator"></div>
      <div class="seperator"></div>
      <a href="?store=<?php echo urlencode($store); ?>&action=view_browse&limit=<?php echo $limit_default; ?>&offset=0&order=ASC&order_by=_id"><b>Browse</b></a>
      <span> | </span>
      <a href="?store=<?php echo urlencode($store); ?>&action=view_query"><b>Query</b></a>
      <span> | </span>
      <b>Drop</b>
      <div class="seperator"></div>
      <div class="seperator"></div>

      <p>Are you sure you want to drop the store '<?php echo $store; ?>'?</p>

      <div class="seperator"></div>
      <form method="POST" action="?store=<?php echo urlencode($store); ?>&action=view_drop">
        <input type="hidden" name="drop" value="true">
        <button type="submit">Confirm</button>
        <a href="?store=<?php echo urlencode($store); ?>&action=view_browse&limit=<?php echo $limit_default; ?>&offset=0&order=ASC&order_by=_id">Cancel</a>
      </form>

    <?php
  return ob_get_clean();
}

function render_html($stores, $html) {
  global $directory;
  global $limit_default;
  ?>
    <!DOCTYPE html>
    <html>
      <head>
        <meta charset="utf-8">

        <title>phpSleekDBAdmin</title>

        <?php render_reset_css(); ?>
        <?php render_css(); ?>

        <script src="vendor/components/jquery/jquery.min.js"></script>
        <?php render_jquery_caret(); ?>
      </head>

      <body class="margins">
        <div class="display-flex">
          <aside class="margins" style="flex: initial; width: 260px; border-right: 1px solid #ccc;">
            <span class="logo">phpSleekDBAdmin</span>
            <span class="version">v0.1.0</span>
            <div style="height: 7px;"></div>
            <a href="https://github.com/galanonym/phpsleekdbadmin" target="_blank">Documentation</a>
            <span> | </span>
            <a href="https://github.com/galanonym/phpsleekdbadmin/blob/main/LICENSE" target="_blank">License</a>
            <span> | </span>
            <a href="https://github.com/galanonym/phpsleekdbadmin" target="_blank">Project site</a>

            <div class="seperator"></div>
            <div class="seperator"></div>
            <b>Database:</b> <span><?php echo $directory; ?></span>
            <div class="seperator"></div>
            <?php foreach ($stores as $store) { ?>
              <p><a href="?store=<?php echo urlencode($store); ?>&action=view_browse&limit=<?php echo $limit_default; ?>&offset=0&order=ASC&order_by=_id">[Store] <?php echo $store; ?></a></p>
            <?php } ?>
            <div class="seperator"></div>
            <div class="seperator"></div>
            <form method="GET">
              <input type="hidden" name="action" value="view_query">
              <p>Create New Store</p>
              <input type="text" name="store">
              <div class="seperator"></div>
              <button type="submit">Create</button>
            </form>
            <div class="seperator"></div>
            <div class="seperator"></div>
            <div class="seperator"></div>
            <form method="GET">
              <input type="hidden" name="logout" value="true">
              <button type="submit">Logout</button>
            </form>
          </aside>
          <main class="margins" style="flex: 1;">
            <?php echo $html; ?>
          </main>
        </div>
      </body>
    </html>
  <?php
}

function render_view_login() {
  ?>
    <!DOCTYPE html>
    <html>
      <head>
        <meta charset="utf-8">

        <title>phpSleekDBAdmin - Login</title>

        <?php render_reset_css(); ?>
        <?php render_css(); ?>

      </head>

      <body class="margins">
        <div style="width: 250px; margin: 0 auto;">
          <div class="seperator"></div>
          <span class="logo">phpSleekDBAdmin</span>
          <span class="version">v0.1.0</span>
          <div class="seperator"></div>
          <div class="seperator"></div>
          <form method="POST">
            Password: <input type="password" name="password">
            <div class="seperator"></div>
            <button type="submit">Login</button>
          </form>
        </div>
      </body>
    </html>
  <?php
}

function render_reset_css() {
  ?>
    <style>
      /* CSS Reset from DigitalOcean */
      html {
        box-sizing: border-box;
        font-size: 16px;
      }

      *, *:before, *:after {
        box-sizing: inherit;
      }

      body, h1, h2, h3, h4, h5, h6, p, ol, ul {
        margin: 0;
        padding: 0;
        font-weight: normal;
      }

      ol, ul {
        list-style: none;
      }

      img {
        max-width: 100%;
        height: auto;
      }
    </style>
  <?php
}

function render_css() {
  ?>
   <style>
      body {
        font-family: 'Arial', sans-serif;
        font-size: 13px;
      }

      pre {
        overflow-x: auto;
        white-space: pre-wrap;
        white-space: -moz-pre-wrap;
        white-space: -pre-wrap;
        white-space: -o-pre-wrap;
        word-wrap: break-word;
       }

      input {
        font-family: monospace;
        background: #FBFBFF;
        display: inline-block;
        height: 27px;
        line-height: 27px;
        margin: 0;
        padding: 0 8px;
        vertical-align: middle;
        border: 1px solid #FFF;
        border-top-color: rgb(255, 255, 255);
        border-right-color: rgb(255, 255, 255);
        border-bottom-color: rgb(255, 255, 255);
        border-left-color: rgb(255, 255, 255);
        border-color: #D2D2DC #E6E6F0 #E6E6F0 #D2D2DC;
        -webkit-box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
        -moz-box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
        box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
        border-radius: 2px;
      }

      hr {
        border: none;
        height: 1px;
        background: #ccc;
      }

      a, a:visited {
        color: #15c;
      }

      a:hover {
        color: #00A;
      }

      button, select {
        cursor: pointer;
        vertical-align: middle;
        display: inline-block;
        margin: 0;
        outline: none;
        border: 1px solid #C8C8C8;
        border-right-color: rgb(200, 200, 200);
        border-bottom-color: rgb(200, 200, 200);
        border-bottom-color: #B4B4B4;
        border-right-color: #AAAAAA;
        height: 27px;
        padding: 0 10px;
        border-radius: 2px;
      }

      .margins {
        margin: 10px;
      }

      .display-flex {
        display: flex;
      }

      .logo {
        font-size: 24px;
      }

      .version {
        font-size: 14px;
      }

      .seperator {
        height: 10px;
      }

      .narrow {
        width: 100px;
      }

      .help {
        display: none;
        margin-top: 10px;
        margin-bottom: 20px;
        padding: 15px;
        background: #f9f9f9;
        border: 1px solid #ccc;
        border-radius: 2px;
      }
    </style>
  <?php
}

class MicroTimer {
	private $startTime, $stopTime;

	function __construct() {
		$this->startTime = microtime(true);
	}

	public function stop() {
		$this->stopTime = microtime(true);
	}

	public function elapsed() {
		if ($this->stopTime)
			return round($this->stopTime - $this->startTime, 4);
		return round(microtime(true) - $this->startTime, 4);
	}

	public function __toString() {
		return (string) $this->elapsed();
	}
}

function render_jquery_caret() {
?>
  <script>
    // Set caret position easily in jQuery
    // Written by and Copyright of Luke Morton, 2011
    // Licensed under MIT
    (function ($) {
        // Behind the scenes method deals with browser
        // idiosyncrasies and such
        $.caretTo = function (el, index) {
            if (el.createTextRange) {
                var range = el.createTextRange();
                range.move("character", index);
                range.select();
            } else if (el.selectionStart != null) {
                el.focus();
                el.setSelectionRange(index, index);
            }
        };

        // The following methods are queued under fx for more
        // flexibility when combining with $.fn.delay() and
        // jQuery effects.

        // Set caret to a particular index
        $.fn.caretTo = function (index, offset) {
            return this.queue(function (next) {
                if (isNaN(index)) {
                    var i = $(this).val().indexOf(index);

                    if (offset === true) {
                        i += index.length;
                    } else if (offset) {
                        i += offset;
                    }

                    $.caretTo(this, i);
                } else {
                    $.caretTo(this, index);
                }

                next();
            });
        };

        // Set caret to beginning of an element
        $.fn.caretToStart = function () {
            return this.caretTo(0);
        };

        // Set caret to the end of an element
        $.fn.caretToEnd = function () {
            return this.queue(function (next) {
                $.caretTo(this, $(this).val().length);
                next();
            });
        };
    }(jQuery));
    </script>
<?php
}
