<?php

//
// HLSDownloaderTest - PHP Unit Testing
//

use Ejz\HLSDownloader;

class HLSDownloaderTest extends PHPUnit_Framework_TestCase {
    public function testSimple() {
        set_error_handler(function($_, $__) {
            echo $__, chr(10);
        });
        HLSDownloader::go("http://ejz.ru/hls/hls-flat/trailer.m3u8");
    }
}
