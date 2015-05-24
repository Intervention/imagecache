<?php

use Intervention\Image\Image;
use Intervention\Image\CachedImage;

class CachedImageTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        Mockery::close();
    }

    public function testSetFromOriginal()
    {
        $image = $this->getTestImage();
        $cachedImage = new CachedImage;
        $cachedImage->setFromOriginal($image, 'foo-key');

        $this->assertInstanceOf('\Intervention\Image\AbstractDriver', $cachedImage->getDriver());
        $this->assertEquals('mock', $cachedImage->getCore());
        $this->assertEquals('image/png', $cachedImage->mime);
        $this->assertEquals('./tmp', $cachedImage->dirname);
        $this->assertEquals('foo.png', $cachedImage->basename);
        $this->assertEquals('png', $cachedImage->extension);
        $this->assertEquals('foo', $cachedImage->filename);
        $this->assertEquals(array(), $cachedImage->getBackups());
        $this->assertEquals('', $cachedImage->encoded);
        $this->assertEquals('foo-key', $cachedImage->cachekey);
    }

    private function getTestImage()
    {
        $driver = Mockery::mock('\Intervention\Image\AbstractDriver');
        $image = new Image($driver, 'mock');
        $image->mime = 'image/png';
        $image->dirname = './tmp';
        $image->basename = 'foo.png';
        $image->extension = 'png';
        $image->filename = 'foo';

        return $image;
    }
}
