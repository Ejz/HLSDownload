<?php

//
// HLSDownloadTest - PHP Unit Testing
//

use Ejz\HLSDownload;

class HLSDownloadTest extends PHPUnit_Framework_TestCase {
    public function testCommon() {
        $url = getenv("URL") ?: "";
        if (!$url) return;
        $tmp = rtrim(`mktemp -d`);
        $result = HLSDownload::go($url, ['dir' => $tmp]);
        $this->assertTrue($result);
        $this->assertTrue(count(glob($tmp . '/*.m3u8')) === 1);
        $this->assertTrue(count(glob($tmp . '/stream*')) > 1);
    }
    public function testFilter() {
        $url = getenv("URL") ?: "";
        if (!$url) return;
        return;
        $link = "http://site.com/hls/hls-flat/trailer.m3u8";
        //
        $tmp = rtrim(`mktemp -d`);
        $result = HLSDownload::go($link, ['dir' => $tmp, 'filter' => '-1']);
        $this->assertTrue($result);
        $this->assertTrue(count(glob($tmp . '/*.m3u8')) === 1);
        $this->assertTrue(count(glob($tmp . '/stream2')) === 1);
        //
        $tmp = rtrim(`mktemp -d`);
        $result = HLSDownload::go($link, ['dir' => $tmp, 'filter' => 'bandWIDTH=2000000']);
        $this->assertTrue($result);
        $this->assertTrue(count(glob($tmp . '/*.m3u8')) === 1);
        $this->assertTrue(count(glob($tmp . '/stream2')) === 1);
        //
        $tmp = rtrim(`mktemp -d`);
        $result = HLSDownload::go($link, ['dir' => $tmp, 'filter' => 'bandWIDTH=Max']);
        $this->assertTrue($result);
        $this->assertTrue(count(glob($tmp . '/*.m3u8')) === 1);
        $this->assertTrue(count(glob($tmp . '/stream2')) === 1);
        //
        $tmp = rtrim(`mktemp -d`);
        $result = HLSDownload::go($link, ['dir' => $tmp, 'filter' => 'bandWIDTH=min']);
        $this->assertTrue($result);
        $this->assertTrue(count(glob($tmp . '/*.m3u8')) === 1);
        $this->assertTrue(count(glob($tmp . '/stream0')) === 1);
    }
}
