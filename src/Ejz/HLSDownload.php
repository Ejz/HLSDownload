<?php

namespace Ejz;

class HLSDownload {
    const UA = "HLSDownload (github.com/Ejz/HLSDownload)";
    const CACHE = "cache";
    public static function go($link, $settings = []) {
        $settings = $settings + [
            'dir' => '.',
            'ua' => self::UA,
            'cache' => self::CACHE,
            'tmp' => sys_get_temp_dir(),
            'decrypt' => true,
            'progress' => false,
            'key' => null,
            'clear_cache' => true,
            'filter' => null,
        ];
        $dir = & $settings['dir'];
        if (!is_dir($dir)) @ mkdir($dir, $mode = 0755, $recursive = true);
        if (!is_dir($dir) or !is_writable($dir))
            return _warn(__CLASS__ . ": Invalid directory: {$dir}");
        $settings['cache'] = '/' . trim($settings['cache'], '/');
        $dir = rtrim($dir, '/');
        return self::backend($link, $settings);
    }
    // private static function getProgressClosure($progress) {
    //     return function ($ch, $total, $download) use ($progress) {
    //         static $last = null;
    //         static $done = false;
    //         if (!$total) return;
    //         $percent = round((100 * $download) / $total);
    //         if ($percent < 0) $percent = 0;
    //         if ($percent > 100) $percent = 100;
    //         if (!is_null($last) and round(microtime(true) - $last, 1) < 0.5 and $percent != 100) return;
    //         $last = microtime(true);
    //         $info = curl_getinfo($ch);
    //         $url = $info['url'];
    //         if ($done) return;
    //         if ($percent == 100) $done = true;
    //         $progress($url, $percent);
    //     };
    // }
    private static function backend($link, $settings) {
        $link = (is_file($link) ? $link : realurl($link));
        $dir = & $settings['dir'];
        @ $manifest_name = $settings['manifest_name'];
        if (!$manifest_name) $manifest_name = 'manifest.m3u8';
        @ $stream_name = $settings['stream_name'];
        if (!$stream_name) $stream_name = 'stream.m3u8';
        @ $ts_name = $settings['ts_name'];
        if (!$ts_name) $ts_name = 'chunk.ts';
        // $tmp = $settings['tmp'];
        // $tmp_prefix = $settings['tmp_prefix'];
        // $tmp =  sprintf("%s/%s", $settings['tmp'], $settings['tmp_prefix']);
        // if (!is_dir($tmp)) mkdir($tmp);
        // if (!is_dir($tmp)) _err("INVALID TMP DIR!");
        // $stream = $settings['stream'];
        // $tsname = $settings['tsname'];
        // $realurl = function ($link) use ($url) {
        //     if (is_file($url) and !host($link))
        //         if (strpos($url, '/') === false) return $link;
        //         else return preg_replace('~/[^/]+$~', '/', $url) . $link;
        //     elseif (host($link) or host($url))
        //         return realurl($link, $url);
        //     return '';
        // };
        $curl_settings = [
            CURLOPT_USERAGENT => $settings['ua'],
            CURLOPT_TIMEOUT => 120,
        ];
        if ($settings['progress']) {
            $curl_settings[CURLOPT_NOPROGRESS] = false;
            // $curl_settings[CURLOPT_PROGRESSFUNCTION] = self::getProgressClosure($settings['progress']);
        }
        // if ($settings['headers'] and is_array($settings['headers'])) {
        //     $curl_settings[CURLOPT_HTTPHEADER] = $settings['headers'];
        // }
        if (preg_match('~^(\d+)\s*(k|m|g)$~i', @ $settings['limit_rate'], $match)) {
            if (defined('CURLOPT_MAX_RECV_SPEED_LARGE')) {
                $m = array('k' => pow(2, 10), 'm' => pow(2, 20), 'g' => pow(2, 30));
                $curl_settings[CURLOPT_MAX_RECV_SPEED_LARGE] = intval($match[1]) * $m[strtolower($match[2])];
            } else return _log(__CLASS__ . ": CURLOPT_MAX_RECV_SPEED_LARGE is not defined!");
        }
        $cache = $dir . $settings['cache'];
        @ mkdir($cache, $mode = 0755, $recursive = true);
        $getter = function ($link) use ($curl_settings, $cache) {
            if (!host($link) and is_file($link))
                return file_get_contents($link);
            $file = $cache . '/' . md5($link);
            if (!is_file($file)) {
                $content = curl($link, $curl_settings);
                if (!$content) return _warn("Error while getting {$link}");
                file_put_contents($file, $content);
            }
            return file_get_contents($file);
        };
        // if ($settings['continue'] and $tsname) {
        //     $d = $dir . '/' . $tsname;
        //     if (file_exists($d) and filesize($d) > 0) {
        //         _log("{$url} -> {$d} (ALREADY)");
        //         return true;
        //     }
        // }
        $content = $getter($link);
        if (!$content) return;
        // either manifest or ts
        $is_manifest = (strpos($content, '#EXTM3U') === 0);
        $is_ts = !$is_manifest;
        if ($is_ts) {
            $target = $dir . '/' . $ts_name;
            echo "{$link} -> {$target}\n";
            $_ = file_put_contents($target, $content);
            if (!$settings['key'] or !$_) return $_ > 0;
            rename($target, $target = $target . '.tmp');
            $error = self::decrypt($target, $settings['key']);
            if ($error)
                return _warn("Error while decrypting {$link}; {$error}");
            return rename($target, substr($target, 0, -4));
        }
        $collect = [];
        $extinf = false;
        $ext_x_stream_inf = false;
        $filter = self::filter($settings['filter'], $content);
        $settings['key'] = null;
        $ts_count = -1;
        $stream_count = -1;
        foreach (nsplit($content) as $line) {
            // if (strpos($line, $str = '#EXT-X-KEY:') === 0) {
            //     $_line = substr($line, strlen($str));
            //     $extract = function ($key) use ($_line) {
            //         preg_match($r = '~(^|,)' . $key . '="([^"]*)"(,|$)~i', $_line, $match1);
            //         preg_match($r = '~(^|,)' . $key . '=(.*?)(,|$)~i', $_line, $match2);
            //         $match = $match1 ?: $match2;
            //         if (!$match) return null;
            //         $match = $match[2];
            //         $match = trim($match, '"');
            //         return $match;
            //     };
            //     $method = $extract('method');
            //     $uri = $_uri = $extract('uri');
            //     if ($uri and strpos($uri, $_ = 'data:text/plain;base64,') === 0)
            //         $uri = base64_decode(substr($uri, strlen($_)));
            //     elseif ($uri and ($_ = $realurl($uri))) {
            //         $uri = $curl($_);
            //     } else $uri = null;
            //     if ($settings['decrypt'] and strtolower($method) != 'none') {
            //         if (!$uri) {
            //             _warn("ERROR WHILE GETTING KEY: {$line}");
            //             return false;
            //         }
            //         $uri = bin2hex($uri);
            //         $settings['key'] = array(
            //             'method' => $method,
            //             'uri' => $uri,
            //             'iv' => $extract('iv'),
            //             'keyformat' => $extract('keyformat'),
            //         );
            //     } elseif ($settings['decrypt'] and strtolower($method) == 'none') {
            //         $settings['key'] = null;
            //     } elseif (!$settings['decrypt'] and strtolower($method) != 'none') {
            //         if (!$uri) {
            //             _warn("ERROR WHILE GETTING KEY: {$line}");
            //             return false;
            //         }
            //         if (!is_dir($d = $dir . '/keys')) mkdir($d);
            //         $i = 0;
            //         while (file_exists($file = $d . '/key' . $i) and md5_file($file) != md5($uri))
            //             $i++;
            //         _log("SAVE KEY: {$_uri} -> {$file}");
            //         file_put_contents($file, $uri);
            //         $collect[] = str_replace_once($extract('uri'), 'keys/key' . $i, $line);
            //     } else {
            //         $collect[] = $line;
            //     }
            //     continue;
            // }
            if (strpos($line, '#EXTINF:') === 0) {
                $collect[] = $line;
                $extinf = true;
                $ts_count += 1;
                continue;
            }
            if (strpos($line, '#EXT-X-STREAM-INF:') === 0) {
                $ext_x_stream_inf = true;
                $stream_count += 1;
                if (is_null($filter) or in_array($stream_count, $filter, true))
                    $collect[] = $line;
                continue;
            }
            if (strpos($line, '#') === 0) {
                $collect[] = $line;
                $extinf = false;
                $ext_x_stream_inf = false;
                continue;
            }
            if ($extinf) {
                // if (!is_file($link))
                $line = realurl($line, $link);
                $ts_name = sprintf("chunk%05s.ts", $ts_count);
                $return = self::backend(
                    $line, ['ts_name' => $ts_name] + $settings
                );
                $collect[] = $ts_name;
                $extinf = false;
                continue;
            }
            if ($ext_x_stream_inf) {
                if (is_null($filter) or in_array($stream_count, $filter)) {
                    $line = realurl($line, $link);
                    $stream_name = sprintf("stream%s/stream%s.m3u8", $stream_count, $stream_count);
                    $new_dir = $dir . '/' . dirname($stream_name);
                    if (!is_dir($new_dir)) @ mkdir($new_dir, $mode = 0755, $recursive = true);
                    $return = self::backend(
                        $line, [
                            'stream_name' => basename($stream_name),
                            'dir' => $new_dir,
                        ] + $settings
                    );
                    $collect[] = $stream_name;
                }
                $ext_x_stream_inf = false;
                continue;
            }
            _warn("Parse error in {$link}; Line: {$line}");
        }
        if ($extinf or $ext_x_stream_inf)
            _warn("Invalid M3U8 ending at {$link}");
        $collect = implode("\n", $collect) . "\n";
        $is_master = (strpos($collect, "\n#EXT-X-STREAM-INF:") !== false);
        $target = $dir . '/' . ($is_master ? $manifest_name : $stream_name);
        echo "{$link} -> {$target}\n";
        if ($settings['clear_cache']) {
            $files = scandir($cache);
            foreach ($files as $file) {
                if ($file === '.' or $file === '..') continue;
                unlink($cache . '/' . $file);
            }
            rmdir($cache);
        }
        return file_put_contents($target, $collect) > 0;
    }
    private static function decrypt($chunk, $key) {
        @ $method = $key['method'];
        @ $uri = $key['uri'];
        @ $iv = $key['iv'];
        $methods = array("AES-128");
        if (!in_array(strtolower($method), array_map('strtolower', $methods)))
            return 'Unknown encryption method!';
        if (!is_file($chunk)) return 'Chunk file is NOT found!';
        exec('which openssl', $output, $return);
        if ($return != '0') return "openssl is NOT found!";
        if (!$uri) return "Invalid URI!";
        if (!$iv) return "Invalid IV!";
        $tmp = rtrim(`mktemp`);
        shell_exec($_ = sprintf(
            "openssl aes-128-cbc -d -in %s -out %s -p -nosalt -iv %s -K %s",
            escapeshellarg($chunk),
            escapeshellarg($tmp),
            preg_replace('~^0x~', '', $iv),
            escapeshellarg($uri)
        ));
        $mpeg = file_get_contents($tmp);
        if ($mpeg and $mpeg[0] === "G") { // sync flag
            unlink($chunk);
            rename($tmp, $chunk);
            return '';
        } else {
            unlink($tmp);
            return 'Error while decoding!';
        }
    }
    private static function filter($filter, $content) {
        if (is_null($filter)) return $filter;
        if (is_string($filter . '')) $filter = explode(',', $filter . '');
        $streams = array();
        $lines = nsplit($content);
        for ($i = 0; $i < count($lines) - 1; $i++) {
            $line = $lines[$i];
            // var_dump($line);
            if (strpos($line, $_ = '#EXT-X-STREAM-INF:') !== 0) continue;
            $line = substr($line, strlen($_));
            $keys = explode(',', $line);
            $info = array();
            foreach ($keys as $key) {
                $key = explode('=', $key, 2);
                if (count($key) != 2) continue;
                list($key, $value) = $key;
                $key = trim(strtolower($key));
                $value = trim($value, ' "\'');
                $info[$key] = $value;
            }
            $streams[] = $info;
        }
        // var_dump($streams);
        if (!$streams) return null;
        $return = array();
        $search = function ($value, $sort, $negate, $op) {
            $col = [];
            foreach ($sort as $k => $v) {
                if (
                    ($op == '=' and (fnmatch($value, $v) xor $negate))
                        or
                    ($op != '=' and (version_compare($v, $value, $op) xor $negate))
                ) $col[] = $k;
            }
            return $col;
        };
        foreach ((array)($filter) as $f) {
            if (is_numeric($f)) {
                $f = intval($f);
                $return[] = ($f < 0 ? count($streams) : 0) + $f;
                continue;
            }
            preg_match("~([a-zA-Z0-9_-]+)(\!?)(=|<=?|>=?)(.*)~", $f, $m);
            $f = strtolower($m[1]);
            $sort = array_column($streams, $f);
            $negate = !!$m[2];
            $operation = $m[3];
            $value = $m[4];
            $v = strtolower($value);
            if ($v === 'min' or $v === 'max') {
                $f = ($v === 'min' ? 'asort' : 'arsort');
                $f($sort);
                list($value) = array_values($sort);
            }
            $res = $search($value, $sort, $negate, $operation);
            $return = array_merge($return, $res);
        }
        return array_unique($return);
    }
}
