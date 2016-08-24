# HLSDownload [![Travis Status for Ejz/HLSDownload](https://travis-ci.org/Ejz/HLSDownload.svg?branch=master)](https://travis-ci.org/Ejz/HLSDownload)

Recursive HTTP Live Streaming Downloader!

### Install

Download and install the latest `hlsdownload.phar` (see [releases page](https://github.com/Ejz/HLSDownload/releases) for details):

```bash
$ wget "http://ejz.ru/hlsdownload.phar"
$ chmod +x hlsdownload.phar
$ mv hlsdownload.phar /usr/local/bin/hlsdownload
```

Test it:

```bash
$ hlsdownload "http://content.jwplatform.com/manifests/nJEIV3eJ.m3u8"
```

### Requirements

PHP 5.5 or above (with cURL library installed)

### Usage

* The `-d` option sets target directory
* The `-F` option allows you to select streams from master manifest
* The `--progress` option turns on progress animation (percents)
* The `--limit-rate` limits download speed

### Examples

Download stream with highest bitrate:

```bash
$ hlsdownload -F bandwidth=max "http://content.jwplatform.com/manifests/nJEIV3eJ.m3u8"
```

Save to `/tmp` and limit connection speed:

```bash
$ hlsdownload -d /tmp --limit-rate 100k "http://content.jwplatform.com/manifests/nJEIV3eJ.m3u8"
```

### Authors

- Ejz Cernisev  | [GitHub](https://github.com/Ejz) | <ejz@ya.ru>

### License

HLSDownload is licensed under the [WTFPL License](https://en.wikipedia.org/wiki/WTFPL) (see [LICENSE](LICENSE))
