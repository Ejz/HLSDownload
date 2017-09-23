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
if (isset($opts['progress'])) $settings['progress'] = $opts['progress'];
$settings['decrypt'] = !isset($opts['no-decrypt']);
$settings['no_ts'] = isset($opts['no-ts']);
$settings['progress_extra_n'] = isset($opts['progress-extra-n']);
if ($settings['no_ts']) {
    $res = HLSDownload::go($opts[1], $settings);
    exit($res ? 0 : 1);
}
$dir = $settings['dir'];
$res = HLSDownload::go($opts[1], [
    'no_ts' => !isset($opts['thread']),
] + $settings);
if (isset($opts['thread'])) exit($res ? 0 : 1);
$thread = 0;
$threads = [];

// [-2, 0, 5] => [0, 1, 5]
function normalize_positions(& $positions, $base = 0) {
    $min = min($positions);
    if ($min >= $base) return;
    foreach ($positions as & $position) $position += ($base - $min);
    foreach (array_keys($positions) as $key) {
        $position = & $positions[$key];
        for ($i = $position - ($base - $min); $i < $position; $i++)
            if ($i >= $base and array_search($i, $positions, true) === false) {
                $position = $i;
                break;
            }
    }
}

// $pos = 
// $pos = [-2, 1, 5, -1]; // 0, 1, 5
// normalize_positions($pos);
// var_dump($pos);
// exit();
// $pid = posix_getpid();
// var_dump($pid);
// sleep(1000);
// $wh = trim(shell_exec('echo -ne \'\\033[18t\' && IFS=\';\' read -n999 -dt -t1 -s csi h w && echo "${w}x${h}"'));
// list($w, $h) = explode('x', $wh);
$cols = intval(`tput cols`);
$lines = intval(`tput lines`);
$depth = 0;
$echoCallback = function ($thread) use (& $threads, & $depth) {
    $prefix_format = "(Thread #%s): ";
    return function ($chunk, $exit = false) use ($thread, & $threads, $prefix_format, & $depth) {
        $echo_prefix = sprintf($prefix_format, $thread);
        $buffer = & $threads[$thread]['buffer'];
        $buffer .= $chunk;
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
                }
                echo "\033[s";
                if ($_ = ($depth - $pos)) echo "\033[{$_}A";
                echo "\033[2K\r" . $echo_prefix . substr($prev, 1);
                echo "\033[u";
                if (strlen($line) > 1) unset($threads[$thread]['pos']);
            } else {
                echo $echo_prefix . $prev . "\n";
                $depth += 1;
            }
        }
        if (count($lines) > 1) {
            $len = array_sum(array_map('strlen', array_slice($lines, 0, -1)));
            $buffer = substr($buffer, $len);
        }
        if ($exit) {
            echo ($buffer ? ($echo_prefix . $buffer) : '');
            $buffer = '';
        }
    };
};
$loop = React\EventLoop\Factory::create();
foreach (scandir($dir) as $elem) {
    if ($elem == '..' or $elem == '.') continue;
    if (strpos($elem, 'stream') === false) continue;
    if (!is_dir($dir . '/' . $elem)) continue;
    $thread += 1;
    $argv_ = $argv;
    $argv_[] = "-d";
    $argv_[] = $dir . '/' . $elem;
    $argv_[] = "-thread";
    $argv_[] = $thread;
    $argv_[] = '-progress';
    $argv_[] = '-progress-extra-n';
    $argv_[array_search($opts[1], $argv_, true)] = $dir . '/' . $elem . '/' . $elem . '.m3u8';
    $exec = array_map('escapeshellarg', $argv_);
    $exec = implode(' ', $exec);
    $process = new React\ChildProcess\Process($exec);
    $process->start($loop);
    $threads[$thread] = ['buffer' => ''];
    $callback = $echoCallback($thread);
    $process->stdout->on('data', $callback);
    $process->stderr->on('data', $callback);
    $process->on('exit', function($code, $term) use ($callback) {
        $callback("Exited with code " . $code . "\n", $exit = true);
    });
    $callback("Started!" . "\n");

    // unset($pipes);
    // $handler = proc_open($exec, $descriptors, $pipes, null, null);
    // if (is_resource($handler)) {
    //     fclose($pipes[0]);
    //     stream_set_blocking($pipes[1], 0);
    //     stream_set_blocking($pipes[2], 0);
    //     stream_set_read_buffer($pipes[1], 0);
    //     stream_set_read_buffer($pipes[2], 0);
    //     $_ = [];
    //     $_['handler'] = $handler;
    //     $_['pipes'] = & $pipes;
    //     $_ = proc_get_status($_['handler']) + $_;
    //     $_['echo_prefix'] = $echo_prefix;
    //     $processes[] = $_;
    //     echo $echo_prefix . "Started with PID {$_['pid']}" . "\n";
    // } else {
    //     echo $echo_prefix . "Failed to run!!" . "\n";
    // }
    // var_dump($process);
    // $processes

    // echo $exec, "\n";
    // $handlers[] = $handler = popen($exec . ' < /dev/null &', 'r');
    // if ($handler) echo sprintf($prefix_format, $thread) . "Started!" . "\n";

    // $descriptorspec = array(
    //     0 => array("pipe", "r"),  // stdin - канал, из которого дочерний процесс будет читать
    //     1 => array("pipe", "w"),  // stdout - канал, в который дочерний процесс будет записывать 
    //     2 => array("file", "/tmp/error-output.txt", "a") // stderr - файл для записи
    // );


    // proc_open ( string $cmd , array $descriptorspec , array &$pipes [, string $cwd [, array $env [, array $other_options ]]] )

    // $process = new React\ChildProcess\Process($exec);
    // $process->start($loop);
    // $process->stdout->on('data', function ($chunk) {
    //     echo $chunk;
    // });
    // $process->on('exit', function($code, $term) use ($thread, $prefix_format) {
    //     echo sprintf($prefix_format, $thread) . "Exited with code " . $code . "\n";
    // });
    // echo sprintf($prefix_format, $thread) . "Initiated" . "\n";
    // periodically send something to stream

    // $exec = 'nohup ' . $exec . ' &';
    // echo $exec . "\n";
    // // passthru("{ sleep 1; echo 1; } &");
    // passthru($exec, $err);
}
// while ($processes) {
//     usleep(0.2 * 1000000);
//     for ($i = 0; $i < count($processes); $i++) {
//         $process = $processes[$i];
//         $pipes = $process['pipes'];
//         $r = [$pipes[1], $pipes[2]];
//         var_dump($r);
//         $w = $e = null;
//         $i = @ stream_select($r, $w, $e, 0, 0.2 * 1000000);
//         var_dump($i);
//         if ($i and is_int($i)) {
//             foreach ($r as $_) {
//                 $data = stream_get_contents($_);
//                 var_dump($data);
//                 if ($data !== '') fclose($_);
//             }
//         }
//         // var_dump($i);
//         $process['status'] = proc_get_status($process['handler']);
//         if ($process['status']['exitcode'] !== -1) {
//             echo $process['echo_prefix'] . 'Exited with code ' . $process['status']['exitcode'] . "\n";
//             array_splice($processes, $i, 1, array());
//             $i -= 1;
//         }
//     }
// }
// foreach ($processes as $process) {
//     var_dump(proc_get_status($process['process']));
// }
// sleep(5);
// foreach ($processes as $process) {
//     var_dump(proc_get_status($process['process']));
// }
// foreach ($handlers as $handler) pclose($handler);
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
