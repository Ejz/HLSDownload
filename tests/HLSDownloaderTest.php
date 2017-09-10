<?php

use Ejz\HLSDownload;

class TestHLSDownload extends PHPUnit_Framework_TestCase {
    public function invokeStatic($class, $method, $parameters = array()) {
        $reflection = new \ReflectionClass($class);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        return $method->invokeArgs(null, $parameters);
    }
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
        $tmp = rtrim(`mktemp -d`);
        HLSDownload::go(WWW_ROOT . '/case1/case1.m3u8', ['dir' => $tmp]);
        $files_result = nsplit(shell_exec(sprintf("find %s -type f -size +0c", escapeshellarg($tmp))));
        $files_case = nsplit(shell_exec(sprintf("find %s -type f -size +0c", escapeshellarg(WWW_ROOT . '/case1'))));
        $this->assertTrue(count($files_result) === count($files_case));
        exec('rm -rf ' . escapeshellarg($tmp));
    }
    public function testHlsDownloadFilter() {
        $content = '
            #EXT-X-STREAM-INF:PROGRAM-ID=1,BANDWIDTH=670000,RESOLUTION=480x270,CODECS="mp4a.40.2,avc1.77.30",CLOSED-CAPTIONS=NONE
            1
            #EXT-X-STREAM-INF:PROGRAM-ID=1,BANDWIDTH=3990000,RESOLUTION=1920x1080,CODECS="mp4a.40.2,avc1.77.30",CLOSED-CAPTIONS=NONE
            2
            #EXT-X-STREAM-INF:PROGRAM-ID=1,BANDWIDTH=120000,CODECS="mp4a.40.2"
            3
        ';
        $content = implode("\n", nsplit($content));
        $filter = $this->invokeStatic('Ejz\HLSDownload', 'filter', array(0, $content));
        $this->assertEquals($filter, ['0']);
        $filter = $this->invokeStatic('Ejz\HLSDownload', 'filter', array(-1, $content));
        $this->assertEquals($filter, [2]);
        $filter = $this->invokeStatic('Ejz\HLSDownload', 'filter', array('-1,0', $content));
        $this->assertEquals($filter, [2, 0]);
        $filter = $this->invokeStatic('Ejz\HLSDownload', 'filter', array('rESOLUTION=1920x1080', $content));
        $this->assertEquals($filter, [1]);
        $filter = $this->invokeStatic('Ejz\HLSDownload', 'filter', array('PROGRAM-ID=1', $content));
        $this->assertEquals($filter, [0, 1, 2]);
        $filter = $this->invokeStatic('Ejz\HLSDownload', 'filter', array('bandWIDTH=max,bandWIDTH=min', $content));
        $this->assertEquals($filter, [1, 2]);
        $filter = $this->invokeStatic('Ejz\HLSDownload', 'filter', array('bandWIDTH>=120000', $content));
        $this->assertEquals($filter, [0, 1, 2]);
        $filter = $this->invokeStatic('Ejz\HLSDownload', 'filter', array('bandWIDTH<=120000', $content));
        $this->assertEquals($filter, [2]);
        $filter = $this->invokeStatic('Ejz\HLSDownload', 'filter', array('bandWIDTH!<=120000', $content));
        $this->assertEquals($filter, [0, 1]);
        $filter = $this->invokeStatic('Ejz\HLSDownload', 'filter', array('audio', $content));
        $this->assertEquals($filter, [0, 1]);
        // var_dump($filter);
        // $scheme = getRequest()->getScheme();
        // $host = getRequest()->getHost();
        // //
        // $tmp = rtrim(`mktemp -d`);
        // HLSDownload::go("{$scheme}://{$host}/case1/case1.m3u8", ['dir' => $tmp, 'filter' => 'bandWIDTH=Max']);
        // $files_result = nsplit(shell_exec(sprintf("find %s -type f -size +0c", escapeshellarg($tmp))));
        // $files_case = nsplit(shell_exec(sprintf("find %s -type f -size +0c", escapeshellarg(WWW_ROOT . '/case1'))));
        // var_dump($files_result);
        // var_dump($files_case);
        // $this->assertTrue(count($files_result) === count($files_case));
        // exec('rm -rf ' . escapeshellarg($tmp));
        //
        // $tmp = rtrim(`mktemp -d`);
        // HLSDownload::go(WWW_ROOT . '/case1/case1.m3u8', ['dir' => $tmp]);
        // $files_result = nsplit(shell_exec(sprintf("find %s -type f -size +0c", escapeshellarg($tmp))));
        // $files_case = nsplit(shell_exec(sprintf("find %s -type f -size +0c", escapeshellarg(WWW_ROOT . '/case1'))));
        // $this->assertTrue(count($files_result) === count($files_case));
        // exec('rm -rf ' . escapeshellarg($tmp));
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
