# Laravel FFMpeg

[![Latest Version on Packagist](https://img.shields.io/packagist/v/pbmedia/laravel-ffmpeg.svg?style=flat-square)](https://packagist.org/packages/pbmedia/laravel-ffmpeg)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://img.shields.io/travis/pascalbaljetmedia/laravel-ffmpeg/master.svg?style=flat-square)](https://travis-ci.org/pascalbaljetmedia/laravel-ffmpeg)
[![Quality Score](https://img.shields.io/scrutinizer/g/pascalbaljetmedia/laravel-ffmpeg.svg?style=flat-square)](https://scrutinizer-ci.com/g/pascalbaljetmedia/laravel-ffmpeg)
[![Total Downloads](https://img.shields.io/packagist/dt/pbmedia/laravel-ffmpeg.svg?style=flat-square)](https://packagist.org/packages/pbmedia/laravel-ffmpeg)

This package provides an integration with FFmpeg for Laravel 6.0 and higher. The storage of the files is handled by [Laravel's Filesystem](http://laravel.com/docs/7.0/filesystem).

## Features
* Super easy wrapper around [PHP-FFMpeg](https://github.com/PHP-FFMpeg/PHP-FFMpeg), including support for filters and other advanced features.
* Integration with [Laravel's Filesystem](http://laravel.com/docs/7.0/filesystem), [configuration system](https://laravel.com/docs/7.0/configuration) and [logging handling](https://laravel.com/docs/7.0/errors).
* Compatible with Laravel 6.0 and higher.
* Support for [Package Discovery](https://laravel.com/docs/7.0/packages#package-discovery).
* Built-in support for HLS.
* PHP 7.4 only.

## Installation

If you're still using an older version of Laravel (or PHP < 7.2), please use the chart below to find out which version you should use. Mind that older versions are no longer supported.

| Laravel Version | Package Version |
|-----------------|-----------------|
| 6.0 + 7.0       | 7.0             |
| 7.0             | 6.0             |
| 6.0             | 5.0             |
| 5.8             | 4.0             |
| 5.7             | 3.0             |
| 5.6             | 2.1             |
| 5.1-5.5         | 1.3             |

You can install the package via composer:

``` bash
composer require pbmedia/laravel-ffmpeg
```

Add the Service Provider and Facade to your ```app.php``` config file if you're not using Package Discovery.

``` php

// Laravel 5: config/app.php

'providers' => [
    ...
    Pbmedia\LaravelFFMpeg\Support\ServiceProvider::class,
    ...
];

'aliases' => [
    ...
    'FFMpeg' => Pbmedia\LaravelFFMpeg\Support\FFMpeg::class
    ...
];
```

Publish the config file using the artisan CLI tool:

``` bash
php artisan vendor:publish --provider="Pbmedia\LaravelFFMpeg\FFMpegServiceProvider"
```

## Usage

Convert an audio or video file:

``` php
FFMpeg::fromDisk('songs')
    ->open('yesterday.mp3')
    ->export()
    ->toDisk('converted_songs')
    ->inFormat(new \FFMpeg\Format\Audio\Aac)
    ->save('yesterday.aac');
```

Instead of the ```fromDisk()``` method you can also use the ```fromFilesystem()``` method, where ```$filesystem``` is an instance of ```Illuminate\Contracts\Filesystem\Filesystem```.

``` php
$media = FFMpeg::fromFilesystem($filesystem)->open('yesterday.mp3');
```

You can monitor the transcoding progress. Use the ```onProgress``` method to provide a callback which gives you the completed percentage. In previous versions of this package you had to pass the callback to the format object.

``` php
FFMpeg::open('steve_howe.mp4')
    ->export()
    ->onProgress(function ($percentage) {
        echo "$percentage % transcoded";
    });
```

You can add filters through a ```Closure``` or by using PHP-FFMpeg's Filter objects:

``` php
FFMpeg::fromDisk('videos')
    ->open('steve_howe.mp4')
    ->addFilter(function ($filters) {
        $filters->resize(new \FFMpeg\Coordinate\Dimension(640, 480));
    })
    ->export()
    ->toDisk('converted_videos')
    ->inFormat(new \FFMpeg\Format\Video\X264)
    ->save('small_steve.mkv');

// or

$start = \FFMpeg\Coordinate\TimeCode::fromSeconds(5)
$clipFilter = new \FFMpeg\Filters\Video\ClipFilter($start);

FFMpeg::fromDisk('videos')
    ->open('steve_howe.mp4')
    ->addFilter($clipFilter)
    ->export()
    ->toDisk('converted_videos')
    ->inFormat(new \FFMpeg\Format\Video\X264)
    ->save('short_steve.mkv');
```

Sometimes you don't want to use the built-in filters. You can apply your own filter by providing a set of options. This can be an array or multiple strings as arguments:

``` php
FFMpeg::fromDisk('videos')
    ->open('steve_howe.mp4')
    ->addFilter(['-itsoffset', 1]);

// or

FFMpeg::fromDisk('videos')
    ->open('steve_howe.mp4')
    ->addFilter('-itsoffset', 1);
```

Chain multiple convertions:

``` php
// The 'fromDisk()' method is not required, the file will now
// be opened from the default 'disk', as specified in
// the config file.

FFMpeg::open('my_movie.mov')

    // export to FTP, converted in WMV
    ->export()
    ->toDisk('ftp')
    ->inFormat(new \FFMpeg\Format\Video\WMV)
    ->save('my_movie.wmv')

    // export to Amazon S3, converted in X264
    ->export()
    ->toDisk('s3')
    ->inFormat(new \FFMpeg\Format\Video\X264)
    ->save('my_movie.mkv');

    // you could even discard the 'toDisk()' method,
    // now the converted file will be saved to
    // the same disk as the source!
    ->export()
    ->inFormat(new FFMpeg\Format\Video\WebM)
    ->save('my_movie.webm')

    // optionally you could set the visibility
    // of the exported file
    ->export()
    ->inFormat(new FFMpeg\Format\Video\WebM)
    ->withVisibility('public')
    ->save('my_movie.webm')
```

As of version 7.0 you can open multiple inputs, even from different disks. This uses FFMpeg's `map` and `filter_complex` features. You can open multiple files by chaining the `open` method of by using an array. You can mix inputs from different disks.

```php
FFMpeg::open('video1.mp4')->open('video2.mp4');

FFMpeg::open(['video1.mp4', 'video2.mp4']);

FFMpeg::fromDisk('uploads')
    ->open('video1.mp4')
    ->fromDisk('archive')
    ->open('video2.mp4');
```

When you open multple inputs, you have to add mappings so FFMpeg knows how to handle them. This package provides a `addFormatOutputMapping` method which takes three parameters: the format, the output, and the output labels of the -filter_complex part. The output (2nd argument) should be an instanceof `\Pbmedia\LaravelFFMpeg\Filesystem\Media`. You can instantiate with the `make` method, call it with the name of the disk and the path.

This is an example [from the underlying library](https://github.com/PHP-FFMpeg/PHP-FFMpeg#base-usage).

*This code takes 2 input videos, stacks they horizontally in 1 output video and adds to this new video the audio from the first video. (It is impossible with simple filtergraph that has only 1 input and only 1 output).*

```php
FFMpeg::fromDisk('local')
    ->open(['video.mp4', 'video2.mp4'])
    ->export()
    ->addFilter('[0:v][1:v]', 'hstack', '[v]')  // $in, $parameters, $out
    ->addFormatOutputMapping(new X264, Media::make('local', 'stacked_video.mp4'), ['0:a', '[v]'])
    ->save();
```

Just like single inputs, you can also pass a callback to the `addFilter` method. This will give you an instance of `\FFMpeg\Filters\AdvancedMedia\ComplexFilters`:

```php
FFMpeg::open(['video.mp4', 'video2.mp4'])
    ->export()
    ->addFilter(function($filters) {
        // $filters->watermark(...);
    });
```

Create a frame from a video:

``` php
FFMpeg::fromDisk('videos')
    ->open('steve_howe.mp4')
    ->getFrameFromSeconds(10)
    ->export()
    ->toDisk('thumnails')
    ->save('FrameAt10sec.png');

// Instead of the 'getFrameFromSeconds()' method, you could
// also use the 'getFrameFromString()' or the
// 'getFrameFromTimecode()' methods:

$media = FFMpeg::open('steve_howe.mp4');
$frame = $media->getFrameFromString('00:00:13.37');

// or

$timecode = new FMpeg\Coordinate\TimeCode(...);
$frame = $media->getFrameFromTimecode($timecode);

```

With the ```Media``` class you can determinate the duration of a file:

``` php
$media = FFMpeg::open('wwdc_2006.mp4');

$durationInSeconds = $media->getDurationInSeconds(); // returns an int
$durationInMiliseconds = $media->getDurationInMiliseconds(); // returns a float
```

When opening or saving files from or to a remote disk, temporary files will be created on your server. After you're done exporting or processing these files, you could clean them up by calling the ```cleanupTemporaryFiles()``` method:

``` php
FFMpeg::cleanupTemporaryFiles();
```

## HLS

You can create a M3U8 playlist to do [HLS](https://en.wikipedia.org/wiki/HTTP_Live_Streaming).

``` php
$lowBitrate = (new X264)->setKiloBitrate(250);
$midBitrate = (new X264)->setKiloBitrate(500);
$highBitrate = (new X264)->setKiloBitrate(1000);

FFMpeg::fromDisk('videos')
    ->open('steve_howe.mp4')
    ->exportForHLS()
    ->setSegmentLength(10) // optional
    ->setKeyFrameInterval(48) // optional
    ->addFormat($lowBitrate)
    ->addFormat($midBitrate)
    ->addFormat($highBitrate)
    ->save('adaptive_steve.m3u8');
```

The ```addFormat``` method of the HLS exporter takes an optional second parameter which can be a callback method. This allows you to add different filters per format:

``` php
$lowBitrate = (new X264)->setKiloBitrate(250);
$highBitrate = (new X264)->setKiloBitrate(1000);

FFMpeg::open('steve_howe.mp4')
    ->exportForHLS()
    ->addFormat($lowBitrate, function($media) {
        $media->addFilter(function ($filters) {
            $filters->resize(new \FFMpeg\Coordinate\Dimension(640, 480));
        });
    })
    ->addFormat($highBitrate, function($media) {
        $media->addFilter(function ($filters) {
            $filters->resize(new \FFMpeg\Coordinate\Dimension(1280, 960));
        });
    })
    ->save('adaptive_steve.m3u8');
```

As of version 2.1.0 you can disable the sorting of the added formats as most players choose the first format as the default one.

``` php
$exporter = FFMpeg::open('steve_howe.mp4')
    ->exportForHLS()
    ->dontSortFormats();
```

## Advanced

The Media object you get when you 'open' a file, actually holds the Media object that belongs to the [underlying driver](https://github.com/PHP-FFMpeg/PHP-FFMpeg). It handles dynamic method calls as you can see [here](https://github.com/pascalbaljetmedia/laravel-ffmpeg/blob/master/src/Media.php#L114-L117). This way all methods of the underlying driver are still available to you.

```php
// This gives you an instance of Pbmedia\LaravelFFMpeg\Media
$media = FFMpeg::fromDisk('videos')->open('video.mp4');

// The 'getStreams' method will be called on the underlying Media object since
// it doesn't exists on this object.
$codec = $media->getStreams()->first()->get('codec_name');
```

If you want direct access to the underlying object, call the object as a function (invoke):

```php
// This gives you an instance of Pbmedia\LaravelFFMpeg\Media
$media = FFMpeg::fromDisk('videos')->open('video.mp4');

// This gives you an instance of FFMpeg\Media\MediaTypeInterface
$baseMedia = $media();
```

## Example app

Here's a blogpost that will help you get started with this package:

https://protone.media/en/blog/how-to-use-ffmpeg-in-your-laravel-projects

## Wiki

* [Custom filters](https://github.com/pascalbaljetmedia/laravel-ffmpeg/wiki/Custom-filters)
* [FFmpeg failed to execute command](https://github.com/pascalbaljetmedia/laravel-ffmpeg/wiki/FFmpeg-failed-to-execute-command)
* [Get the dimensions of a Video file](https://github.com/pascalbaljetmedia/laravel-ffmpeg/wiki/Get-the-dimensions-of-a-Video-file)
* [Monitoring the transcoding progress](https://github.com/pascalbaljetmedia/laravel-ffmpeg/wiki/Monitoring-the-transcoding-progress)
* [Unable to load FFProbe](https://github.com/pascalbaljetmedia/laravel-ffmpeg/wiki/Unable-to-load-FFProbe)

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Testing

``` bash
$ composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Other Laravel packages

* [`Laravel Analytics Event Tracking`](https://github.com/pascalbaljetmedia/laravel-analytics-event-tracking): Laravel package to easily send events to Google Analytics.
* [`Laravel Blade On Demand`](https://github.com/pascalbaljetmedia/laravel-blade-on-demand): Laravel package to compile Blade templates in memory.
* [`Laravel Paddle`](https://github.com/pascalbaljetmedia/laravel-paddle): Paddle.com API integration for Laravel with support for webhooks/events.
* [`Laravel Verify New Email`](https://github.com/pascalbaljetmedia/laravel-verify-new-email): This package adds support for verifying new email addresses: when a user updates its email address, it won't replace the old one until the new one is verified.
* [`Laravel WebDAV`](https://github.com/pascalbaljetmedia/laravel-webdav): WebDAV driver for Laravel's Filesystem.
## Security

If you discover any security related issues, please email code@protone.media instead of using the issue tracker. Please do not email any questions, open an issue if you have a question.

## Credits

- [Pascal Baljet](https://github.com/pascalbaljet)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
