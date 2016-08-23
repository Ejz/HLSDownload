<?php

//
// HLSDownloadTest - PHP Unit Testing
//

use Ejz\HLSDownload;

class HLSDownloadTest extends PHPUnit_Framework_TestCase {
    /*
    public function testEjzLinks() { return;
        foreach (array("http://site.com/hls/hls-flat/trailer.m3u8", "http://site.com/hls/hls-2/trailer.m3u8", "http://site.com/hls/hls-3/trailer.m3u8") as $link) {
            $tmp = rtrim(`mktemp -d`);
            $result = HLSDownload::go($link, ['dir' => $tmp]);
            $this->assertTrue($result);
            $this->assertTrue(count(glob($tmp . '/*.m3u8')) === 1);
            $this->assertTrue(count(glob($tmp . '/stream*')) > 1);
            $this->assertTrue(count(glob($tmp . '/stream0/*.m3u8')) === 1);
            $this->assertTrue(count(glob($tmp . '/stream0/*.ts')) > 1);
        }
    }
    public function testFilter() { return;
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
