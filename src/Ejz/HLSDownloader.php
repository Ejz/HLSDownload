<?php

namespace Ejz;

class HLSDownloader {
    public static function go($url) {
        $dir = rtrim(`mktemp -d`);
        if(self::goBackend($url, $dir)) return $dir;
        return;
    }
    private static function goBackend($url, $dir) {
        $target = toStorage($url, [
            'dir' => $dir, 'subdir' => ''
        ]);
        $content = file_get_contents($dir . '/' . $target);
        if(!$content) return false;
        $is_ts = strpos($content, '#EXTM3U') !== 0;
        $ext = $is_ts ? 'ts' : 'm3u8';
        if(file_get_ext($target) != $ext) {
            $_ = preg_replace('~\.\w+$~', ".{$ext}", $target);
            rename($dir . '/' . $target, $dir . '/' . $_);
            $target = $_;
        }
        if($is_ts) return $target;
        $collect = array();
        foreach(array_values(array_unique(nsplit($content))) as $line) {
            if(strpos($line, '#') === 0) {
                $collect[] = $line;
                continue;
            }
            $tmp = realurl($line, $url);
            $_ = self::goBackend($tmp, $dir);
            if(!$_) {
                _warn(__CLASS__ . '::' . __FUNCTION__, "INVALID URL: '{$tmp}'");
                continue;
            }
            $collect[] = preg_replace('~^/~', '', $_);
        }
        file_put_contents($dir . '/' . $target, implode("\n", $collect) . "\n");
        return $target;
    }
}
