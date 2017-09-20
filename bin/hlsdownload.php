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

$opts = getopts([
    'h' => false, 'help' => 'h',
    'd' => true, 'directory' => 'd',
    'F' => ['multiple' => true, 'value' => true], 'filters' => 'F',
    'no-ts' => false,
    'no-decrypt' => false,
    'limit-rate' => true,
]);

if (isset($opts['h'])) goto help;
if (!isset($opts[1]) or isset($opts[2])) goto help;

$settings = array();
if (isset($opts['d'])) $settings['dir'] = $opts['d'];
if (isset($opts['F'])) $settings['filters'] = $opts['F'];
if (isset($opts['limit-rate'])) $settings['limit_rate'] = $opts['limit-rate'];
$settings['decrypt'] = !isset($opts['no-decrypt']);
$settings['no-ts'] = isset($opts['no-ts']);
$res = HLSDownload::go($opts[1], $settings);
exit($res ? 0 : 1);

help:

ob_start();
$ver = VERSION;
$aut = AUTHOR;
echo "HLSDownload {$ver} by {$aut}.

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
