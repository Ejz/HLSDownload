<?php

namespace Ejz;

class HLSDownload {
    const UA = "HLSDownload (github.com/Ejz/HLSDownload)";
    const TMP_PREFIX = "HLSDownload";
    public static function go($url, $settings = array()) {
        $dir = isset($settings['dir']) ? $settings['dir'] : '.';
        $ua = isset($settings['ua']) ? $settings['ua'] : self::UA;
        $tmp = isset($settings['tmp']) ? $settings['tmp'] : sys_get_temp_dir();
        $tmp_prefix = isset($settings['tmp_prefix']) ? $settings['tmp_prefix'] : self::TMP_PREFIX;
        if (!is_dir($dir)) exec("mkdir -p " . escapeshellarg($dir));
        if (!is_dir($dir)) {
            _warn("INVALID DIR: {$dir}");
            return false;
        }
        if (!is_writable($dir)) {
            _warn("DIRECTORY IS NOT WRITABLE: {$dir}");
            return false;
        }
        $dir = rtrim($dir, '/');
        return self::goBackend((is_file($url) ? $url : realurl($url)), [
            'dir' => $dir,
            'ua' => $ua,
            'filter' => (isset($settings['filter']) ? $settings['filter'] : null),
            'decrypt' => (isset($settings['decrypt']) ? $settings['decrypt'] : true),
            'stream' => null,
            'ts' => null,
            'key' => null,
            'tmp' => $tmp,
            'tmp_prefix' => $tmp_prefix,
            'continue' => (isset($settings['continue']) ? $settings['continue'] : false),
            'progress' => ((isset($settings['progress']) and is_callable($settings['progress'])) ? $settings['progress'] : null),
            'limitRate' => (isset($settings['limitRate']) ? $settings['limitRate'] : null),
        ]);
    }
    private static function getProgressClosure($progress) {
        return function ($ch, $total, $download) use ($progress) {
            static $last = null;
            static $done = false;
            if (!$total) return;
            $percent = round((100 * $download) / $total);
            if ($percent < 0) $percent = 0;
            if ($percent > 100) $percent = 100;
            if (!is_null($last) and round(microtime(true) - $last, 1) < 0.5 and $percent != 100) return;
            $last = microtime(true);
            $info = curl_getinfo($ch);
            $url = $info['url'];
            if ($done) return;
            if ($percent == 100) $done = true;
            $progress($url, $percent);
        };
    }
    private static function goBackend($url, $settings) {
        $tmp = $settings['tmp'];
        $tmp_prefix = $settings['tmp_prefix'];
        $tmp =  sprintf("%s/%s", $settings['tmp'], $settings['tmp_prefix']);
        if (!is_dir($tmp)) mkdir($tmp);
        if (!is_dir($tmp)) _err("INVALID TMP DIR!");
        $dir = $settings['dir'];
        $stream = $settings['stream'];
        $ts = $settings['ts'];
        $realurl = function ($link) use ($url) {
            if (is_file($url) and !host($link))
                return preg_replace('~/[^/]+$~', '/', $url) . $link;
            elseif (host($link) or host($url))
                return realurl($link, $url);
            return '';
        };
        $curl_settings = array(
            CURLOPT_USERAGENT => $settings['ua'],
            CURLOPT_TIMEOUT => 120
        );
        if ($settings['progress']) {
            $curl_settings[CURLOPT_NOPROGRESS] = false;
            $curl_settings[CURLOPT_PROGRESSFUNCTION] = self::getProgressClosure($settings['progress']);
        }
        if ($settings['limitRate'] and preg_match('~^(\d+)\s*(k|m|g)$~i', $settings['limitRate'], $match)) {
            if (defined('CURLOPT_MAX_RECV_SPEED_LARGE')) {
                $m = array('k' => pow(2, 10), 'm' => pow(2, 20), 'g' => pow(2, 30));
                $curl_settings[CURLOPT_MAX_RECV_SPEED_LARGE] = intval($match[1]) * $m[strtolower($match[2])];
            } else {
                _log("CURLOPT_MAX_RECV_SPEED_LARGE IS NOT DEFINED!");
                return false;
            }
        }
        $curl = function ($link) use ($curl_settings, $tmp) {
            $err = "ERROR WHILE TRYING TO FETCH: {$link}";
            if (!host($link) and is_file($link)) {
                $content = file_get_contents($link);
                if (!$content) _err($err);
                return $content;
            }
            $file = $tmp . '/' . md5($link);
            if (!is_file($file)) {
                $content = curl($link, $curl_settings);
                if (!$content) _err($err);
                file_put_contents($file, $content);
            }
            return file_get_contents($file);
        };
        if ($settings['continue'] and !is_null($ts)) {
            $d = $dir . sprintf("/ts%05s.ts", $ts);
            if (file_exists($d) and filesize($d) > 0) {
                _log("{$url} -> {$d} (ALREADY)");
                return true;
            }
        }
        $content = $curl($url);
        if (strpos($content, '#EXTM3U') !== 0 and is_null($ts))
            return null;
        if (strpos($content, '#EXTM3U') !== 0) {
            $d = (is_null($ts) ? $dir . '/ts.ts' : $dir . sprintf("/ts%05s.ts", $ts));
            _log("{$url} -> {$d}");
            $_ = file_put_contents($d, $content);
            if (!$settings['key'] or !$_) return $_ > 0;
            rename($d, $dd = $d . '.tmp');
            $error = self::decryptChunk($dd, $settings['key']);
            if ($error) {
                _log("ERROR WHILE DECRYPTING: {$error}");
                return false;
            }
            rename($dd, $d);
            return true;
        }
        $collect = array();
        $extinf = false;
        $ext_x_stream_inf = false;
        $filter = self::prepareFilter($settings['filter'], $content);
        $settings['key'] = null;
        foreach (nsplit($content) as $line) {
            if (strpos($line, $str = '#EXT-X-KEY:') === 0) {
                $_line = substr($line, strlen($str));
                $extract = function ($key) use ($_line) {
                    preg_match($r = '~(^|,)' . $key . '="([^"]*)"(,|$)~i', $_line, $match1);
                    preg_match($r = '~(^|,)' . $key . '=(.*?)(,|$)~i', $_line, $match2);
                    $match = $match1 ?: $match2;
                    if (!$match) return null;
                    $match = $match[2];
                    $match = trim($match, '"');
                    return $match;
                };
                $method = $extract('method');
                $uri = $_uri = $extract('uri');
                if ($uri and strpos($uri, $_ = 'data:text/plain;base64,') === 0)
                    $uri = base64_decode(substr($uri, strlen($_)));
                elseif ($uri and ($_ = $realurl($uri))) {
                    $uri = $curl($_);
                } else $uri = null;
                if ($settings['decrypt'] and strtolower($method) != 'none') {
                    if (!$uri) {
                        _warn("ERROR WHILE GETTING KEY: {$line}");
                        return false;
                    }
                    $uri = bin2hex(trim($uri));
                    $settings['key'] = array(
                        'method' => $method,
                        'uri' => $uri,
                        'iv' => $extract('iv'),
                        'keyformat' => $extract('keyformat'),
                    );
                } elseif ($settings['decrypt'] and strtolower($method) == 'none') {
                    $settings['key'] = null;
                } elseif (!$settings['decrypt'] and strtolower($method) != 'none') {
                    if (!$uri) {
                        _warn("ERROR WHILE GETTING KEY: {$line}");
                        return false;
                    }
                    if (!is_dir($d = $dir . '/keys')) mkdir($d);
                    $i = 0;
                    while (file_exists($file = $d . '/key' . $i) and md5_file($file) != md5($uri))
                        $i++;
                    _log("SAVE KEY: {$_uri} -> {$file}");
                    file_put_contents($file, $uri);
                    $collect[] = str_replace_once($extract('uri'), 'keys/key' . $i, $line);
                } else {
                    $collect[] = $line;
                }
                continue;
            }
            if (strpos($line, '#EXTINF:') === 0) {
                $collect[] = $line;
                $extinf = true;
                $ts = is_null($ts) ? 0 : $ts + 1;
                continue;
            }
            if (strpos($line, '#EXT-X-STREAM-INF:') === 0) {
                $ext_x_stream_inf = true;
                $stream = is_null($stream) ? 0 : $stream + 1;
                if (is_null($filter) or in_array($stream, $filter)) {
                    $collect[] = $line;
                }
                continue;
            }
            if (strpos($line, '#') === 0) {
                $collect[] = $line;
                $extinf = false;
                $ext_x_stream_inf = false;
                continue;
            }
            if ($extinf) {
                $line = $realurl($line);
                $return = self::goBackend($line, ['ts' => $ts] + $settings);
                if (!$return) {
                    _warn("ERROR WHILE PROCESSING: {$line}");
                    return false;
                }
                $collect[] = sprintf("ts%05s.ts", $ts);
                $extinf = false;
            } elseif ($ext_x_stream_inf) {
                if (is_null($filter) or in_array($stream, $filter)) {
                    $line = $realurl($line);
                    if (!is_dir($d = $dir . '/stream' . $stream)) mkdir($d);
                    $return = self::goBackend($line, ['stream' => $stream, 'dir' => $d] + $settings);
                    if (!$return) {
                        _warn("ERROR WHILE PROCESSING: {$line}");
                        return false;
                    }
                    $collect[] = "stream{$stream}/stream{$stream}.m3u8";
                }
                $ext_x_stream_inf = false;
            } else {
                _warn("PARSE ERROR: {$url}");
                return false;
            }
        }
        $stream = $settings['stream'];
        $collect = implode("\n", $collect) . "\n";
        if (is_null($stream) and strpos($collect, "\n#EXT-X-STREAM-INF:") === false)
            $d = $dir . '/stream0.m3u8';
        elseif (is_null($stream))
            $d = $dir . '/hls.m3u8';
        else $d = $dir . "/stream{$stream}.m3u8";
        _log("{$url} -> {$d}");
        return file_put_contents(
            $d,
            $collect
        ) > 0;
    }
    private static function decryptChunk($chunk, $key) {
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
    private static function prepareFilter($filter, $content) {
        if (is_null($filter)) return $filter;
        if (is_string($filter)) $filter = explode(',', $filter);
        $streams = array();
        $lines = nsplit($content);
        for ($i = 0; $i < count($lines) - 1; $i++) {
            $line = $lines[$i];
            if (strpos($line, $_ = '#EXT-X-STREAM-INF:') !== 0) continue;
            $line = substr($line, strlen($_));
            $keys = explode(',', $line);
            $info = array();
            foreach ($keys as $key) {
                $key = explode('=', $key, 2);
                if (count($key) != 2) continue;
                $info[strtolower($key[0])] = $key[1];
            }
            $streams[] = $info;
        }
        if (!$streams) return null;
        $return = array();
        foreach ($filter as $f)
            if (is_numeric($f) and intval($f) >= 0)
                $return[] = $f;
            elseif (is_numeric($f) and intval($f) < 0)
                $return[] = count($streams) + intval($f);
            elseif (preg_match("~(.*)(=|<|>|<=|>=)(.*)~", $f, $m)) {
                if ($m[2] === "=" and is_numeric($m[3])) {
                    $sort = array_column($streams, strtolower($m[1]));
                    $_ = array_search($m[3], $sort, $strict = false);
                    if (is_numeric($_)) $return[] = $_;
                } elseif ($m[2] === "=" and strtolower($m[3]) === "min") {
                    $sort = array_column($streams, strtolower($m[1]));
                    asort($sort);
                    list($key) = array_keys($sort);
                    $return[] = $key;
                } elseif ($m[2] === "=" and strtolower($m[3]) === "max") {
                    $sort = array_column($streams, strtolower($m[1]));
                    arsort($sort);
                    list($key) = array_keys($sort);
                    $return[] = $key;
                }
            }
        return $return;
    }
}
