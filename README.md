# HLSDownloader [![Travis Status for Ejz/HLSDownloader](https://travis-ci.org/Ejz/HLSDownloader.svg?branch=master)](https://travis-ci.org/Ejz/HLSDOwnloader)

Recursive HTTP Live Streaming Downloader!

### Quick start (CLI)

```bash
$ curl -sSL 'https://raw.githubusercontent.com/Ejz/HLSDownloader/master/i.sh' | sudo bash
$ hls-downloader-cli "http://ejz.ru/hls/hls-flat/trailer.m3u8"
/tmp/tmp.jYqtLR4pVQ
$ find /tmp/tmp.jYqtLR4pVQ -type f
/tmp/tmp.jYqtLR4pVQ/1280x7203.ts
/tmp/tmp.jYqtLR4pVQ/480x2705.ts
/tmp/tmp.jYqtLR4pVQ/1280x720.m3u8
/tmp/tmp.jYqtLR4pVQ/1280x7201.ts
/tmp/tmp.jYqtLR4pVQ/640x3603.ts
/tmp/tmp.jYqtLR4pVQ/480x270.m3u8
/tmp/tmp.jYqtLR4pVQ/640x3604.ts
/tmp/tmp.jYqtLR4pVQ/480x2703.ts
/tmp/tmp.jYqtLR4pVQ/480x2704.ts
/tmp/tmp.jYqtLR4pVQ/640x3601.ts
/tmp/tmp.jYqtLR4pVQ/640x3605.ts
/tmp/tmp.jYqtLR4pVQ/480x2702.ts
/tmp/tmp.jYqtLR4pVQ/trailer.m3u8
/tmp/tmp.jYqtLR4pVQ/480x2701.ts
/tmp/tmp.jYqtLR4pVQ/640x360.m3u8
/tmp/tmp.jYqtLR4pVQ/1280x7202.ts
/tmp/tmp.jYqtLR4pVQ/640x3602.ts
/tmp/tmp.jYqtLR4pVQ/1280x7205.ts
/tmp/tmp.jYqtLR4pVQ/1280x7204.ts
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
$path = HLSDownloader::go("http://ejz.ru/hls/hls-flat/trailer.m3u8");
echo $path, "\n";
foreach(scandir($path) as $file)
    echo $file, "\n";
```

Will output:

```
/tmp/tmp.oVCbca8212
.
..
1280x7201.ts
1280x7202.ts
1280x7203.ts
1280x7204.ts
1280x7205.ts
1280x720.m3u8
480x2701.ts
480x2702.ts
480x2703.ts
480x2704.ts
480x2705.ts
480x270.m3u8
640x3601.ts
640x3602.ts
640x3603.ts
640x3604.ts
640x3605.ts
640x360.m3u8
trailer.m3u8
```

### CI: Codeship

[![Codeship Status for Ejz/HLSDownloader](https://codeship.com/projects/63b9a990-7045-0132-4b61-227a26fe7ed7/status)](https://codeship.com/projects/54502)

### CI: Travis

[![Travis Status for Ejz/HLSDownloader](https://travis-ci.org/Ejz/HLSDownloader.svg?branch=master)](https://travis-ci.org/Ejz/HLSDownloader)
