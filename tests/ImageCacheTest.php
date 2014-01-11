<?php

use Intervention\Image\ImageCache;

class ImageCacheTest extends PHPUnit_Framework_Testcase
{
    public function emptyCacheDirectory()
    {
        $files = new \Illuminate\Filesystem\Filesystem;

        foreach ($files->directories('storage/cache') as $directory)
        {
            $files->deleteDirectory($directory);
        }
    }
    
    public function tearDown()
    {
        $this->emptyCacheDirectory();
    }

    public function testConstructor()
    {
        $img = new ImageCache;
        $this->assertInstanceOf('Intervention\Image\ImageCache', $img);
    }

    public function testCacheIsAvailable()
    {
        $img = new ImageCache;
        $this->assertInstanceOf('Illuminate\Cache\Repository', $img->cache);
    }

    public function testConstructorWithInjection()
    {
        // add new default cache
        $storage = new \Illuminate\Cache\FileStore(new \Illuminate\Filesystem\Filesystem, __DIR__.'/mycache');
        $cache = new \Illuminate\Cache\Repository($storage);

        $img = new ImageCache($cache);
        $this->assertInstanceOf('Intervention\Image\ImageCache', $img);
        $this->assertInstanceOf('Illuminate\Cache\Repository', $img->cache);
    }

    public function testMagicMethodCalls()
    {
        $img = new ImageCache;
        
        $img->test1(1, 2, 3);
        $img->test2(null);
        $img->test3(array(1, 2, 3));

        $this->assertInternalType('array', $img->calls);
        $this->assertEquals(count($img->calls), 3);
        $this->assertInternalType('string', $img->calls[0]['name']);
        $this->assertInternalType('string', $img->calls[1]['name']);
        $this->assertInternalType('string', $img->calls[2]['name']);
        $this->assertInternalType('array', $img->calls[0]['arguments']);
        $this->assertInternalType('array', $img->calls[1]['arguments']);
        $this->assertInternalType('array', $img->calls[2]['arguments']);
        $this->assertEquals($img->calls[0]['name'], 'test1');
        $this->assertEquals($img->calls[1]['name'], 'test2');
        $this->assertEquals($img->calls[2]['name'], 'test3');
        $this->assertEquals($img->calls[0]['arguments'][0], 1);
        $this->assertEquals($img->calls[0]['arguments'][1], 2);
        $this->assertEquals($img->calls[0]['arguments'][2], 3);
        $this->assertTrue(is_null($img->calls[1]['arguments'][0]));
        $this->assertInternalType('array', $img->calls[2]['arguments'][0]);
        $this->assertEquals($img->calls[2]['arguments'][0][0], 1);
        $this->assertEquals($img->calls[2]['arguments'][0][1], 2);
        $this->assertEquals($img->calls[2]['arguments'][0][2], 3);
    }

    public function testChecksum()
    {
        // checksum of empty image
        $sum = '40cd750bba9870f18aada2478b24840a';
        $img = new ImageCache;
        $this->assertEquals($img->checksum(), $sum);

        // checksum of test image resized to 300x200
        $sum = '243c7c0dd9e7328697dfc7d874bb9103';
        $img = new ImageCache;
        $img->open('public/test.jpg');
        $img->resize(300, 200);
        $this->assertEquals($img->checksum(), $sum);
    }

    public function testProcess()
    {
        $img = new ImageCache;
        $img->open('public/test.jpg');
        $img->resize(300, 200);
        $result = $img->process();

        $this->assertEquals(count($img->calls), 0);
        $this->assertInstanceOf('Intervention\Image\Image', $result);
        $this->assertInternalType('int', $result->width);
        $this->assertInternalType('int', $result->height);
        $this->assertEquals($result->width, 300);
        $this->assertEquals($result->height, 200);
        $this->assertEquals($result->dirname, 'public');
        $this->assertEquals($result->basename, 'test.jpg');
    }

    public function testStaticCalls()
    {
        $image = ImageCache::make('public/test.jpg')->resize(300, 200)->process();
        $this->assertInstanceOf('Intervention\Image\Image', $image);
        $this->assertInternalType('int', $image->width);
        $this->assertInternalType('int', $image->height);
        $this->assertEquals($image->width, 300);
        $this->assertEquals($image->height, 200);
        $this->assertEquals($image->dirname, 'public');
        $this->assertEquals($image->basename, 'test.jpg');

        $image = ImageCache::canvas(800, 600)->resize(300, 200)->process();
        $this->assertInstanceOf('Intervention\Image\Image', $image);
        $this->assertInternalType('int', $image->width);
        $this->assertInternalType('int', $image->height);
        $this->assertEquals($image->width, 300);
        $this->assertEquals($image->height, 200);
        $this->assertEquals($image->dirname, null);
        $this->assertEquals($image->basename, null);

        $image = ImageCache::canvas(800, 600, 'b53717')->resize(300, 200)->process();
        $this->assertInstanceOf('Intervention\Image\Image', $image);
        $this->assertInternalType('int', $image->width);
        $this->assertInternalType('int', $image->height);
        $this->assertEquals($image->width, 300);
        $this->assertEquals($image->height, 200);
        $this->assertEquals($image->dirname, null);
        $this->assertEquals($image->basename, null);
        $this->assertEquals($image->pickColor(10, 10, 'hex'), '#b53717');
    }

    public function testGetImageFromCache()
    {
        // put image into cache
        $image = ImageCache::make('public/test.jpg')->resize(300, 200);
        $key = $image->checksum();
        $value = (string) $image->process();
        $image->cache->put($key, $value, 5);

        // call get method (must return image from cache)
        $image = ImageCache::make('public/test.jpg')->resize(300, 200)->get(5, true);
        $this->assertInternalType('int', $image->width);
        $this->assertInternalType('int', $image->height);
        $this->assertEquals($image->width, 300);
        $this->assertEquals($image->height, 200);
        $this->assertEquals(get_class($image), 'Intervention\Image\CachedImage');
        // encoded is only set if image is processed by GD, if from cache it is
        // not processed so it should be null
        $this->assertEquals(is_null($image->encoded), true);
    }

    public function testGetImageNotFromCache()
    {
        // empty cache directory
        $this->emptyCacheDirectory();

        // call get method (must return image directly)
        $image = ImageCache::make('public/test.jpg')->resize(300, 200)->get(5, true);
        $this->assertInternalType('int', $image->width);
        $this->assertInternalType('int', $image->height);
        $this->assertEquals($image->width, 300);
        $this->assertEquals($image->height, 200);
        $this->assertEquals(get_class($image), 'Intervention\Image\CachedImage');
        // encoded is only set if image is processed by GD, image should not
        // come from cache so encoded should not be null
        $this->assertEquals(is_null($image->encoded), false);
        
    }
}
