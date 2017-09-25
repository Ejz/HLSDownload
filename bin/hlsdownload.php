#!/usr/bin/env php
<?php

define('ROOT', __DIR__);
define('VERSION', '__VERSION__');
define('AUTHOR', 'Evgeny Cernisev <ejz@ya.ru>');
require(ROOT . '/../vendor/autoload.php');

use Ejz\HLSDownload;

if (version_compare('5.6.0', PHP_VERSION, '>'))
    _err("Error! Minimum required version is 5.6!");

if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
    _err("Windows OS is not supported!");

$argv = isset($_SERVER['argv']) ? $_SERVER['argv'] : array();
$opts = getopts([
    'h' => false, 'help' => 'h',
    'd' => true, 'directory' => 'd',
    'F' => ['multiple' => true, 'value' => true], 'filters' => 'F',
    'no-ts' => false,
    'no-decrypt' => false,
    'limit-rate' => true,
    'width' => true,
    'progress' => false,
    'no-progress' => false,
    'thread' => true, // for multitheading purposes
], $argv);

if (is_string($opts)) {
    fwrite(STDERR, $opts . ' Use -h|--help to view help!' . "\n");
    exit(1);
}
if (!isset($opts[0])) _err("Internal error!");
if (isset($opts['h'])) goto help;
if (!isset($opts[1]) or isset($opts[2])) goto help;

$settings = array();
$settings['dir'] = isset($opts['d']) ? $opts['d'] : '.';
if (isset($opts['F'])) $settings['filters'] = $opts['F'];
if (isset($opts['limit-rate'])) $settings['limit_rate'] = $opts['limit-rate'];
$settings['decrypt'] = !isset($opts['no-decrypt']);

if (isset($opts['width']))
    $width = intval($opts['width']);
elseif (getenv("TERM")) {
    $width = intval(`tput cols`);
}

if (getenv("TERM")) $height = intval(`tput lines`);

$def = (defined('STDOUT') and posix_isatty(STDOUT) and !empty($width) and !empty($height));
if (empty($width)) $width = 80;
if (empty($height)) $height = 25;
$keys = array_keys($opts);
$ep = !empty($opts['progress']);
$dp = !empty($opts['no-progress']);
$progress = (
    ($def and !$ep and !$dp) or
    ($ep and !$dp) or
    ($ep and $dp and (array_search('progress', $keys, true) > array_search('no-progress', $keys, true)))
);
$prefix_format = "(#%s) ";
$settings['progress'] = $progress;
$settings['progress_extra_n'] = isset($opts['progress-extra-n']);
$res = HLSDownload::go($opts[1], [
    'no_ts' => (!isset($opts['thread']) or isset($opts['no-ts'])),
    'terminal_width' => (isset($opts['thread']) ? $width - strlen($prefix_format) : $width),
] + $settings);
if (isset($opts['thread']) or isset($opts['no-ts'])) exit($res ? 0 : 1);
$dir = $settings['dir'];
$thread = 0;
$threads = [];

// [-2, 0, 5] => [0, 1, 5]
function normalize_status_pos(& $threads, $base = 0) {
    $pos = [];
    foreach ($threads as & $thread)
        if (isset($thread['status']['pos'])) $pos[] = & $thread['status']['pos'];
    sort($pos);
    $rec = function (& $pos, $base) use (& $rec) {
        if (!$pos) return;
        if ($pos[0] >= $base) return;
        $pos[0] = $base;
        for ($copy = [], $i = 1; $i < count($pos); $i++) $copy[] = & $pos[$i];
        $rec($copy, $base + 1);
    };
    $rec($pos, $base);
}

function tty_make($string, $color) {
    return preg_replace("~(\(.*?\))~", "{$color}$1\033[0m", $string);
}

function tty_make_bold($string) {
    return tty_make($string, "\033[7m");
}

function tty_make_green($string) {
    return tty_make($string, "\033[1;15;42m");
}

function tty_make_red($string) {
    return tty_make($string, "\033[1;15;41m");
}

