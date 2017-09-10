<?php

// ------------ //
//     Init     //
// ------------ //

define('START_TIME', microtime(true));
if (defined('ROOT')) {
    echo __FILE__ . " is executed twice!\n";
    exit(1);
}
define('ROOT', __DIR__);
date_default_timezone_set('UTC');
ini_set('display_errors', '1');
error_reporting(E_ALL);
if (!function_exists('mb_internal_encoding')) {
    echo "No mbstring module!\n";
    exit(1);
}
mb_internal_encoding('utf-8');
ini_set('mysql.connect_timeout', 1000);
ini_set('default_socket_timeout', 1000);
ini_set('session.cookie_lifetime', $_ = (3600 * 24 * 365));
ini_set('session.gc_maxlifetime', $_);

if (!is_file($_ = ROOT . '/vendor/autoload.php')) {
    echo "No autoload.php! Use Composer to install all dependencies!\n";
    exit(1);
} else require(ROOT . '/vendor/autoload.php');

// ---------------- //
//      Config      //
// ---------------- //

define('GLOBAL_INI', ROOT . '/global.ini');
define('LOCAL_INI', ROOT . '/local.ini');
$global = parse_ini_file(GLOBAL_INI, true);
if (is_file(LOCAL_INI))
    $local = parse_ini_file(LOCAL_INI, true);
else $local = [];
$local = $local ?: [];
config('.', array_replace_recursive($global, $local));

// ------------ //
//  getRequest  //
// ------------ //

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorage;

function getRequest() {
    static $request = null;
    if (!is_null($request)) return $request;
    if (!defined('STDIN')) {
        $request = Request::createFromGlobals();
        Request::setTrustedProxies(array('127.0.0.1', $request->server->get('REMOTE_ADDR')));
        $session = new Session();
        $session->start();
        $request->setSession($session);
        return $request;
    }
    $argv = isset($_SERVER['argv']) ? $_SERVER['argv'] : array();
    $handle = STDIN;
    $content = null;
    if ($handle and ftell($handle) === 0 and isset($argv[0]) and basename($argv[0]) != basename(__FILE__)) {
        $content = '';
        while (!feof($handle)) $content .= fread($handle, 1024 * 1024);
    }
    if (!is_null($content)) parse_str(rtrim($content), $parameters);
    $default_scheme = config("global.default_scheme") ?: "http";
    $default_host = config("global.default_host") ?: "localhost";
    $scheme = getenv("SCHEME") ?: $default_scheme;
    $host = getenv("HOST") ?: $default_host;
    $uri = (isset($argv[1]) and (host($argv[1]) or $argv[1][0] === '/')) ? $argv[1] : '/';
    if (!host($uri)) $uri = "{$scheme}://{$host}{$uri}";
    $request = Request::create(
        $uri,
        is_null($content) ? 'GET' : 'POST',
        isset($parameters) ? $parameters : array()
    );
    $session = new Session(new MockFileSessionStorage());
    $session->start();
    $request->setSession($session);
    if ($request->isMethod('POST')) {
        $request->headers->set('Referer', $request->getRequestUri());
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
    }
    return $request;
}

// ----------------- //
//     Constants     //
// ----------------- //

if (is_dir($_ = ROOT . '/../www'))
    define('WWW_ROOT', $_);
else define('WWW_ROOT', ROOT . '/www');
define('LOGS_ROOT', ROOT . '/logs');
define('TPL_ROOT', ROOT . '/tpl');
define('PAGE_ROOT', ROOT . '/page');
define('STAT_ROOT', ROOT . '/stat');
define('IS_DEV', config('global.is_dev'));

if (is_file($_ = ROOT . '/constants.php'))
    require_once($_);

// --------------- //
//     Monolog     //
// --------------- //

use Monolog\Logger;
use Monolog\ErrorHandler;
use Monolog\Handler\StreamHandler;
use Ejz\LogFormatter\LogFormatter;

if (is_file($_ = ROOT . '/formatter.php'))
    require_once($_);

