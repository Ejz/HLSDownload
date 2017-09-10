<?php

use Ejz\HLSDownload;

class TestHLSDownload extends PHPUnit_Framework_TestCase {
    public function testHlsDownloadCase1() {
        $scheme = getRequest()->getScheme();
        $host = getRequest()->getHost();
        //
        $tmp = rtrim(`mktemp -d`);
        HLSDownload::go("{$scheme}://{$host}/case1/case1.m3u8", ['dir' => $tmp]);
        $files_result = nsplit(shell_exec(sprintf("find %s -type f -size +0c", escapeshellarg($tmp))));
        $files_case = nsplit(shell_exec(sprintf("find %s -type f -size +0c", escapeshellarg(WWW_ROOT . '/case1'))));
        $this->assertTrue(count($files_result) === count($files_case));
        exec('rm -rf ' . escapeshellarg($tmp));
        //
        // return;
        $tmp = rtrim(`mktemp -d`);
        HLSDownload::go(WWW_ROOT . '/case1/case1.m3u8', ['dir' => $tmp]);
        $files_result = nsplit(shell_exec(sprintf("find %s -type f -size +0c", escapeshellarg($tmp))));
        $files_case = nsplit(shell_exec(sprintf("find %s -type f -size +0c", escapeshellarg(WWW_ROOT . '/case1'))));
        var_dump($files_result);
        var_dump($files_case);
    }
    // public function testCase1() {
    //     $url = getenv("URL") ?: "";
    //     if (!$url) return;
    //     $tmp = rtrim(`mktemp -d`);
    //     $result = HLSDownload::go($url, ['dir' => $tmp]);
    //     $this->assertTrue($result);
    //     $this->assertTrue(count(glob($tmp . '/*.m3u8')) === 1);
    //     $this->assertTrue(count(glob($tmp . '/stream*')) > 1);
    // }
    // public function testFilter() {
    //     $url = getenv("URL") ?: "";
    //     if (!$url) return;
    //     return;
    //     $link = "http://site.com/hls/hls-flat/trailer.m3u8";
    //     //
    //     $tmp = rtrim(`mktemp -d`);
    //     $result = HLSDownload::go($link, ['dir' => $tmp, 'filter' => '-1']);
    //     $this->assertTrue($result);
    //     $this->assertTrue(count(glob($tmp . '/*.m3u8')) === 1);
    //     $this->assertTrue(count(glob($tmp . '/stream2')) === 1);
    //     //
    //     $tmp = rtrim(`mktemp -d`);
    //     $result = HLSDownload::go($link, ['dir' => $tmp, 'filter' => 'bandWIDTH=2000000']);
    //     $this->assertTrue($result);
    //     $this->assertTrue(count(glob($tmp . '/*.m3u8')) === 1);
    //     $this->assertTrue(count(glob($tmp . '/stream2')) === 1);
    //     //
    //     $tmp = rtrim(`mktemp -d`);
    //     $result = HLSDownload::go($link, ['dir' => $tmp, 'filter' => 'bandWIDTH=Max']);
    //     $this->assertTrue($result);
    //     $this->assertTrue(count(glob($tmp . '/*.m3u8')) === 1);
    //     $this->assertTrue(count(glob($tmp . '/stream2')) === 1);
    //     //
    //     $tmp = rtrim(`mktemp -d`);
    //     $result = HLSDownload::go($link, ['dir' => $tmp, 'filter' => 'bandWIDTH=min']);
    //     $this->assertTrue($result);
    //     $this->assertTrue(count(glob($tmp . '/*.m3u8')) === 1);
    //     $this->assertTrue(count(glob($tmp . '/stream0')) === 1);
    // }
}
