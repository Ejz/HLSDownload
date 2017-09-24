#!/usr/bin/env php
<?php

define('ROOT', __DIR__);
define('VERSION', '1.4.1');
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
    'progress' => false,
    'no-progress' => false,
    'progress-extra-n' => false,
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

$width = intval(`tput cols 2>/dev/null`);
$height = intval(`tput lines 2>/dev/null`) - 1;
$def = (defined('STDOUT') and posix_isatty(STDOUT) and $width and $height);
$keys = array_keys($opts);
$ep = !empty($opts['progress']);
$dp = !empty($opts['no-progress']);
$progress = (
    ($def and !$ep and !$dp) or
    ($ep and !$dp) or
    ($ep and $dp and (array_search('progress', $keys, true) > array_search('no-progress', $keys, true)))
);
$settings['progress'] = $progress;
$settings['progress_extra_n'] = isset($opts['progress-extra-n']);
$res = HLSDownload::go($opts[1], [
    'no_ts' => (!isset($opts['thread']) or isset($opts['no-ts'])),
] + $settings);
if (isset($opts['thread']) or isset($opts['no-ts'])) exit($res ? 0 : 1);
$dir = $settings['dir'];
$thread = 0;
$threads = [];

// [-2, 0, 5] => [0, 1, 5]
function normalize_threads_positions(& $threads, $base = 0) {
    $pos = [];
    foreach ($threads as & $thread)
        if (isset($thread['pos'])) $pos[] = & $thread['pos'];
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

$depth = 0;
$echoCallback = function ($thread) use (& $threads, & $depth, $height, $width) {
    $prefix_format = "(#%s) ";
    return function ($chunk, $exit = false) use ($thread, & $threads, $prefix_format, & $depth, $height, $width) {
        $echo_prefix = sprintf($prefix_format, $thread);
        $buffer = & $threads[$thread]['buffer'];
        $buffer .= $chunk;
        $trunc = function ($s) use ($width) {
            return str_truncate($s, $width, $center = false, $replacer = '..');
        };
        // echo mt_rand() . "\n";
        // $depth += 1;
        // normalize_threads_positions($threads, $depth - $height + 0);
        $lines = preg_split('/(\\n+)/', $buffer, -1, PREG_SPLIT_DELIM_CAPTURE);
        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            $prev = (isset($lines[$i - 1]) ? $lines[$i - 1] : '');
            if (!$line or $line[0] != "\n") continue;
            if ($prev and $prev[0] === "\r") {
                $pos = isset($threads[$thread]['pos']) ? $threads[$thread]['pos'] : $depth;
                if (!isset($threads[$thread]['pos'])) {
                    echo "\n";
                    $depth += 1;
                    $threads[$thread]['pos'] = $pos;
                    normalize_threads_positions($threads, $depth - $height + 0);
                }
                $msg = $trunc($echo_prefix . substr($prev, 1));
                $threads[$thread]['status'] = "\033[s\033[%moveup%A\033[2K\r" . $msg . "\033[u";
                $threads[$thread]['end'] = (strlen($line) > 1);
            } else {
                echo $trunc($echo_prefix . $prev) . "\n";
                $depth += 1;
                normalize_threads_positions($threads, $depth - $height + 0);
            }
        }
        if (count($lines) > 1) {
            $len = array_sum(array_map('strlen', array_slice($lines, 0, -1)));
            $buffer = substr($buffer, $len);
        }
        if ($exit) {
            echo $trunc($buffer ? ($echo_prefix . $buffer) : '');
            $buffer = '';
        }
        foreach ($threads as & $t) {
            if (!isset($t['status'])) continue;
            echo str_replace('%moveup%', $depth - $t['pos'], $t['status']);
            if ($t['end']) {
                unset($t['pos']);
                unset($t['status']);
            }
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
    if ($progress) $argv_[] = '-progress-extra-n';
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

    -F, --filters <filter>    Filter M3U8 streams, ex: bandwidth=max
    -d, --directory <dir>     Target directory
    --limit-rate <speed>      Limit download speed (can use K, M, G)
    --no-decrypt              Turn off decryption
    --no-ts                   Download just manifests
";
$ob = ob_get_clean();
fwrite(STDERR, $ob);

exit(1);


// 1
// 2
// 3
// 4
// 5

// 5
// 3
// 1
// 4
// 2

// (Thread #5): http://hlsdownload.dev/case1/v2/one.ts ~ 20% moveup=42, pos=173, thread=2
// (Thread #3): http://hlsdownload.dev/case1/v2/one.ts ~ 20% moveup=41, pos=174, thread=2
// (Thread #1): http://hlsdownload.dev/case1/v1/one.ts ~ 20% moveup=38, pos=175, thread=1
// (Thread #1): http://hlsdownload.dev/case1/v1/one.ts ~ 20% moveup=38, pos=176, thread=4
// (Thread #1): http://hlsdownload.dev/case1/v1/one.ts ~ 20% moveup=38, pos=177, thread=2
// (Thread #4): http://hlsdownload.dev/case1/v2/one.ts ~ 20% moveup=37, pos=178, thread=2
// (Thread #2): http://hlsdownload.dev/case1/v2/one.ts ~ 20% moveup=36, pos=179, thread=2