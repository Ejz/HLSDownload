#!/usr/bin/env php
<?php

define('ROOT', __DIR__);
require(ROOT . '/../vendor/autoload.php');

use Ejz\HLSDownload;

if (version_compare('5.5.0', PHP_VERSION, '>'))
    _log("MINIMUM REQUIRED PHP VERSION IS 5.5!", E_USER_ERROR);

$opts = getopts(array('d' => true, 'F' => true, 'progress' => false, 'limit-rate' => true));

if ($opts === array()) goto help;
if (!isset($opts[1]) or !host($opts[1])) goto help;

$settings = array();
if (isset($opts['d'])) $settings['dir'] = $opts['d'];
if (isset($opts['F'])) $settings['filter'] = $opts['F'];
if (isset($opts['progress']) and $opts['progress'])
    $settings['progress'] = function ($url, $percent) {
        fwrite(STDERR, "\r{$url}: {$percent}%" . ($percent == 100 ? "\n" : ""));
    };
if (isset($opts['limit-rate']) and $opts['limit-rate'])
    $settings['limitRate'] = $opts['limit-rate'];
if (HLSDownload::go($opts[1], $settings))
    exit(0);
exit(1);

help:

ob_start();
echo "Usage: hlsdownload [-d DIR] [-F FILTER] URL\n";
$ob = ob_get_clean();
fwrite(STDERR, $ob);

exit(1);
