<?php

//
// HLSDownloaderTest - PHP Unit Testing
//

use Ejz\HLSDownloader;

class HLSDownloaderTest extends PHPUnit_Framework_TestCase {
    public function testFlat() {
        $path = HLSDownloader::go("http://ejz.ru/hls/hls-flat/trailer.m3u8");
        $this -> assertEquals(21, count(scandir($path)));
    }
    public function testLevelUp() {
        $path = HLSDownloader::go("http://ejz.ru/hls/hls-2/trailer.m3u8");
        $this -> assertEquals(21, count(scandir($path)));
    }
    public function testLevelDown() {
        $path = HLSDownloader::go("http://ejz.ru/hls/hls-3/trailer.m3u8");
        $this -> assertEquals(21, count(scandir($path)));
    }
}
