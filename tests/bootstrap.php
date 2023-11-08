<?php
use Migrations\TestSuite\Migrator;

function find_root() {
    $root = dirname(__DIR__);
    if (is_dir($root . '/vendor/cakephp/cakephp')) {
        return $root;
    }

    $root = dirname(dirname(__DIR__));
    if (is_dir($root . '/vendor/cakephp/cakephp')) {
        return $root;
    }

    $root = dirname(dirname(dirname(__DIR__)));
    if (is_dir($root . '/vendor/cakephp/cakephp')) {
        return $root;
    }
}

function find_app() {
    if (is_dir(ROOT . '/App')) {
        return 'App';
    }

    if (is_dir(ROOT . '/vendor/cakephp/app/App')) {
        return 'vendor/cakephp/app/App';
    }
}

if (!defined('DS')){
define('DS', DIRECTORY_SEPARATOR);
}
define('ROOT', find_root());
define('APP_DIR', find_app());
define('WEBROOT_DIR', 'webroot');
define('CONFIG', ROOT . DS . 'config' . DS);
define('APP', ROOT . DS . APP_DIR . DS);
define('WWW_ROOT', ROOT . DS . WEBROOT_DIR . DS);
define('TESTS', ROOT . DS . 'Test' . DS);
define('TMP', ROOT . DS . 'tmp' . DS);
define('LOGS', TMP . 'logs' . DS);
define('CACHE', TMP . 'cache' . DS);
define('CAKE_CORE_INCLUDE_PATH', ROOT . '/vendor/cakephp/cakephp');
define('CORE_PATH', CAKE_CORE_INCLUDE_PATH . DS);
define('CAKE', CORE_PATH . 'src' . DS);

require ROOT . '/vendor/autoload.php';
require CORE_PATH . 'config/bootstrap.php';

Cake\Core\Configure::write('App', ['namespace' => 'App']);
Cake\Core\Configure::write('debug', 2);

$cache = [
    'default' => [
        'engine' => 'File',
        'path' => CACHE
    ],
    '_cake_core_' => [
        'className' => 'File',
        'prefix' => 'search_myapp_cake_core_',
        'path' => CACHE . 'persistent/',
        'serialize' => true,
        'duration' => '+10 seconds'
    ],
    '_cake_model_' => [
        'className' => 'File',
        'prefix' => 'search_my_app_cake_model_',
        'path' => CACHE . 'models/',
        'serialize' => 'File',
        'duration' => '+10 seconds'
    ]
];

Cake\Cache\Cache::setConfig($cache);

// Ensure default test connection is defined

//travis対応
if (!getenv('TESTDB') || getenv('TESTDB') == 'postgresql'){
    if (!getenv('db_class')) {
        putenv('db_class=Cake\Database\Driver\Postgres');
        putenv('db_dsn=sqlite::memory:');
    }

    Cake\Datasource\ConnectionManager::setConfig('test', [
        'className' => 'Cake\Database\Connection',
        'driver' => 'Cake\Database\Driver\Postgres',
        //'dsn' => getenv('db_dsn'),
        'database' => 'cake_test_db',
        'username' => 'postgres',
        'password' => 'postgres',
        'timezone' => 'UTC'
    ]);
    Cake\Datasource\ConnectionManager::alias('test', 'default');

} else {
    if (!getenv('db_class')) {
        putenv('db_class=Cake\Database\Driver\Mysql');
        putenv('db_dsn=sqlite::memory:');
    }

    Cake\Datasource\ConnectionManager::setConfig('test', [
        'className' => 'Cake\Database\Connection',
        'driver' => 'Cake\Database\Driver\Mysql',
        //'dsn' => getenv('db_dsn'),
        'database' => 'cake_test_db',
        'username' => 'root',
        'password' => '',
        'timezone' => 'UTC'
    ]);
    Cake\Datasource\ConnectionManager::alias('test', 'default');
}

// migration 実行
$migrator = new Migrator();
$migrator->run();