$depth = 0;
$echoCallback = function ($thread) use (
        & $threads, & $depth, $height, $width, $prefix_format
    ) {
    $threads[$thread]['prefix'] = sprintf($prefix_format, $thread);
    return function ($chunk, $exit = false) use (
        $thread, & $threads, & $depth, $height, $width
    ) {
        $t = & $threads[$thread];
        $echo_prefix = $t['prefix'];
        $buffer = & $t['buffer'];
        $buffer .= $chunk;
        $lines = preg_split('/(\n|\e\[u)/', $buffer, -1, PREG_SPLIT_DELIM_CAPTURE);
        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            $prev = (isset($lines[$i - 1]) ? $lines[$i - 1] : '');
            if (!$line) continue;
            if ($line == "\e[u") {
                if (!isset($t['status'])) $t['status'] = ['pos' => $depth - 1];
                $status = & $t['status'];
                preg_match('!\r(\S+) ~ (\S+) \(!', $prev, $match);
                if (!$match) continue;
                $status['percent'] = intval($match[2]);
                $msg = str_replace("\033[1A", "\033[%moveup%A", $prev . $line);
                $status['msg'] = $msg;
            }
            if ($line[0] == "\n") {
                if (isset($t['status'])) {
                    $s = $t['status'];
                    $s['msg'] = str_replace('%moveup%', $depth - $s['pos'], $s['msg']);
                    $_ = $s['percent'] == 100 ? tty_make_green($echo_prefix) : tty_make_red($echo_prefix);
                    echo str_replace("\r", "\r" . $_, $s['msg']);
                    unset($t['status']);
                }
                echo $echo_prefix . $prev . "\n";
                $depth += 1 + intval(strlen($echo_prefix . $prev) / $width);
                normalize_status_pos($threads, $depth - $height + 1);
            }
        }
        if (count($lines) > 1) {
            $len = array_sum(array_map('strlen', array_slice($lines, 0, -1)));
            $buffer = substr($buffer, $len);
        }
        if ($exit) {
            $_ = ($buffer ? ($echo_prefix . ' ' . $buffer) : '');
            echo $_;
            $depth += intval(strlen($_) / $width);
            $buffer = '';
        }
        foreach ($threads as $_) {
            if (!isset($_['status'])) continue;
            $s = $_['status'];
            $s['msg'] = str_replace('%moveup%', $depth - $s['pos'], $s['msg']);
            echo str_replace("\r", "\r" . tty_make_bold($_['prefix']), $s['msg']);
        }
    };
};
$loop = React\EventLoop\Factory::create();
foreach (scandir($dir) as $elem) {
    if ($elem == '..' or $elem == '.') continue;
    if (strpos($elem, 'stream') === false) continue;
    $target = $dir . '/' . $elem;
    if (!is_dir($target) and !is_file($target)) continue;
    $thread += 1;
    $argv_ = $argv;
    if (is_dir($target)) {
        $argv_[] = "-d";
        $argv_[] = $target;
    }
    $argv_[] = "-thread";
    $argv_[] = $thread;
    $argv_[] = $progress ? '-progress' : '-no-progress';
    $argv_[] = '-width';
    $argv_[] = $width;
    $argv_[array_search($opts[1], $argv_, true)] = is_file($target) ? $target : "{$target}/{$elem}.m3u8";
    $exec = array_map('escapeshellarg', $argv_);
    $exec = implode(' ', $exec);
    $process = new React\ChildProcess\Process($exec);
    $process->start($loop);
    $threads[$thread] = ['buffer' => '', 'time' => microtime(true)];
    $callback = $echoCallback($thread);
    $process->stdout->on('data', $callback);
    $process->stderr->on('data', $callback);
    $process->on('exit', function($code, $term) use ($callback, $thread, & $threads) {
        $time = round(microtime(true) - $threads[$thread]['time'], 1);
        $callback("Thread #{$thread} is finished in {$time} sec. Exit code = {$code}\n", $exit = true);
    });
    $callback("Thread #{$thread} is started!\n");
}
$loop->run();
exit(0);

help:

ob_start();
$ver = VERSION;
$aut = AUTHOR;
echo "HLSDownload {$ver} by {$aut}

Usage: hlsdownload [options] <M3U8>

Downloads recursively HLS file.

Options:

    -d, --directory <dir>     Target directory, by default CWD
    -F, --filters <filter>    Filter M3U8 streams, ex: Bandwidth=MAX
    --limit-rate <speed>      Limit download speed (can use K, M, G)
    --no-decrypt              Turn off decryption (enabled by default)
    --no-ts                   Download just manifests (TS links are normalized)
";
$ob = ob_get_clean();
fwrite(STDERR, $ob);

exit(1);
