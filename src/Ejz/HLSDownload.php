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
            'filters' => [],
        ];
        $dir = & $settings['dir'];
        if (!is_dir($dir)) @ mkdir($dir, $mode = 0755, $recursive = true);
        if (!is_dir($dir) or !is_writable($dir))
            return _warn(__CLASS__ . ": Invalid directory: {$dir}");
        if (!is_array($settings['filters']))
            $settings['filters'] = [$settings['filters']];
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
            'checker' => [200, 201, 202],
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
        $filter = null;
        $filters = $settings['filters'];
        for ($i = 0; $i < count($filters); $i++) {
            if ($filters[$i] === 'audio') {
                array_splice($filters, $i, 1, ['codecs=*mp4a*,!resolution', 'codecs!=*avc1*']);
            } elseif ($filters[$i] === 'video') {
                array_splice($filters, $i, 1, ['codecs=*avc1*,resolution']);
            }
        }
        $col = [];
        for ($i = 0; $i < count($filters); $i++) {
            $col[] = self::filter($filters[$i], $content);
        }
        if ($filters and count($col) > 1)
            $filter = call_user_func_array('array_intersect', $col);
        elseif ($filters) $filter = $col[0];
        $settings['key'] = null;
        $ts_count = -1;
        $stream_count = -1;
        foreach (nsplit($content) as $line) {
            if (strpos($line, $str = '#EXT-X-MEDIA-SEQUENCE:') === 0) {
                $collect[] = $line;
                $line = substr($line, strlen($str));
                $ts_count += intval($line);
                continue;
            }
            if (strpos($line, $str = '#EXT-X-KEY:') === 0) {
                $_line = $line;
                $line = substr($line, strlen($str));
                $meta = self::extractMeta($line);
                @ $method = strtolower($meta['method']);
                @ $uri = $meta['uri'];
                $_uri = '';
                $base64 = (strpos($uri, $_ = 'data:text/plain;base64,') === 0);
                if ($uri and $base64) {
                    $uri = base64_decode(substr($uri, strlen($_)));
                } elseif ($uri and ($_ = realurl($uri, $link))) {
                    $_uri = $_;
                    $uri = $getter($_);
                } else $uri = null;
                $err = "Error getting key from {$_line}";
                if ($settings['decrypt'] and $method != 'none') {
                    if (!$uri) return _warn($err);
                    $uri = bin2hex($uri);
                    @ $iv = $meta['iv'];
                    if (!$iv) $iv = sprintf("0x%016s", dechex($ts_count + 1));
                    $settings['key'] = array(
                        'method' => $method,
                        'uri' => $uri,
                        'iv' => $iv,
                        'keyformat' => @ $meta['keyformat'],
                    );
                } elseif ($settings['decrypt'] and $method == 'none') {
                    $settings['key'] = null;
                } elseif (!$settings['decrypt'] and $method != 'none' and !$base64) {
                    if (!$uri) return _warn($err);
                    if (!is_dir($keys = $dir . '/keys'))
                        @ mkdir($keys, $mode = 0755, $recursive = true);
                    $i = 0;
                    while (file_exists($file = $keys . '/key' . $i) and md5_file($file) != md5($uri))
                        $i++;
                    echo "{$_uri} -> {$file}\n";
                    file_put_contents($file, $uri);
                    $collect[] = str_replace_once($meta['uri'], 'keys/key' . $i, $line);
                } else {
                    $collect[] = $line;
                }
                continue;
            }
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
    private static function extractMeta($line) {
        $add = '';
        $rules = [
            function (& $string, & $output) use (& $add) {
                preg_match('~^([a-zA-Z0-9_-]+)=\s*~', $string, $match);
                if (!$match) return;
                $string = substr($string, strlen($match[0]));
                $add = strtolower($match[1]);
                return true;
            },
            function (& $string, & $output) use (& $add) {
                $one = $string[0];
                if ($one != '"') return;
                $string = substr($string, 1);
                $pos = strpos($string, $one);
                if ($pos === false) {
                    if ($add) {
                        $output[$add] = $string;
                        $add = '';
                    }
                    $string = '';
                    return true;
                }
                $sub = substr($string, 0, $pos);
                if ($add) {
                    $output[$add] = $sub;
                    $add = '';
                }
                $string = substr($string, $pos + 1) . '';
                return true;
            },
            function (& $string, & $output) use (& $add) {
                preg_match('~^([^,]*),?\s*~', $string, $match);
                if (!$match) return;
                $string = substr($string, strlen($match[0]));
                if ($add) {
                    $output[$add] = $match[1];
                    $add = '';
                }
                return true;
            },
        ];
        return Lexer::go($line, ['rules' => $rules, 'implode' => null]);
    }
    private static function decrypt($chunk, $key) {
        @ $method = $key['method'];
        @ $uri = $key['uri'];
        @ $iv = $key['iv'];
        $methods = array("aes-128");
        if (!in_array($method, $methods))
            return "Unknown encryption method ({$method})!";
        if (!is_file($chunk)) return "Chunk file is NOT found ({$chunk})!";
        exec('which openssl', $output, $return);
        if ($return != '0') return "openssl binary is NOT found!";
        if (!$uri) return "Invalid Key content ({$chunk})!";
        if (!$iv) return "Invalid IV ({$chunk})!";
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
            return "Error while decoding ({$chunk})!";
        }
    }
    private static function filter($filter, $content) {
        if (is_null($filter)) return [];
        if (is_string($filter . '')) $filter = explode(',', $filter . '');
        $streams = array();
        $lines = nsplit($content);
        for ($i = 0; $i < count($lines) - 1; $i++) {
            $line = $lines[$i];
            if (strpos($line, $_ = '#EXT-X-STREAM-INF:') !== 0) continue;
            $line = substr($line, strlen($_));
            $meta = self::extractMeta($line);
            if ($meta) $streams[] = $meta;
        }
        if (!$streams) return [];
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
            $f = preg_replace('~^(\!?)([a-zA-Z0-9_-]+)$~', '$2$1=?*', $f);
            preg_match("~([a-zA-Z0-9_-]+)(\!?)(=|<=?|>=?)(.*)~", $f, $m);
            if (!$m) continue;
            $f = strtolower($m[1]);
            $sort = $streams;
            foreach ($sort as & $s) $s = isset($s[$f]) ? $s[$f] : '';
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
