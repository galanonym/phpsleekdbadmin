<?php
//	Project: phpSleekDBAdmin (https://kalis.no)
//	Version: 0.3.0
//	Summary: PHP-based admin tool to manage SleekDB databases on the web
//	Last updated: 2022-01-22
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

// You can configure phpSleekDBAdmin in one of 2 ways:
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

require __DIR__.'/phpsleekdbadmin_dependencies/autoload.php';

use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\VarDumper;
use battye\array_parser\parser;

// Load optional configuration file
$config_filename = './phpsleekdbadmin.config.php';
if (is_readable($config_filename)) {
  include_once $config_filename;
}

// Ensure directory seperator
if ($directory[strlen($directory)-1] != '/') {
  $directory .= '/';
}

// start() is runned at the bottom of this file
function start() {
  authenticate();

  setup_vardumper();

  $stores = scan_stores();

  $html = '';
  $html .= render_no_view($html);
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

function render_no_view() {
  if (!isset($_GET['action'])) {
    ob_start();
      ?>
        <b>Welcome to phpSleekDBAdmin!</b>
        <div class="seperator"></div>
        <span>Please select a store in the main menu.</span>
      <?php
    return ob_get_clean();
  }
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

  $time_start = microtime(true);

  $data = $db_store->findAll([$order_by => $order], $limit, $offset);
  $count = $db_store->count();

  $time_query = round(microtime(true) - $time_start, 4);

  ob_start();
    ?>
      <span><?php echo $directory; ?></span> → <span><?php echo $store; ?></span>
      <div class="seperator"></div>
      <div class="seperator"></div>
      <b>Browse</b>
      <span> | </span>
      <a href="?store=<?php echo urlencode($store); ?>&action=view_query&function_name=findAll&query_param_1=[]&query_param_2=<?php echo $limit_default; ?>&query_param_3=0"><b>Query</b></a>
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
        <p><b>Showing documents <?php echo $offset; ?> - <?php if ($offset + $limit <= $count) { echo $offset + $limit; } else { echo $count; } ?>, Total: <?php echo $count; ?> (Query took <?php echo $time_query; ?> sec)</b></p>
      <?php } ?>
      <div class="seperator"></div>
      <code style="font-size: 11px;">$store-&gt;findAll(["<?php echo $order_by; ?>" =&gt; "<?php echo $order; ?>"], <?php echo $limit; ?>, <?php echo $offset; ?>)</code>

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

      <?php if ($count > 1) { ?>
        <p><button data-compact>Compact</button> <button data-expand>Expand</button></p>
        <script>
          'use strict';
          $('[data-compact]').on('click', function() {
            $('.sf-dump-expanded:not(:first)').prev().text('▼');
            $('.sf-dump-expanded:not(:first)').removeClass('sf-dump-expanded').addClass('sf-dump-compact');
          });
          $('[data-expand]').on('click', function() {
            $('.sf-dump-compact').prev().text('▶');
            $('.sf-dump-compact').removeClass('sf-dump-compact').addClass('sf-dump-expanded');
          });
        </script>
      <?php } ?>
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

  $function_name = $_GET['function_name'] ?? '';
  $query_param_1 = $_GET['query_param_1'] ?? '';
  $query_param_2 = $_GET['query_param_2'] ?? '';
  $query_param_3 = $_GET['query_param_3'] ?? '';
  $query_param_4 = $_GET['query_param_4'] ?? '';

  $count = 0;
  $data = [];

  $time_start = microtime(true);

  $db_store = new \SleekDB\Store($store, $directory, ['timeout' => false]);

  try {

    if ($function_name === 'findAll') {
      $order_by = parser::parse_simple($query_param_1);
      $limit = intval($query_param_2);
      $offset = intval($query_param_3);
      $data = $db_store->findAll($order_by, $limit, $offset);
    }

    if ($function_name === 'findById') {
      $id = $query_param_1;
      $data = $db_store->findById($id);
    }

    if ($function_name === 'findBy') {
      $criteria = parser::parse_simple($query_param_1);
      $order_by = parser::parse_simple($query_param_2);
      $limit = intval($query_param_3);
      $offset = intval($query_param_4);
      $data = $db_store->findBy($criteria, $order_by, $limit, $offset);
    }

    if ($function_name === 'findOneBy') {
      $criteria = parser::parse_simple($query_param_1);
      $data = $db_store->findOneBy($criteria);
    }

    if ($function_name === 'count') {
      $data = $db_store->count();
    }

    if ($function_name === 'insert') {
      $insertable = parser::parse_simple($query_param_1);
      $data = $db_store->insert($insertable);
    }

    if ($function_name === 'insertMany') {
      $insertable = parser::parse_simple($query_param_1);
      $data = $db_store->insertMany($insertable);
    }

    if ($function_name === 'updateById') {
      $id = $query_param_1;
      $updatable = parser::parse_simple($query_param_2);

      $data = $db_store->updateById($id, $updatable);
    }

    if ($function_name === 'update') {
      $updatable = parser::parse_simple($query_param_1);
      $data = $db_store->update($updatable);
    }

    if ($function_name === 'updateOrInsert') {
      $insertable = parser::parse_simple($query_param_1);
      $autoGenerateIdOnInsert = boolval($query_param_2);
      $data = $db_store->updateOrInsert($insertable, $autoGenerateIdOnInsert);
    }

    if ($function_name === 'updateOrInsertMany') {
      $insertable = parser::parse_simple($query_param_1);
      $autoGenerateIdOnInsert = boolval($query_param_2);

      $data = $db_store->updateOrInsertMany($insertable, $autoGenerateIdOnInsert);
    }

    if ($function_name === 'removeFieldsById') {
      $id = $query_param_1;
      $fieldsToRemove = parser::parse_simple($query_param_2);
      $data = $db_store->removeFieldsById($id, $fieldsToRemove);
    }

    if ($function_name === 'deleteBy') {
      $criteria = parser::parse_simple($query_param_1);
      $data = $db_store->deleteBy($criteria);
    }

    if ($function_name === 'deleteById') {
      $id = $query_param_1;
      $data = $db_store->deleteById($id);
    }
  } catch (Throwable $e) {
    $data = $e->getMessage();

    // Improved error messages
    if (str_starts_with($data, '[php-array-parser]')) {
      $data = str_replace('[php-array-parser] ', 'Parse error: ', $data);
    }

    if (str_contains($e->getFile(), 'sleekdb/src/QueryBuilder.php')) {
      $data = 'Query error: ' . $data;
    }

    if (str_contains($e->getFile(), 'sleekdb/src/Classes/ConditionsHandler.php')) {
      $data = 'Condition error: ' . $data;
    }
  }

  if (isset($data[0]) AND is_array($data[0])) {
    $count = count($data);
  } else {
    $count = 1;
  }

  $query = '$' . $store . '->' . $function_name . '(';
  if ($query_param_1 !== '') { $query .= $query_param_1; }
  if ($query_param_2 !== '') { $query .= ', ' . $query_param_2; }
  if ($query_param_3 !== '') { $query .= ', ' . $query_param_3; }
  if ($query_param_4 !== '') { $query .= ', ' . $query_param_4; }
  $query .= ')';

  $time_query = round(microtime(true) - $time_start, 4);

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

        <code><span style="font-size: 11px;">Method name - Hint: <span data-hint></span></span></code>
        <div style="height: 2px;"></div>
        <select data-select name="function_name">
          <option value="findAll" <?php if ($function_name === 'findAll') { ?>selected<?php } ?> >$<?php echo $store; ?>-&gt;findAll()</option>
          <option value="findById" <?php if ($function_name === 'findById') { ?>selected<?php } ?> >$<?php echo $store; ?>-&gt;findById()</option>
          <option value="findBy" <?php if ($function_name === 'findBy') { ?>selected<?php } ?> >$<?php echo $store; ?>-&gt;findBy()</option>
          <option value="findOneBy" <?php if ($function_name === 'findOneBy') { ?>selected<?php } ?> >$<?php echo $store; ?>-&gt;findOneBy()</option>
          <option value="count" <?php if ($function_name === 'count') { ?>selected<?php } ?> >$<?php echo $store; ?>-&gt;count()</option>
          <option value="insert" <?php if ($function_name === 'insert') { ?>selected<?php } ?> >$<?php echo $store; ?>-&gt;insert()</option>
          <option value="insertMany" <?php if ($function_name === 'insertMany') { ?>selected<?php } ?> >$<?php echo $store; ?>-&gt;insertMany()</option>
          <option value="updateById" <?php if ($function_name === 'updateById') { ?>selected<?php } ?> >$<?php echo $store; ?>-&gt;updateById()</option>
          <option value="update" <?php if ($function_name === 'update') { ?>selected<?php } ?> >$<?php echo $store; ?>-&gt;update()</option>
          <option value="updateOrInsert" <?php if ($function_name === 'updateOrInsert') { ?>selected<?php } ?> >$<?php echo $store; ?>-&gt;updateOrInsert()</option>
          <option value="updateOrInsertMany" <?php if ($function_name === 'updateOrInsertMany') { ?>selected<?php } ?> >$<?php echo $store; ?>-&gt;updateOrInsertMany()</option>
          <option value="removeFieldsById" <?php if ($function_name === 'removeFieldsById') { ?>selected<?php } ?> >$<?php echo $store; ?>-&gt;removeFieldsById()</option>
          <option value="deleteBy" <?php if ($function_name === 'deleteBy') { ?>selected<?php } ?> >$<?php echo $store; ?>-&gt;deleteBy()</option>
          <option value="deleteById" <?php if ($function_name === 'deleteById') { ?>selected<?php } ?> >$<?php echo $store; ?>-&gt;deleteById()</option>
        </select>

        <div style="display: none;" data-function-param-1>
          <div class="seperator"></div>
          <code style="font-size: 11px;">Method parameter 1 - Type: array $orderBy - Example: ["name" =&gt; "asc"]</code>
          <div style="height: 2px;"></div>
          <input data-input style="width: 100%;" type="text" name="query_param_1" value="">
        </div>

         <div style="display: none;" data-function-param-2>
          <div class="seperator"></div>
          <code style="font-size: 11px;">Method parameter 2 - Type: int $limit - Example: 30</code>
          <div style="height: 2px;"></div>
          <input type="text" style="width: 100%" name="query_param_2" value="">
        </div>

         <div style="display: none;" data-function-param-3>
          <div class="seperator"></div>
          <code style="font-size: 11px;">Method parameter 3 - Type: int $offset - Example: 0</code>
          <div style="height: 2px;"></div>
          <input type="text" style="width: 100%" name="query_param_3" value="">
        </div>

         <div style="display: none;" data-function-param-4>
          <div class="seperator"></div>
          <code style="font-size: 11px;">Method parameter 4 - Type: int $offset - Example: 0</code>
          <div style="height: 2px;"></div>
          <input type="text" style="width: 100%" name="query_param_4" value="">
        </div>

        <div class="seperator"></div>
        <button type="submit">Go</button>
      </form>

      <div class="seperator"></div>
      <div class="seperator"></div>

      <p><b>Showing <?php echo $count; ?> document(s). (Query took <?php echo $time_query; ?> sec)</b></p>
      <div class="seperator"></div>

      <?php if ($count === 0) { ?>
        <p><code style="font-size: 11px;">No results.</code></p>
      <?php } else { ?>
        <p><code style="font-size: 11px;"><?php echo htmlspecialchars($query); ?></code></p>
      <?php } ?>

      <style>
        pre.sf-dump {
          margin-bottom: -5px;
        }
      </style>
      <pre style="padding-left: 10px; background-color: #1d1f21; border-radius: 3px;"><!--
        --><?php dump($data); ?><!--
        --><script>
          'use strict';
          $('.sf-dump-compact').removeClass('sf-dump-compact').addClass('sf-dump-expanded');
        </script><!--
      --></pre>

      <?php if ($count > 1) { ?>
        <p><button data-compact>Compact</button> <button data-expand>Expand</button></p>
        <script>
          'use strict';
          $('[data-compact]').on('click', function() {
            $('.sf-dump-expanded:not(:first)').prev().text('▼');
            $('.sf-dump-expanded:not(:first)').removeClass('sf-dump-expanded').addClass('sf-dump-compact');
          });
          $('[data-expand]').on('click', function() {
            $('.sf-dump-compact').prev().text('▶');
            $('.sf-dump-compact').removeClass('sf-dump-compact').addClass('sf-dump-expanded');
          });
        </script>
      <?php } ?>

      <script>
        'use strict';
        var functionName = '<?php echo $function_name; ?>';
        var limitDefault = '<?php echo $limit_default; ?>';

        var queryParam1 = b64_to_utf8('<?php echo base64_encode($query_param_1); ?>');
        var queryParam2 = b64_to_utf8('<?php echo base64_encode($query_param_2); ?>');
        var queryParam3 = b64_to_utf8('<?php echo base64_encode($query_param_3); ?>');
        var queryParam4 = b64_to_utf8('<?php echo base64_encode($query_param_4); ?>');

        $(document).ready(function() { viewInputs(true) });
        $('[data-select]').on('change', function() { viewInputs(false); });

        function viewInputs(isFirstLoad) {
          $('[data-function-param-1]').hide().find('input').val('');
          $('[data-function-param-2]').hide().find('input').val('');
          $('[data-function-param-3]').hide().find('input').val('');
          $('[data-function-param-4]').hide().find('input').val('');

          var $option = $('[data-select]').find('option:selected');

          if (isFirstLoad) {
            $option = $('option[value=' + functionName + ']');
            $option.prop('selected', true);
          }

          var value = $option.val();

          if (value === 'findAll') {
            $('[data-hint]').html('Get all documents.');

            $('[data-function-param-1]').show().find('code').html('Method parameter 1 - Type: array $orderBy - Example: ["name" =&gt; "asc"]');
            $('[data-function-param-1]').find('input').val(queryParam1 && isFirstLoad ? queryParam1 : '[]').caretTo('[', true);

            $('[data-function-param-2]').show().find('code').html('Method parameter 2 - Type: int $limit - Example: 30');
            $('[data-function-param-2]').find('input').val(queryParam2 && isFirstLoad ? queryParam2 : limitDefault);

            $('[data-function-param-3]').show().find('code').html('Method parameter 3 - Type: int $offset - Example: 0');
            $('[data-function-param-3]').find('input').val(0);
          }

          if (value === 'findById') {
            $('[data-hint]').html('Get a single document by _id.');

            $('[data-function-param-1]').show().find('code').html('Method parameter 1 - Type: int|string $id - Example: 1');
            $('[data-function-param-1]').find('input').val(queryParam1 && isFirstLoad ? queryParam1 : '').caretTo('', true);
          }

          if (value === 'findBy') {
            $('[data-hint]').html('Get one or multiple documents by chosen criteria.');

            $('[data-function-param-1]').show().find('code').html('Method parameter 1 - Type: array $criteria - Example: ["author", "=", "Mike"]');
            $('[data-function-param-1]').find('input').val(queryParam1 && isFirstLoad ? queryParam1 : '[]').caretTo('[', true);

            $('[data-function-param-2]').show().find('code').html('Method parameter 2 - Type: array $orderBy - Example: ["name" =&gt; "asc"]');
            $('[data-function-param-2]').find('input').val(queryParam2 && isFirstLoad ? queryParam2 : '[]');

            $('[data-function-param-3]').show().find('code').html('Method parameter 3 - Type: int $limit - Example: 30');
            $('[data-function-param-3]').find('input').val(queryParam3 && isFirstLoad ? queryParam3 : limitDefault);

            $('[data-function-param-4]').show().find('code').html('Method parameter 4 - Type: int $offset - Example: 0');
            $('[data-function-param-4]').find('input').val(0);
          }

          if (value === 'findOneBy') {
            $('[data-hint]').html('Get one document by chosen criteria.');

            $('[data-function-param-1]').show().find('code').html('Method parameter 1 - Type: array $criteria - Example: ["author", "=", "Mike"]');
            $('[data-function-param-1]').find('input').val(queryParam1 && isFirstLoad ? queryParam1 : '[]').caretTo('[', true);
          }

          if (value === 'count') {
            $('[data-hint]').html('Get the amount of all documents stored.');
          }

          if (value === 'insert') {
            $('[data-hint]').html('Insert a single document.');

            $('[data-function-param-1]').show().find('code').html('Method parameter 1 - Type: array $data - Example: ["name" => "Josh", "age" => 23, "city" => "london"]');
            $('[data-function-param-1]').find('input').val(queryParam1 && isFirstLoad ? queryParam1 : '[]').caretTo('[', true);
          }

          if (value === 'insertMany') {
            $('[data-hint]').html('Insert multiple documents.');

            $('[data-function-param-1]').show().find('code').html('Method parameter 1 - Type: array $data - Example: [ ["name" => "Josh", "age" => 23], ["name" => "Mike", "age" => 19], ... ]');
            $('[data-function-param-1]').find('input').val(queryParam1 && isFirstLoad ? queryParam1 : '[]').caretTo('[', true);
          }

          if (value === 'updateById') {
            $('[data-hint]').html('Update parts of a document.');

            $('[data-function-param-1]').show().find('code').html('Method parameter 1 - Type: int|string $id - Example: 1');
            $('[data-function-param-1]').find('input').val(queryParam1 && isFirstLoad ? queryParam1 : '').caretTo('', true);

            $('[data-function-param-2]').show().find('code').html('Method parameter 2 - Type: array $updatable - Example: [ "name" => "Georg", "age" => 22 ] or [ "address.street" => "first street" ]');
            $('[data-function-param-2]').find('input').val(queryParam2 && isFirstLoad ? queryParam2 : '[]');
          }

          if (value === 'update') {
            $('[data-hint]').html('Update multiple whole documents. Must have _ids defined.');

            $('[data-function-param-1]').show().find('code').html('Method parameter 1 - Type: array $updatable - Example: [ ["_id" => 12, "title" => "SleekDB rocks!", ...], ["_id" => 13, "title" => "Multiple Updates", ...], ... ]');
            $('[data-function-param-1]').find('input').val(queryParam1 && isFirstLoad ? queryParam1 : '[]').caretTo('[', true);
          }


          if (value === 'updateOrInsert') {
            $('[data-hint]').html('Update or insert one whole document.');

            $('[data-function-param-1]').show().find('code').html('Method parameter 1 - Type: array $data - Example: ["_id" => 23, "name" => "John", ...  ]');
            $('[data-function-param-1]').find('input').val(queryParam1 && isFirstLoad ? queryParam1 : '[]').caretTo('[', true);

            $('[data-function-param-2]').show().find('code').html('Method parameter 2 - Type: bool $autoGenerateIdOnInsert - Example: true (Apply an auto-generated _id if it is an insert)');
            $('[data-function-param-2]').find('input').val('true');
          }

          if (value === 'updateOrInsertMany') {
            $('[data-hint]').html('Update or insert multiple whole documents.');

            $('[data-function-param-1]').show().find('code').html('Method parameter 1 - Type: array $data - Example: [ ["name" => "Josh", "age" => 23], ["name" => "Mike", "age" => 19], ... ]');
            $('[data-function-param-1]').find('input').val(queryParam1 && isFirstLoad ? queryParam1 : '[]').caretTo('[', true);

            $('[data-function-param-2]').show().find('code').html('Method parameter 2 - Type: bool $autoGenerateIdOnInsert - Example: true (Apply an auto-generated _id if it is an insert)');
            $('[data-function-param-2]').find('input').val('true');
          }

          if (value === 'removeFieldsById') {
            $('[data-hint]').html('Remove parts of a document.');

            $('[data-function-param-1]').show().find('code').html('Method parameter 1 - Type: int|string $id - Example: 1');
            $('[data-function-param-1]').find('input').val(queryParam1 && isFirstLoad ? queryParam1 : '').caretTo('', true);

            $('[data-function-param-2]').show().find('code').html('Method parameter 2 - Type: array $fieldsToRemove - Example: [ "name", "age" ]');
            $('[data-function-param-2]').find('input').val(queryParam2 && isFirstLoad ? queryParam2 : '[]');
          }

          if (value === 'deleteBy') {
            $('[data-help]').html('Delete one or multiple documents by chosen criteria.');

            $('[data-function-param-1]').show().find('code').html('Method parameter 1 - Type: array $criteria - Example: ["name", "=", "Joshua Edwards"]]');
            $('[data-function-param-1]').find('input').val(queryParam1 && isFirstLoad ? queryParam1 : '[]').caretTo('[', true);
          }

          if (value === 'deleteById') {
            $('[data-hint]').html('Delete one document by _id.');

            $('[data-function-param-1]').show().find('code').html('Method parameter 1 - Type: int|string $id - Example: 1');
            $('[data-function-param-1]').find('input').val(queryParam1 && isFirstLoad ? queryParam1 : '').caretTo('', true);
          }
        }

        function b64_to_utf8( str ) {
          return decodeURIComponent(escape(window.atob( str )));
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
      <a href="?store=<?php echo urlencode($store); ?>&action=view_query&function_name=findAll"><b>Query</b></a>
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

  $action = $_GET['action'] ?? 'view_browse';
  ?>
    <!DOCTYPE html>
    <html>
      <head>
        <meta charset="utf-8">

        <title>phpSleekDBAdmin</title>

        <link rel="icon" href="data:image/x-icon;base64,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">

        <?php render_reset_css(); ?>
        <?php render_css(); ?>

        <script src="phpsleekdbadmin_dependencies/components/jquery/jquery.min.js"></script>
        <?php render_jquery_caret(); ?>
      </head>

      <body class="margins">
        <div class="display-flex">
          <aside class="margins" style="flex: initial; width: 260px; border-right: 1px solid #ccc;">
            <span class="logo">phpSleekDBAdmin</span>
            <span class="version">v0.3.0</span>
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
              <p><a href="?store=<?php echo urlencode($store); ?>&action=<?php echo urlencode($action); ?><?php if ($action === 'view_query') { ?>&function_name=findAll&query_param_1=[]&query_param_2=<?php echo $limit_default; ?>&query_param_3=0<?php } ?>">[Store] <?php echo $store; ?></a></p>
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
          <main class="margins" style="flex: 1; max-width: 900px;">
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
          <span class="version">v0.3.0</span>
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
    </style>
  <?php
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

start();