$logger = config('logger') ?: [];
foreach ($logger as $name => $level) {
    $logger = new Logger($name);
    if ($name === 'php') {
        $handler = new ErrorHandler($logger);
        $handler->registerErrorHandler(/* $levelMap = */array(), $callPrevious = false);
        $handler->registerExceptionHandler(/* $level = */null, $callPrevious = false);
        $handler->registerFatalHandler();
    }
    $file = LOGS_ROOT . sprintf("/%s.%s.%s.log",
        defined('STDIN') ? 'cli' : 'web',
        $name,
        date('Y-m-d')
    );
    $log = new StreamHandler($file, constant('\Monolog\Logger::' . $level));
    $log->setFormatter(new LogFormatter());
    $logger->pushHandler($log);
    if (defined('STDIN')) {
        $log = new StreamHandler('php://stderr', constant('\Monolog\Logger::' . $level));
        $log->setFormatter(new LogFormatter());
        $logger->pushHandler($log);
    }
    Monolog\Registry::addLogger($logger);
}

// ----- //
//  SQL  //
// ----- //

$sql = config("sql");
if ($sql)
    SQL(
        @ $sql['host'],
        @ $sql['user'],
        @ $sql['pass'],
        @ $sql['db'],
        @ $sql['port']
    );

// ----------- //
//  Redis (R)  //
// ----------- //

$redis = config("redis");
if ($redis)
    R(
        @ $redis['host'],
        @ $redis['port'],
        @ $redis['auth'],
        @ $redis['db']
    );

// ------- //
//  Cache  //
// ------- //

use Ejz\Cache;

function cache_clear() {
    return call_user_func_array(['Ejz\Cache', 'clear'], func_get_args());
}

function cache_search() {
    return call_user_func_array(['Ejz\Cache', 'search'], func_get_args());
}

function cache_search_tags() {
    return call_user_func_array(['Ejz\Cache', 'searchTags'], func_get_args());
}

function cache_drop() {
    return call_user_func_array(['Ejz\Cache', 'drop'], func_get_args());
}

// ------- //
//  Extra  //
// ------- //

set_include_path(get_include_path() . PATH_SEPARATOR . TPL_ROOT);
if (is_file($_ = ROOT . '/handler.php'))
    require_once($_);

// ----------- //
//  getRoutes  //
// ----------- //

function getRoutes() {
    static $routes = null;
    if (is_array($routes)) return $routes;
    $routes = array();
    if (is_file($include = (ROOT . '/routes.php'))) {
        $_ = include($include);
        if (is_array($_)) $routes = $_ + $routes;
    }
    return $routes;
}

// ----------- //
//  Functions  //
// ----------- //

function encrypt($string, $expire = 0) {
    $secret = config('global.secret');
    $expire = $expire ? time() + $expire : 0;
    $string = $expire . ':' . $string;
    return xencrypt($string, $secret);
}

function decrypt($string) {
    $secret = config('global.secret');
    $string = xdecrypt($string, $secret);
    $string = explode(':', $string, 2);
    if (!isset($string[1])) return;
    if ($string[0] and $string[0] < time()) return;
    return $string[1];
}

function econfig($config) {
    $value = config($config);
    if (strpos($value, '---') === 0)
        $value = decrypt(substr($value, 3));
    return $value;
}

function _monolog() {
    $args = func_get_args();
    $type = array_shift($args);
    if (count($args) < 2) return;
    list($source, $message) = $args;
    $context = isset($args[2]) ? (array)($args[2]) : array();
    $log = \Monolog\Registry::$source();
    return $log->{"add{$type}"}($message, $context);
}

function _debug() {
    $args = func_get_args();
    array_unshift($args, 'Debug');
    return call_user_func_array('_monolog', $args);
}

function _info($source, $message, $context = array()) {
    $args = func_get_args();
    array_unshift($args, 'Info');
    return call_user_func_array('_monolog', $args);
}

function _notice($source, $message, $context = array()) {
    $args = func_get_args();
    array_unshift($args, 'Notice');
    return call_user_func_array('_monolog', $args);
}

