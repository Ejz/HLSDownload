# HLSDownload [![Travis Status for Ejz/HLSDownload](https://travis-ci.org/Ejz/HLSDownload.svg?branch=master)](https://travis-ci.org/Ejz/HLSDownload)

Recursive HTTP Live Streaming Downloader!

### Quick start (CLI)

```bash
$ curl -sSL 'https://raw.githubusercontent.com/Ejz/HLSDownloader/master/i.sh' | sudo bash
$ hls-downloader-cli "http://ejz.ru/hls/hls-flat/trailer.m3u8"
http://ejz.ru/hls/hls-flat/480x2701.ts .. ./stream0/ts00000.ts
http://ejz.ru/hls/hls-flat/480x2702.ts .. ./stream0/ts00001.ts
http://ejz.ru/hls/hls-flat/480x2703.ts .. ./stream0/ts00002.ts
http://ejz.ru/hls/hls-flat/480x2704.ts .. ./stream0/ts00003.ts
http://ejz.ru/hls/hls-flat/480x2705.ts .. ./stream0/ts00004.ts
http://ejz.ru/hls/hls-flat/480x270.m3u8 .. ./stream0/stream0.m3u8
http://ejz.ru/hls/hls-flat/640x3601.ts .. ./stream1/ts00000.ts
http://ejz.ru/hls/hls-flat/640x3602.ts .. ./stream1/ts00001.ts
http://ejz.ru/hls/hls-flat/640x3603.ts .. ./stream1/ts00002.ts
http://ejz.ru/hls/hls-flat/640x3604.ts .. ./stream1/ts00003.ts
http://ejz.ru/hls/hls-flat/640x3605.ts .. ./stream1/ts00004.ts
http://ejz.ru/hls/hls-flat/640x360.m3u8 .. ./stream1/stream1.m3u8
http://ejz.ru/hls/hls-flat/1280x7201.ts .. ./stream2/ts00000.ts
http://ejz.ru/hls/hls-flat/1280x7202.ts .. ./stream2/ts00001.ts
http://ejz.ru/hls/hls-flat/1280x7203.ts .. ./stream2/ts00002.ts
http://ejz.ru/hls/hls-flat/1280x7204.ts .. ./stream2/ts00003.ts
http://ejz.ru/hls/hls-flat/1280x7205.ts .. ./stream2/ts00004.ts
http://ejz.ru/hls/hls-flat/1280x720.m3u8 .. ./stream2/stream2.m3u8
http://ejz.ru/hls/hls-flat/trailer.m3u8 .. ./hls.m3u8
```

There are two args in CLI mode: `-d` to set target directory, `-F` to filter stream playlists. Example, download just stream with highest bitrate (`BANDWIDTH` field in master playlist):

```
$ hls-downloader-cli -F BANDWIDTH=MAX "http://ejz.ru/hls/hls-flat/trailer.m3u8"
http://ejz.ru/hls/hls-flat/1280x7201.ts .. ./stream2/ts00000.ts
http://ejz.ru/hls/hls-flat/1280x7202.ts .. ./stream2/ts00001.ts
http://ejz.ru/hls/hls-flat/1280x7203.ts .. ./stream2/ts00002.ts
http://ejz.ru/hls/hls-flat/1280x7204.ts .. ./stream2/ts00003.ts
http://ejz.ru/hls/hls-flat/1280x7205.ts .. ./stream2/ts00004.ts
http://ejz.ru/hls/hls-flat/1280x720.m3u8 .. ./stream2/stream2.m3u8
http://ejz.ru/hls/hls-flat/trailer.m3u8 .. ./hls.m3u8
```

### Quick start (PHP)

```bash
$ mkdir myproject && cd myproject
$ curl -sS 'https://getcomposer.org/installer' | php
$ php composer.phar require ejz/hls-downloader:~1.0
```

Let's test on an HLS file:

```php
<?php

define('ROOT', __DIR__);
require(ROOT . '/vendor/autoload.php');

use Ejz\HLSDownloader;

$tmp = rtrim(`mktemp -d`);
$link = "http://ejz.ru/hls/hls-flat/trailer.m3u8";
$result = HLSDownloader::go($link, array('dir' => $tmp));
if (!$result) {
    echo "FAILED!\n";
} else {
    echo "SUCCESS!\n";
    $files = nsplit(shell_exec("find " . escapeshellarg($tmp) . " | grep m3u8"));
    foreach ($files as $file)
        echo $file, "\n";
}
```

Will output:

```
SUCCESS!
/tmp/tmp.EBlLhXenFY/stream1/stream1.m3u8
/tmp/tmp.EBlLhXenFY/stream0/stream0.m3u8
/tmp/tmp.EBlLhXenFY/hls.m3u8
/tmp/tmp.EBlLhXenFY/stream2/stream2.m3u8
```
