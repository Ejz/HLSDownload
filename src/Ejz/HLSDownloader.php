<?php

namespace Ejz;

class HLSDownloader {
    public static function go($url, $path = null, $target = null) {
        $path = $path ? $path : rtrim(`mktemp -d`);
        if(!$target) {
            $ext = file_get_ext($url);
            $name = file_get_name($url);
            $ext = $ext ? ('.' . $ext) : '';
            if(!$name) return false;
            $target = "{$path}/{$name}{$ext}";
        } else $target = "{$path}/{$target}";
        // trigger_error("Save '{$url}' --> '{$target}'", E_USER_NOTICE);
        $content = curl($url, array(
            CURLOPT_CONNECTTIMEOUT => 0,
            CURLOPT_TIMEOUT => 400
        ));
        file_put_contents($target, $content);
        if(strpos($content, '#EXTM3U') !== 0) return true;
        $lines = nsplit($content);
        $lines = array_unique($lines);
        $replaceFrom = array();
        $replaceTo = array();
        foreach($lines as $line) {
            if(strpos($line, '#') === 0) continue;
            if(host($line)) return false; // TODO: HANDLE THIS CASE
            $_line = str_replace('/', '-', $line);
            $_line = preg_replace('/[\.-]{2,}/', '', $_line);
            if($_line != $line) {
                $replaceFrom[] = $line;
                $replaceTo[] = $_line;
            }
            self::go(dirname($url) . '/' . $line, $path, $_line);
        }
        if($replaceFrom and $replaceTo) {
            $content = str_replace($replaceFrom, $replaceTo, $content);
            file_put_contents($target, $content);
        }
        return $path;
    }
}