function _warning($source, $message, $context = array()) {
    $args = func_get_args();
    array_unshift($args, 'Warning');
    return call_user_func_array('_monolog', $args);
}

function _error($source, $message, $context = array()) {
    $args = func_get_args();
    array_unshift($args, 'Error');
    return call_user_func_array('_monolog', $args);
}

function _T($var, $lang = '') {
    $lang = $lang ?: config('global.lang');
    static $map = array();
    if (!$map) $map = require(ROOT . "/lang.php");
    $key = "{$lang}-{$var}";
    return isset($map[$key]) ? $map[$key] : $var;
}

function getToken($expire = 86400) { // 24 hours
    return encrypt(mt_rand(), $expire);
}

function globalInvoker(Request $request, $settings, $callstack) {
    $session = $request->getSession();
    $vars = $request->attributes->all();
    $nocache = $request->query->get('nocache');
    $request->query->remove('nocache');
    $nocache = ($nocache === "" or $nocache === "true" or $nocache === "1" or $nocache === "yes");
    $recache = $request->query->get('recache');
    $request->query->remove('recache');
    $recache = ($recache === "" or $recache === "true" or $recache === "1" or $recache === "yes");
    $settings['nocache'] = isset($settings['nocache']) ? $settings['nocache'] : ($nocache);
    $settings['recache'] = isset($settings['recache']) ? $settings['recache'] : ($recache);
    $settings['cache'] = isset($settings['cache']['key']) ? $settings['cache'] : false;
    if ($settings['nocache']) $settings['cache'] = false;
    unset($settings['nocache']);
    $vars['request'] = $request;
    $vars['session'] = $session;
    $vars["is_{$vars['_route']}"] = true;
    $vars['ip'] = $request->getClientIp();
    $vars['settings'] = & $settings;
    $vars['vars'] = & $vars;
    if (!empty($vars['lang'])) {
        config('global.lang', $_ = rtrim($vars['lang'], '/'));
        $vars['lang'] = '/' . $_;
    }
    if (isset($settings['force /']) and $settings['force /']) {
        $pathInfo = $request->getPathInfo();
        $requestUri = $request->getRequestUri();
        if ($pathInfo[strlen($pathInfo) - 1] != "/") {
            $url = str_replace($pathInfo, $pathInfo . '/', $requestUri);
            return new RedirectResponse($url, 302);
        }
    }
    $vars['me'] = $session->get('me', array());
    if (isset($settings['token']) and $settings['token']) {
        $vars['token'] = getToken();
        $session->set($vars['token'], array());
    }
    $modifyResponse = function (Response $response) use (& $settings) {
        if (isset($settings['appendTime']) and $settings['appendTime']) {
            $_ = sprintf("<!-- %s -->\n", round(microtime(true) - START_TIME, 3));
            $response->setContent($response->getContent() . $_);
        }
        return $response;
    };
    $callstack = is_array($callstack) ? $callstack : explode(',', $callstack);
    foreach ($callstack as $call)
        if (is_callable($call)) {
            $response = call_user_func_array($call, array(& $vars));
            if (is_string($response)) $response = new Response($response);
            if ($response instanceof Response) ; else continue;
            return $modifyResponse($response);
        }
    $content = template('all.php', $vars);
    if (isset($settings['cache']) and $settings['cache'] and Cache::$key) {
        Cache::set(Cache::$key, $content, Cache::$expire, Cache::$tags);
        $settings['recache'] = false;
        return call_user_func_array(__FUNCTION__, array($request, $settings, $callstack));
    }
    $response = new Response($content);
    return $modifyResponse($response);
}

