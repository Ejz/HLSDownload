#!/usr/bin/env php
<?php

define('ROOT', __DIR__);
require(ROOT . '/../vendor/autoload.php');

use Ejz\HLSDownload;

if (version_compare('5.5.0', PHP_VERSION, '>'))
    _log("MINIMUM REQUIRED PHP VERSION IS 5.5!", E_USER_ERROR);

if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
    _log("WINDOWS IS NOT SUPPORTED!", E_USER_ERROR);

$opts = getopts(array(
    'd' => true, 'F' => true,
    'progress' => false,
    'decrypt' => false, 'no-decrypt' => false,
    'limit-rate' => true, 'continue' => false
));

if ($opts === array()) goto help;
if (!isset($opts[1]) or !(host($opts[1]) or is_file($opts[1]))) goto help;

$settings = array();
if (isset($opts['d'])) $settings['dir'] = $opts['d'];
if (isset($opts['F'])) $settings['filter'] = $opts['F'];
if (isset($opts['progress']) and $opts['progress'])
    $settings['progress'] = function ($url, $percent) {
        fwrite(STDERR, "\r{$url}: {$percent}%" . ($percent == 100 ? "\n" : ""));
    };
if (isset($opts['limit-rate']) and $opts['limit-rate'])
    $settings['limitRate'] = $opts['limit-rate'];
if (isset($opts['continue']) and $opts['continue'])
    $settings['continue'] = $opts['continue'];
if (
    isset($opts['decrypt']) and $opts['decrypt']
    and isset($opts['no-decrypt']) and $opts['no-decrypt']
) {
    fwrite(STDERR, "Error: used --decrypt with --no-decrypt\n");
    exit(1);
}
$settings['decrypt'] = !(isset($opts['no-decrypt']) and $opts['no-decrypt']);
if (HLSDownload::go($opts[1], $settings))
    exit(0);
exit(1);

help:

ob_start();
echo "HLSDownload 1.4 by Ejz Cernisev.

Usage: hlsdownload [options] <M3U8>

Options:
  -F <filter>          Filter M3U8 streams, ex: bandwidth=max
  -d <dir>             Target directory
  --limit-rate <speed> Limit download speed (can use K, M, G)
  --progress           Show progress
  --decrypt            Decrypt every chunk
  --no-decrypt         Turn off decryption
  --continue           Continue download (in case of disconnect)
";
$ob = ob_get_clean();
fwrite(STDERR, $ob);

exit(1);
