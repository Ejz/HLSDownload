# HLSDownload [![Travis Status for Ejz/HLSDownload](https://travis-ci.org/Ejz/HLSDownload.svg?branch=master)](https://travis-ci.org/Ejz/HLSDownload)

Recursive HTTP Live Streaming Downloader!

### Quick install

Download and install the latest `hlsdownload.phar` from [releases page](https://github.com/Ejz/HLSDownload/releases):

```bash
$ chmod +x hlsdownload.phar
$ mv hlsdownload.phar /usr/local/bin/hlsdownload
```

Test it:

```bash
$ hlsdownload "http://content.jwplatform.com/manifests/nJEIV3eJ.m3u8"
```

```
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364766.mp4-1.ts -> ./stream0/ts00000.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364766.mp4-2.ts -> ./stream0/ts00001.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364766.mp4-3.ts -> ./stream0/ts00002.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364766.mp4-4.ts -> ./stream0/ts00003.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364766.mp4-5.ts -> ./stream0/ts00004.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364766.mp4-6.ts -> ./stream0/ts00005.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364766.mp4-7.ts -> ./stream0/ts00006.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364766.mp4-8.ts -> ./stream0/ts00007.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364766.mp4-9.ts -> ./stream0/ts00008.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364766.mp4.m3u8?token=0_57bda619_0x15396ea98f31ec5fe834b4c44c1a414765e8bf18 -> ./stream0/stream0.m3u8
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-1703854.mp4-1.ts -> ./stream1/ts00000.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-1703854.mp4-2.ts -> ./stream1/ts00001.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-1703854.mp4-3.ts -> ./stream1/ts00002.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-1703854.mp4-4.ts -> ./stream1/ts00003.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-1703854.mp4-5.ts -> ./stream1/ts00004.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-1703854.mp4-6.ts -> ./stream1/ts00005.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-1703854.mp4-7.ts -> ./stream1/ts00006.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-1703854.mp4-8.ts -> ./stream1/ts00007.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-1703854.mp4-9.ts -> ./stream1/ts00008.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-1703854.mp4.m3u8?token=0_57bda619_0x6d00f46de75199fd964b4f7cde49e8c6513a9ca7 -> ./stream1/stream1.m3u8
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364768.mp4-1.ts -> ./stream2/ts00000.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364768.mp4-2.ts -> ./stream2/ts00001.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364768.mp4-3.ts -> ./stream2/ts00002.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364768.mp4-4.ts -> ./stream2/ts00003.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364768.mp4-5.ts -> ./stream2/ts00004.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364768.mp4-6.ts -> ./stream2/ts00005.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364768.mp4-7.ts -> ./stream2/ts00006.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364768.mp4-8.ts -> ./stream2/ts00007.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364768.mp4-9.ts -> ./stream2/ts00008.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364768.mp4.m3u8?token=0_57bda619_0x26d0184c4c7b03051d906ad80fff717944491e48 -> ./stream2/stream2.m3u8
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364767.mp4-1.ts -> ./stream3/ts00000.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364767.mp4-2.ts -> ./stream3/ts00001.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364767.mp4-3.ts -> ./stream3/ts00002.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364767.mp4-4.ts -> ./stream3/ts00003.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364767.mp4-5.ts -> ./stream3/ts00004.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364767.mp4-6.ts -> ./stream3/ts00005.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364767.mp4-7.ts -> ./stream3/ts00006.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364767.mp4-8.ts -> ./stream3/ts00007.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364767.mp4-9.ts -> ./stream3/ts00008.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364767.mp4.m3u8?token=0_57bda619_0x46d70fed5fe25b87e7812fb898fcf1c3f880ab7c -> ./stream3/stream3.m3u8
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-1753142.mp4-1.ts -> ./stream4/ts00000.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-1753142.mp4-2.ts -> ./stream4/ts00001.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-1753142.mp4-3.ts -> ./stream4/ts00002.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-1753142.mp4-4.ts -> ./stream4/ts00003.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-1753142.mp4-5.ts -> ./stream4/ts00004.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-1753142.mp4-6.ts -> ./stream4/ts00005.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-1753142.mp4-7.ts -> ./stream4/ts00006.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-1753142.mp4-8.ts -> ./stream4/ts00007.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-1753142.mp4-9.ts -> ./stream4/ts00008.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-1753142.mp4.m3u8?token=0_57bda619_0x832eb3ef2ee61af57868dcf45dab69fc5acc7efd -> ./stream4/stream4.m3u8
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364765.mp4-1.ts -> ./stream5/ts00000.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364765.mp4-2.ts -> ./stream5/ts00001.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364765.mp4-3.ts -> ./stream5/ts00002.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364765.mp4-4.ts -> ./stream5/ts00003.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364765.mp4-5.ts -> ./stream5/ts00004.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364765.mp4-6.ts -> ./stream5/ts00005.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364765.mp4-7.ts -> ./stream5/ts00006.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364765.mp4-8.ts -> ./stream5/ts00007.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364765.mp4-9.ts -> ./stream5/ts00008.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-364765.mp4.m3u8?token=0_57bda619_0x41776dbce37ed4c015ce76da9a7b510f2bf87300 -> ./stream5/stream5.m3u8
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-588477.m4a-1.aac -> ./stream6/ts00000.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-588477.m4a-2.aac -> ./stream6/ts00001.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-588477.m4a-3.aac -> ./stream6/ts00002.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-588477.m4a-4.aac -> ./stream6/ts00003.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-588477.m4a-5.aac -> ./stream6/ts00004.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-588477.m4a-6.aac -> ./stream6/ts00005.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-588477.m4a-7.aac -> ./stream6/ts00006.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-588477.m4a-8.aac -> ./stream6/ts00007.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-588477.m4a-9.aac -> ./stream6/ts00008.ts
http://videos-f.jwpsrv.com/content/conversions/zWLy8Jer/videos/nJEIV3eJ-588477.m4a.m3u8?token=0_57bda619_0x294252c3ccafa24b73066f5fdf7dbbeb72763bc1 -> ./stream6/stream6.m3u8
http://content.jwplatform.com/manifests/nJEIV3eJ.m3u8 -> ./hls.m3u8
```