function paginator($paginator, $request) {
    $getPage = function ($page) use ($request) {
        $query = array();
        $url = $request->getPathInfo();
        $all = $request->query->all();
        $all['page'] = $request->query->get('page', 1);
        foreach ($all as $k => $v)
            if ($k == 'page' and $page == 1) ;
            elseif ($k == 'page') $query[] = $k . '=' . $page;
            else $query[] = $k . '=' . urlencode($v);
        $query = implode('&', $query);
        if ($query) return "{$url}?{$query}";
        return $url;
    };
    $return = array('echo' => array());
    list($page, $total) = $paginator;
    if ($total < 2) return $return;
    if ($page != 1 and $page <= $total) {
        $href = $getPage($page - 1);
        $return['prev'] = $href;
        $href = fesc($href);
        $prev = esc(_T('paginator-prev'));
        $return['echo'][] = "<a class=\"prev\" href=\"{$href}\">{$prev}</a>";
    }
    $dots = [$page - 2, $page + 2];
    $display = [1, $page - 1, $page + 1, $total];
    for ($i = 1; $i <= $total; $i++) {
        if ($i == $page) $return['echo'][] = "<span class=\"page\">{$i}</span>";
        elseif (in_array($i, $display)) {
            $href = $getPage($i);
            $href = fesc($href);
            $return['echo'][] = "<a class=\"page\" href=\"{$href}\">{$i}</a>";
        } elseif (in_array($i, $dots)) $return['echo'][] = '<span class="dot"></span>';
    }
    if ($page != $total and $page <= $total) {
        $href = $getPage($page + 1);
        $return['next'] = $href;
        $href = fesc($href);
        $next = esc(_T('paginator-next'));
        $return['echo'][] = "<a class=\"next\" href=\"{$href}\">{$next}</a>";
    }
    $return['echo'] = implode('', $return['echo']);
    if ($return['echo']) $return['echo'] = "<div class=\"paginator\">{$return['echo']}</div>";
    return $return;
}

function hash_password($password, $salt = '') {
    return md5(implode('', [
        $salt, rand_from_string($password), $salt,
        $password,
        $salt, rand_from_string($password), $salt,
    ]));
}

function generate_password($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $count = mb_strlen($chars);
    for ($i = 0, $result = []; $i < $length; $i++) {
        $index = mt_rand(0, $count - 1);
        $result[] = mb_substr($chars, $index, 1);
    }
    return implode('', $result);
}

if (is_file($_ = ROOT . '/extra.php'))
    require_once($_);

// --------------- //
//  bootstrap.php  //
// --------------- //

$argv = isset($_SERVER['argv']) ? $_SERVER['argv'] : array();
if (defined('STDIN') and basename($argv[0]) === basename(__FILE__)) {
    if (!isset($argv[1])) _err("NO FUNCTION @ " . __FILE__);
    $function = $argv[1];
    array_shift($argv);
    array_shift($argv);
    if (defined($function)) {
        echo constant($function), "\n";
        exit(0);
    }
    if (!is_callable($function))
        foreach (glob(ROOT . '/cli/*.php') as $php)
            include_once($php);
    if (!is_callable($function) and preg_match('~\*$~', $function)) {
        $function = substr($function, 0, strlen($function) - 1);
        if ($function[0] === '*') {
            $star = true;
            $function = substr($function, 1);
        } else $star = false;
        $functions = get_defined_functions();
        foreach ($functions['user'] as $f)
            if (stripos($f, $function) === 0 or ($star and stripos($f, $function) !== false)) {
                $list = nsplit(shell_exec($_ = sprintf("grep --include '*.php' -Ri %s %s | grep function", escapeshellarg($f), ROOT)));
                $clue = '';
                foreach ($list as $l) {
                    $at = preg_replace('~^.*?(\S+):.*$~', '$1', $l);
                    $args = (preg_replace('~^.*\((.*?)\).*$~', '$1', $l) ?: '()');
                    $clue .= " | @ {$at} : {$args}";
                }
                _log($f . $clue);
            }
        exit(0);
    }
    if (!is_callable($function)) _err("FUNCTION {$function} IS NOT FOUND @ " . __FILE__);
    $argv = array_map('readable_to_variable', $argv);
    $result = call_user_func_array($function, $argv);
    echo variable_to_readable($result);
    exit(($result === null or $result === "" or $result === false) ? 1 : 0);
}
