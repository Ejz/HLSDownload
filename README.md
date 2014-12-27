# HLSDownloader [![Travis Status for Ejz/HLSDownloader](https://travis-ci.org/Ejz/HLSDownloader.svg?branch=master)](https://travis-ci.org/Ejz/HLSDOwnloader)

Recursive HTTP Live Streaming Downloader!

### Quick start

```bash
$ mkdir myproject && cd myproject
$ curl -sS 'https://getcomposer.org/installer' | php
$ nano -w composer.json
```

Insert following code:

```javascript
{
    "require": {
        "ejz/hls-downloader": "~1.0"
    }
}
```

Now install dependencies:

```bash
$ php composer.phar install
```

Let's test on an HLS file:

```php
<?php

define('ROOT', __DIR__);
require(ROOT . '/vendor/autoload.php');

use Ejz\HLSDownloader;
$path = HLSDownloader::go("http://ejz.ru/hls/hls-flat/trailer.m3u8");
foreach(scandir($path) as $file) echo $file, chr(10);
```

```php
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

[![Codeship Status for Ejz/HLSDownloader](https://codeship.com/projects/bcd7db20-6abb-0132-5494-2e0b75730361/status)](https://codeship.com/projects/53779)

### CI: Travis

[![Travis Status for Ejz/HLSDownloader](https://travis-ci.org/Ejz/HLSDownloader.svg?branch=master)](https://travis-ci.org/Ejz/HLSDownloader)
