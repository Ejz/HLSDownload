<?php

namespace Ejz;

class HLSDownloader {
    private static function _($url, $path = null) {
        $path = $path ? $path : trim(exec("mktemp -d"));
        $ext = file_get_ext($url);
        $name = file_get_name($url);
        $ext = $ext ? ('.' . $ext) : '';
        $target = "{path}/{$name}{$ext}";
        $content = curl($url, array(
            CURLOPT_CONNECTTIMEOUT => 0,
            CURLOPT_TIMEOUT => 400
        ));
        file_put_contents($target, $content);
    }
    public static function download1($prefix, $m3u8, $dir = null) {
        if(is_null($dir)) $dir = TMP_ROOT . '/hls-' . mt_rand();
        if(!is_dir($dir)) exec("mkdir -p '{$dir}'");
        // curl signature has changed
        // $content = curl($_ = $prefix . $m3u8, null, 3000);
        //
        $ext = file_get_ext($prefix . $m3u8);
        $name = file_get_name($prefix . $m3u8);
        $ext = $ext ? '.' . $ext : '';
        $target = "{$dir}/{$name}{$ext}";
        echo "Saving to '$_' to '{$target}'", chr(10);
        file_put_contents($target, $content);
        //
        if(strpos($content, '#EXTM3U') !== 0) return;
        $lines = nsplit($content);
        $lines = array_unique($lines);
        foreach($lines as $line) {
            if(strpos($line, '#') === 0) continue;
            if(is_numeric(strpos($line, '/'))) { // "one/two/link.m3u8"
                preg_match('~^(.*)/([^/]*?)$~', $line, $match);
                $append = $match[1]; // "one/two"
                $line = $match[2];
                $_prefix = $prefix . $append . '/';
            } else {
                $_prefix = $prefix;
                $append = '';
            }
            self::download($_prefix, $line, $dir . ($append ? '/' . $append : ''));
        }
    }
}
