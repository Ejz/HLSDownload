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

if (getenv("TERM")) {
    $width = intval(`tput cols`);
    $height = intval(`tput lines`);
}

$def = (defined('STDOUT') and posix_isatty(STDOUT) and !empty($width) and !empty($height));
if (empty($width)) $width = 80;
if (empty($height)) $height = 25;
// $height -= 1; // one line is kept for empty line
$keys = array_keys($opts);
$ep = !empty($opts['progress']);
$dp = !empty($opts['no-progress']);
$progress = (
    ($def and !$ep and !$dp) or
    ($ep and !$dp) or
    ($ep and $dp and (array_search('progress', $keys, true) > array_search('no-progress', $keys, true)))
);
// $h = $height + 1;
// if ($progress) echo "Terminal size: {$width}x{$h}\n";
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

function tty_make_bold($string, $start = '(', $end = ')') {
    $s = preg_quote($start, '~');
    $e = preg_quote($end, '~');
    return preg_replace("~({$s}.*?{$e})~", "\033[7m$1\033[0m", $string);
}

$depth = 0;
$echoCallback = function ($thread) use (
        & $threads, & $depth, $height, $width, $prefix_format
    ) {
    return function ($chunk, $exit = false) use (
        $thread, & $threads, $prefix_format, & $depth, $height, $width, $prefix_format
    ) {
        $echo_prefix = sprintf($prefix_format, $thread);
        $buffer = & $threads[$thread]['buffer'];
        $buffer .= $chunk;
        // echo mt_rand() . "\n";
        // $depth += 1;
        // normalize_threads_positions($threads, $depth - $height + 1);
        $lines = preg_split('/(\n|\e\[u)/', $buffer, -1, PREG_SPLIT_DELIM_CAPTURE);
        $lines['thread'] = $thread . '';
        file_put_contents('/tmp/lines', print_r(array_map('json_encode', $lines), 1), FILE_APPEND);
        unset($lines['thread']);
        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            $prev = (isset($lines[$i - 1]) ? $lines[$i - 1] : '');
            if (!$line) continue;
            if ($line == "\e[u") {
                $pos = isset($threads[$thread]['pos']) ? $threads[$thread]['pos'] : $depth - 1;
                if (!isset($threads[$thread]['pos'])) $threads[$thread]['pos'] = $pos;
                preg_match('!\r(\S+) ~ (\S+) \(!', $prev, $match);
                $url = $match[1];
                $percent = $match[2];
                $end = (intval($percent) == 100);
                $msg = str_replace("\033[1A", "\033[%moveup%A", $prev . $line);
                $_ = $end ? $echo_prefix : tty_make_bold($echo_prefix);
                $msg = str_replace("\r", "\r" . $_, $msg);
                if (isset($threads[$thread]['url']) and $url != $threads[$thread]['url']) {
                    $end = true;
                    $threads[$thread]['pos'] = $threads[$thread]['newpos'] = $depth - 1;
                }
                $threads[$thread]['msg'] = $msg;
                $threads[$thread]['end'] = $end;
                $threads[$thread]['url'] = $url;
            }
            if ($line[0] == "\n") {
                echo $echo_prefix . $prev . "\n";
                file_put_contents('/tmp/lines', $echo_prefix . $prev . "\n", FILE_APPEND);
                $depth += 1 + intval(strlen($echo_prefix . $prev) / $width);
                normalize_threads_positions($threads, $depth - $height + 1);
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
        foreach ($threads as & $t) {
            if (!isset($t['msg'])) continue;
            echo str_replace('%moveup%', $depth - $t['pos'], $t['msg']);
            file_put_contents('/tmp/lines', str_replace('%moveup%', $depth - $t['pos'], $t['msg']) . "\n", FILE_APPEND);
            if ($t['end']) {
                unset($t['pos']);
                unset($t['msg']);
                unset($t['url']);
            }
            if (isset($t['newpos'])) {
                $t['pos'] = $t['newpos'];
                unset($t['newpos']);
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