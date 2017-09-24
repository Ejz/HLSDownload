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
            'key' => null,
            'filters' => [],
            'no_ts' => false,
            'level' => 0,
            'progress_extra_n' => false,
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
    private static function getProgressCallback($progress_extra_n) {
        $progress = function ($url, $percent, $total) use ($progress_extra_n) {
            $n = $progress_extra_n ? "\n" : '';
            $n100 = $percent == 100 ? "\n" : "";
            $total = round($total / 1024);
            if ($total >= 1000) {
                $total = round($total / 1000, 1);
                $total = $total == intval($total) ? $total . '.0' : $total;
                $total .= 'M';
            } else $total .= 'K';
            fwrite(STDOUT, "\r{$url} ~ {$percent}% ({$total})" . $n . $n100);
        };
        return function ($ch, $total, $download) use ($progress) {
            static $last = null;
            static $done = false;
            if (!$total) return;
            $percent = round((100 * $download) / $total);
            if ($percent < 0) $percent = 0;
            if ($percent > 100) $percent = 100;
            if (!is_null($last) and round(microtime(true) - $last, 1) < 2.2 and $percent != 100) return;
            $last = microtime(true);
            $info = curl_getinfo($ch);
            $url = $info['url'];
            if ($done) return;
            if ($percent == 100) $done = true;
            $progress($url, $percent, $total);
        };
    }
    private static function backend($link, $settings) {
        $link = (is_file($link) ? $link : realurl($link));
        $dir = & $settings['dir'];
        $level = $settings['level'];
        @ $manifest_name = $settings['manifest_name'];
        if (!$manifest_name) $manifest_name = 'manifest.m3u8';
        @ $stream_name = $settings['stream_name'];
        if (!$stream_name) $stream_name = 'stream.m3u8';
        @ $ts_name = $settings['ts_name'];
        if (!$ts_name) $ts_name = 'chunk.ts';
        $curl_settings = [
            CURLOPT_USERAGENT => $settings['ua'],
            CURLOPT_TIMEOUT => 120,
            'checker' => [200, 201, 202],
        ];
        // if ($settings['headers'] and is_array($settings['headers'])) {
        //     $curl_settings[CURLOPT_HTTPHEADER] = $settings['headers'];
        // }
        $progress_default = (defined('STDOUT') and posix_isatty(STDOUT));
        $progress = (!isset($settings['progress']) or is_null($settings['progress'])) ? $progress_default : (bool)($settings['progress']);
        if ($progress) {
            $curl_settings[CURLOPT_NOPROGRESS] = false;
            $curl_settings[CURLOPT_PROGRESSFUNCTION] = self::getProgressCallback($settings['progress_extra_n']);
        }
        if (preg_match('~^(\d+)\s*(k|m|g)$~i', @ $settings['limit_rate'], $match)) {
            if (defined('CURLOPT_MAX_RECV_SPEED_LARGE')) {
                $m = array('k' => pow(2, 10), 'm' => pow(2, 20), 'g' => pow(2, 30));
                $curl_settings[CURLOPT_MAX_RECV_SPEED_LARGE] = intval($match[1]) * $m[strtolower($match[2])];
            } else return _warn(__CLASS__ . ": CURLOPT_MAX_RECV_SPEED_LARGE is not defined!");
        }
        $cache = $dir . $settings['cache'];
        @ mkdir($cache, $mode = 0755, $recursive = true);
        $getter = function ($link) use ($curl_settings, $cache) {
            if (!host($link) and is_file($link))
                return file_get_contents($link);
            $file = $cache . '/' . md5($link);
            if (!is_file($file)) {
                $content = curl($link, $curl_settings);
                if (!$content) return _warn(__CLASS__ . ": Error while getting {$link}");
                file_put_contents($file, $content);
            }
            return file_get_contents($file);
        };
        $content = $getter($link);
        if (!$content) return;
        // either manifest or ts
        $is_manifest = (strpos($content, '#EXTM3U') === 0);
        if (!$is_manifest) {
            if ($settings['no_ts'] and $level) return false;
            $target = $dir . '/' . $ts_name;
            echo "{$link} -> {$target}\n";
            @ unlink($target);
            $_ = file_put_contents($target, $content);
            if (!$settings['key'] or !$_) return $_ > 0;
            rename($target, $target = $target . '.tmp');
            $error = self::decrypt($target, $settings['key']);
            if ($error)
                return _warn(__CLASS__ . ": Error while decrypting {$link}; {$error}");
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
        $ts_count = isset($settings['ts_count']) ? $settings['ts_count'] : -1;
        $stream_count = isset($settings['stream_count']) ? $settings['stream_count'] : -1;
        foreach (nsplit($content) as $line) {
            if (strpos($line, $str = '#EXT-X-MEDIA-SEQUENCE:') === 0) {
                $collect[] = $line;
                $line = substr($line, strlen($str));
                $ts_count += intval($line);
                continue;
            }
            if (strpos($line, '#EXT-X-KEY:') === 0) {
                $meta = self::extractMeta($line);
                @ $method = strtolower($meta['method']);
                @ $uri = $meta['uri'];
                $base64 = (stripos($uri, $_base64 = 'data:text/plain;base64,') === 0);
                if (strtolower($method) == 'none') {
                    $settings['key'] = null;
                    $collect[] = $line;
                    continue;
                }
                if (!$base64) $uri = realurl($uri, $link);
                if ($settings['no_ts']) {
                    $meta['uri'] = $uri;
                    $collect[] = self::compileLine($meta);
                    continue;
                }
                $uri_content = $base64 ? base64_decode(substr($uri, strlen($_base64))) : $getter($uri);
                $err = __CLASS__ . ": Error getting key from {$line}";
                if ($settings['decrypt']) {
                    if (!$uri_content) return _warn($err);
                    $uri_content = bin2hex($uri_content);
                    @ $iv = $meta['iv'];
                    if (!$iv) $iv = sprintf("0x%016s", dechex($ts_count + 1));
                    $settings['key'] = array(
                        'method' => $method,
                        'uri' => $uri_content,
                        'iv' => $iv,
                        'keyformat' => @ $meta['keyformat'],
                        'keyformatversions' => @ $meta['keyformatversions'],
                    );
                    continue;
                }
                if (!$base64) {
                    if (!$uri_content) return _warn($err);
                    if (!is_dir($keys = $dir . '/keys'))
                        @ mkdir($keys, $mode = 0755, $recursive = true);
                    $i = 0;
                    while (file_exists($file = $keys . '/key' . $i) and md5_file($file) != md5($uri_content))
                        $i++;
                    echo "{$uri} -> {$file}\n";
                    file_put_contents($file, $uri_content);
                    $meta['uri'] = 'keys/key' . $i;
                    $collect[] = self::compileLine($meta);
                    continue;
                }
                $collect[] = $line;
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
                if ($settings['no_ts'] and is_file($link)) {
                    if (!is_file($line)) _warn(__CLASS__ . ": File is not found {$line}");
                    else copy($line, $dir . '/' . $ts_name);
                } elseif ($settings['no_ts']) {
                    $ts_name = $line;
                } else
                    $return = self::backend(
                        $line, ['ts_name' => $ts_name, 'level' => $level + 1] + $settings
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
                            'level' => $level + 1,
                            'dir' => $new_dir,
                        ] + $settings
                    );
                    $collect[] = $stream_name;
                }
                $ext_x_stream_inf = false;
                continue;
            }
            _warn(__CLASS__ . ": Parse error in {$link}; Line: {$line}");
        }
        if ($extinf or $ext_x_stream_inf)
            _warn(__CLASS__ . ": Invalid M3U8 ending at {$link}");
        $collect = implode("\n", $collect) . "\n";
        $is_master = (strpos($collect, "\n#EXT-X-STREAM-INF:") !== false);
        $stream_name = !isset($settings['stream_count']) ? $stream_name : sprintf("stream%s.m3u8", $settings['stream_count']);
        $target = $dir . '/' . ($is_master ? $manifest_name : $stream_name);
        echo "{$link} -> {$target}\n";
        foreach ((is_dir($cache) ? scandir($cache) : []) as $file) {
            if ($file === '.' or $file === '..') continue;
            @ unlink($cache . '/' . $file);
        }
        if (is_dir($cache)) rmdir($cache);
        return file_put_contents($target, $collect) > 0;
    }
    private static function compileLine($meta) {
        $prefix = $meta['_prefix'];
        unset($meta['_prefix']);
        $collect = [];
        foreach ($meta as $key => $value) {
            $value = ctype_alnum(str_replace('_', '', $value)) ? $value : "\"{$value}\"";
            $collect[] = strtoupper($key) . '=' . $value;
        }
        return $prefix . implode(',', $collect);
    }
    private static function extractMeta($line) {
        $add = '';
        $line = explode(':', $line, 2);
        $prefix = $line[0];
        if (isset($line[1])) {
            $line = $line[1];
            $prefix .= ':';
        } else $line = '';
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
        $_ = Lexer::go($line, ['rules' => $rules, 'implode' => null]);
        $_ = $_ ?: [];
        $_['_prefix'] = $prefix;
        return $_;
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
