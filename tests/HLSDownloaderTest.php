<?php

//
// HLSDownloaderTest - PHP Unit Testing
//

use Ejz\HLSDownloader;

class HLSDownloaderTest extends PHPUnit_Framework_TestCase {
    public function testEjzLinks() {
        foreach (array("http://ejz.ru/hls/hls-flat/trailer.m3u8", "http://ejz.ru/hls/hls-2/trailer.m3u8", "http://ejz.ru/hls/hls-3/trailer.m3u8") as $link) {
            $tmp = rtrim(`mktemp -d`);
            $result = HLSDownloader::go($link, ['dir' => $tmp]);
            $this->assertTrue($result);
            $this->assertTrue(count(glob($tmp . '/*.m3u8')) === 1);
            $this->assertTrue(count(glob($tmp . '/stream*')) > 1);
            $this->assertTrue(count(glob($tmp . '/stream0/*.m3u8')) === 1);
            $this->assertTrue(count(glob($tmp . '/stream0/*.ts')) > 1);
        }
    }
    public function testFilter() {
        $link = "http://ejz.ru/hls/hls-flat/trailer.m3u8";
        //
        $tmp = rtrim(`mktemp -d`);
        $result = HLSDownloader::go($link, ['dir' => $tmp, 'filter' => '-1']);
        $this->assertTrue($result);
        $this->assertTrue(count(glob($tmp . '/*.m3u8')) === 1);
        $this->assertTrue(count(glob($tmp . '/stream2')) === 1);
        //
        $tmp = rtrim(`mktemp -d`);
        $result = HLSDownloader::go($link, ['dir' => $tmp, 'filter' => 'bandWIDTH=2000000']);
        $this->assertTrue($result);
        $this->assertTrue(count(glob($tmp . '/*.m3u8')) === 1);
        $this->assertTrue(count(glob($tmp . '/stream2')) === 1);
        //
        $tmp = rtrim(`mktemp -d`);
        $result = HLSDownloader::go($link, ['dir' => $tmp, 'filter' => 'bandWIDTH=Max']);
        $this->assertTrue($result);
        $this->assertTrue(count(glob($tmp . '/*.m3u8')) === 1);
        $this->assertTrue(count(glob($tmp . '/stream2')) === 1);
    }
}
