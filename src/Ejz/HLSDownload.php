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
            'ts' => null
        ]);
    }
    public static function progress($ch, $total, $download) {
        static $last = null;
        static $already = array();
        if (!$total) return;
        $percent = round((100 * $download) / $total);
        if ($percent < 0) $percent = 0;
        if ($percent > 100) $percent = 100;
        if (!is_null($last) and round(microtime(true) - $last, 1) < 0.5 and $percent < 100) return;
        $info = curl_getinfo($ch);
        $url = $info['url'];
        if (isset($already[$url]) and $already[$url]) return;
        if ($percent == 100) $already[$url] = true;
        fwrite(STDERR, "\r{$url}: {$percent}%" . ($percent == 100 ? "\n" : ""));
    }
    private static function goBackend($url, $settings) {
        $dir = $settings['dir'];
        $stream = $settings['stream'];
        $ts = $settings['ts'];
        $url = realurl($url);
        $content = curl($url, array(
            CURLOPT_USERAGENT => $settings['ua'],
            "CURLOPT_PROGRESSFUNCTION" => array(__CLASS__, 'progress'),
            "CURLOPT_NOPROGRESS" => false
        ));
        if (strpos($content, '#EXTM3U') !== 0) {
            $d = (is_null($ts) ? $dir . '/ts.ts' : $dir . sprintf("/ts%05s.ts", $ts));
            _log("{$url} .. {$d}");
            return file_put_contents($d, $content) > 0;
        }
        $collect = array();
        $extinf = false;
        $ext_x_stream_inf = false;
        $filter = self::prepareFilter($settings['filter'], $content);
        foreach (nsplit($content) as $line) {
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
        _log("{$url} .. {$d}");
        return file_put_contents(
            $d,
            $collect
        ) > 0;
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
