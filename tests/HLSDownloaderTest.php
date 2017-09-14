<?php

use Ejz\HLSDownload;

class TestHLSDownload extends PHPUnit_Framework_TestCase {
    public function invokeStatic($class, $method, $parameters = array()) {
        $reflection = new \ReflectionClass($class);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        return $method->invokeArgs(null, $parameters);
    }
    public function testHlsDownloadBinary() {
        $scheme = getRequest()->getScheme();
        $host = getRequest()->getHost();
        //
        $tmp = rtrim(`mktemp -d`);
        $res = shell_exec(sprintf(
            "%s -F audio -d %s %s",
            escapeshellarg(ROOT . '/bin/hlsdownload.php'), escapeshellarg($tmp), WWW_ROOT . '/case1/case1.m3u8'
        ));
        $this->assertTrue(strpos($res, 'stream2/chunk00000.ts') !== false);
        $this->assertTrue(strpos($res, 'stream2/chunk00001.ts') !== false);
        $this->assertTrue(strpos($res, 'stream1/chunk00001.ts') === false);
        $this->assertTrue(strpos($res, 'stream0/chunk00001.ts') === false);
        exec('rm -rf ' . escapeshellarg($tmp));
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
    public function testHlsDownloadCase2() {
        $scheme = getRequest()->getScheme();
        $host = getRequest()->getHost();
        //
        $tmp = rtrim(`mktemp -d`);
        HLSDownload::go("{$scheme}://{$host}/case2/case2.m3u8", ['dir' => $tmp, 'decrypt' => true]);
        $files = nsplit(shell_exec(sprintf("find %s -type f -iname '*.ts' -size +0c", escapeshellarg($tmp))));
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $this->assertTrue($content[0] === 'G', "no G at {$file}");
        }
        exec('rm -rf ' . escapeshellarg($tmp));
        //
        $tmp = rtrim(`mktemp -d`);
        HLSDownload::go("{$scheme}://{$host}/case2/case2.m3u8", ['dir' => $tmp, 'decrypt' => false]);
        $files = nsplit(shell_exec(sprintf("find %s -type f -iname '*.ts' -size +0c", escapeshellarg($tmp))));
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $this->assertTrue($content[0] != 'G', "there is G at {$file}");
        }
        exec('rm -rf ' . escapeshellarg($tmp));
    }
    public function testHlsDownloadExtractMeta() {
        $line = 'PROGRAM-ID=1,BANDWIDTH=670000, RESOLUTION=480x270, CODECS="mp4a.40.2,avc1.77.30", CLOSED-CAPTIONS=NONE';
        $meta = $this->invokeStatic('Ejz\HLSDownload', 'extractMeta', array($line));
        $this->assertTrue($meta['program-id'] == '1');
        $this->assertTrue($meta['resolution'] == '480x270');
        $this->assertTrue($meta['codecs'] == 'mp4a.40.2,avc1.77.30');
    }
    public function testHlsDownloadFilters() {
        $scheme = getRequest()->getScheme();
        $host = getRequest()->getHost();
        $streams = function ($dir) {
            $files = nsplit(shell_exec(sprintf(
                "find %s -type f -size +0c | grep -oP '/stream\\d+/stream' | sort",
                escapeshellarg($dir)
            )));
            exec('rm -rf ' . escapeshellarg($dir));
            foreach ($files as & $file) {
                preg_match('~\d+~', $file, $match);
                $file = $match[0];
            }
            return $files;
        };
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
        $filter = $this->invokeStatic('Ejz\HLSDownload', 'filter', array('codecs!=*avc1*', $content));
        $this->assertEquals($filter, [2]);
        $filter = $this->invokeStatic('Ejz\HLSDownload', 'filter', array('!resolution', $content));
        $this->assertEquals($filter, [2]);
        //
        $tmp = rtrim(`mktemp -d`);
        HLSDownload::go("{$scheme}://{$host}/case1/case1.m3u8", ['dir' => $tmp, 'filters' => 'codecs!=*avc1*']);
        $this->assertEquals($streams($tmp), [2]);
        //
        $tmp = rtrim(`mktemp -d`);
        HLSDownload::go("{$scheme}://{$host}/case1/case1.m3u8", ['dir' => $tmp, 'filters' => '!resolution']);
        $this->assertEquals($streams($tmp), [2]);
        //
        $tmp = rtrim(`mktemp -d`);
        HLSDownload::go("{$scheme}://{$host}/case1/case1.m3u8", ['dir' => $tmp, 'filters' => 'resolution']);
        $this->assertEquals($streams($tmp), [0, 1]);
        //
        $tmp = rtrim(`mktemp -d`);
        HLSDownload::go("{$scheme}://{$host}/case1/case1.m3u8", ['dir' => $tmp, 'filters' => 'audio']);
        $this->assertEquals($streams($tmp), [2]);
        //
        $tmp = rtrim(`mktemp -d`);
        HLSDownload::go("{$scheme}://{$host}/case1/case1.m3u8", ['dir' => $tmp, 'filters' => 'video']);
        $this->assertEquals($streams($tmp), [0, 1]);
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
