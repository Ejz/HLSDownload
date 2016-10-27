<?php

namespace Ejz;

class HLSDownload {
    public static function go($url, $settings = array()) {
        $dir = isset($settings['dir']) ? $settings['dir'] : '.';
        $ua = isset($settings['ua']) ? $settings['ua'] : "HLSDownload (github.com/Ejz/HLSDownload)";
        if (!is_dir($dir)) $dir = '.';
        if (!is_writable($dir)) {
            _log("DIRECTORY IS NOT WRITABLE: {$dir}", E_USER_WARNING);
            return false;
        }
        $dir = rtrim($dir, '/');
        return self::goBackend($url, [
            'dir' => $dir,
            'ua' => $ua,
            'filter' => (isset($settings['filter']) ? $settings['filter'] : null),
            'stream' => null,
            'ts' => null,
            'key' => null,
            'progress' => ((isset($settings['progress']) and is_callable($settings['progress'])) ? $settings['progress'] : null),
            'limitRate' => (isset($settings['limitRate']) ? $settings['limitRate'] : null)
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
        $dir = $settings['dir'];
        $stream = $settings['stream'];
        $ts = $settings['ts'];
        $url = realurl($url);
        $curl = array(
            CURLOPT_USERAGENT => $settings['ua'],
            CURLOPT_TIMEOUT => 120
        );
        if ($settings['progress']) {
            $curl[CURLOPT_NOPROGRESS] = false;
            $curl[CURLOPT_PROGRESSFUNCTION] = self::getProgressClosure($settings['progress']);
        }
        if ($settings['limitRate'] and preg_match('~^(\d+)\s*(k|m|g)$~i', $settings['limitRate'], $match)) {
            if (defined('CURLOPT_MAX_RECV_SPEED_LARGE')) {
                $m = array('k' => pow(2, 10), 'm' => pow(2, 20), 'g' => pow(2, 30));
                $curl[CURLOPT_MAX_RECV_SPEED_LARGE] = intval($match[1]) * $m[strtolower($match[2])];
            } else {
                _log("CURLOPT_MAX_RECV_SPEED_LARGE IS NOT DEFINED!");
                return false;
            }
        }
        $content = curl($url, $curl);
        if (strpos($content, '#EXTM3U') !== 0) {
            $d = (is_null($ts) ? $dir . '/ts.ts' : $dir . sprintf("/ts%05s.ts", $ts));
            _log("{$url} -> {$d}");
            $_ = file_put_contents($d, $content);
            if (!$settings['key']) return $_ > 0;
            $_ = self::decryptChunk($d, $key['method'], $key['uri'], $key['iv']);
            if (is_string($_)) {
                _log("ERROR WHILE DECRYPTING: {$_}");
                return false;
            }
            if (!$_) _log("CHUNK {$d} IS NOT DECRYPTED CORRECTLY!");
            return $_;
        }
        $collect = array();
        $extinf = false;
        $ext_x_stream_inf = false;
        $filter = self::prepareFilter($settings['filter'], $content);
        $settings['key'] = null;
        foreach (nsplit($content) as $line) {
            if (strpos($line, '#EXT-X-KEY:') === 0) {
                $extract = function ($key) use ($line) {
                    preg_match('~(^|,)' . $key . '=(")?(.*?)\2(,|$)~i', $line, $match);
                    if (!$match) return null;
                    $match = $match[3];
                    $match = trim($match, '"');
                    return $match;
                };
                $method = $extract('method');
                $uri = $extract('uri');
                if (strtolower($method) != 'none') {
                    if (strpos($uri, $_ = 'data:text/plain;base64,') === 0)
                        $uri = base64_decode(substr($uri, strlen($_)));
                    elseif (host($_ = realurl($uri, $url))) $uri = curl($_);
                    else $uri = null;
                    if (!$uri) {
                        _log("ERROR WHILE GETTING KEY: {$line}");
                        return false;
                    }
                    $uri = bin2hex(trim($uri));
                    $settings['key'] = array(
                        'method' => $method,
                        'uri' => $uri,
                        'iv' => $extract('iv'),
                        'keyformat' => $extract('keyformat'),
                    );
                } else $settings['key'] = null;
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
                $line = realurl($line, $url);
                $return = self::goBackend($line, ['ts' => $ts] + $settings);
                if (!$return) {
                    _log("ERROR WHILE PROCESSING: {$line}");
                    return false;
                }
                $collect[] = sprintf("ts%05s.ts", $ts);
                $extinf = false;
            } elseif ($ext_x_stream_inf) {
                if (is_null($filter) or in_array($stream, $filter)) {
                    $line = realurl($line, $url);
                    if (!is_dir($d = $dir . '/stream' . $stream)) mkdir($d);
                    $return = self::goBackend($line, ['stream' => $stream, 'dir' => $d] + $settings);
                    if (!$return) {
                        _log("ERROR WHILE PROCESSING: {$line}");
                        return false;
                    }
                    $collect[] = "stream{$stream}/stream{$stream}.m3u8";
                }
                $ext_x_stream_inf = false;
            } else {
                _log("PARSE ERROR: {$url}");
                return false;
            }
        }
        $stream = $settings['stream'];
        $collect = implode("\n", $collect) . "\n";
        if (is_null($stream) and strpos($collect, "\n#EXT-X-STREAM-INF:") === false) {
            _log("NO STREAMS: {$url}");
            return false;
        }
        $d = (is_null($stream) ? $dir . '/hls.m3u8' : $dir . "/stream{$stream}.m3u8");
        _log("{$url} -> {$d}");
        return file_put_contents(
            $d,
            $collect
        ) > 0;
    }
    private static function decryptChunk($chunk, $method, $key, $iv) {
        $methods = array("AES-128");
        if (!in_array(strtolower($method), array_map('strtolower', $methods)))
            return 'Unknown encryption method!';
        if (!is_file($chunk)) return 'Chunk file is NOT found!';
        exec('which openssl && which file', $output, $return);
        if ($return != '0') return "openssl or file is NOT found!";
        $tmp = rtrim(`mktemp`);
        shell_exec($_ = sprintf(
            "openssl aes-128-cbc -d -in %s -out %s -p -nosalt -iv %s -K %s",
            escapeshellarg($chunk),
            escapeshellarg($tmp),
            preg_replace('~^0x~', '', $iv),
            escapeshellarg($key)
        ));
        $_ = shell_exec("file 2>&1 " . escapeshellarg($tmp));
        if (stripos($_, 'MPEG transport') !== false) {
            unlink($chunk);
            rename($tmp, $chunk);
            return true;
        } else {
            unlink($tmp);
            return false;
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
