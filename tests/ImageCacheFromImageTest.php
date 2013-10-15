<?php

use Intervention\Image\Image;

class ImageCacheFromImageTest extends PHPUnit_Framework_Testcase
{
    public function tearDown()
    {
        $this->emptyCacheDirectory();
    }

    public function emptyCacheDirectory()
    {
        $files = new \Illuminate\Filesystem\Filesystem;

        foreach ($files->directories('storage/cache') as $directory)
        {
            $files->deleteDirectory($directory);
        }
    }

    public function testStaticCall()
    {
        $img = Image::cache();
        $this->assertInternalType('string', $img);
    }

    public function testStaticCallReturnObject()
    {
        $img = Image::cache(null, 5, true);

        // must be empty \Intervention\Image\Image
        $this->assertInstanceOf('Intervention\Image\Image', $img);
        $this->assertInternalType('int', $img->width);
        $this->assertInternalType('int', $img->height);
        $this->assertEquals($img->width, 1);
        $this->assertEquals($img->height, 1);
    }

    public function testStaticCallWithCallback()
    {
        $img = Image::cache(function($image) {
            return $image->make('public/test.jpg')->resize(320, 200)->greyscale();
        });

        // must be empty \Intervention\Image\Image
        $this->assertInternalType('string', $img);
    }

    public function testStaticCallWithCallbackReturnObject()
    {
        $img = Image::cache(function($image) {
            return $image->make('public/test.jpg')->resize(320, 200)->greyscale();
        }, 5, true);

        // must be empty \Intervention\Image\Image
        $this->assertInstanceOf('Intervention\Image\Image', $img);
        $this->assertInternalType('int', $img->width);
        $this->assertInternalType('int', $img->height);
        $this->assertEquals($img->width, 320);
        $this->assertEquals($img->height, 200);
    }

    public function testStaticCallWithCallbackUsePath()
    {
        $path = 'public/test.jpg';

        $img = Image::cache(function($image) use ($path) {
            return $image->make($path);
        }, 10, true);

        $this->assertInstanceOf('Intervention\Image\Image', $img);
    }

    public function testStaticCallWithCallbackUseResource()
    {
        $resource = imagecreatefromjpeg('public/test.jpg');

        $img = Image::cache(function($image) use ($resource) {
            return $image->make($resource);
        }, 10, true);

        $this->assertInstanceOf('Intervention\Image\Image', $img);
    }

    public function testStaticCallWithCallbackUseBinary()
    {
        $data = file_get_contents('public/test.jpg');

        $img = Image::cache(function($image) use ($data) {
            return $image->make($data);
        }, 10, true);

        $this->assertInstanceOf('Intervention\Image\Image', $img);
    }
}
