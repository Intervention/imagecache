<?php

use Intervention\Image\ImageCache;

class ImageCacheTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        $this->emptyCacheDirectory();
        Mockery::close();
    }

    public function emptyCacheDirectory()
    {
        $files = new \Illuminate\Filesystem\Filesystem;

        foreach ($files->directories('storage/cache') as $directory)
        {
            $files->deleteDirectory($directory);
        }
    }

    public function testConstructor()
    {
        $img = new ImageCache;
        $this->assertInstanceOf('Intervention\Image\ImageCache', $img);
        $this->assertInstanceOf('Intervention\Image\ImageManager', $img->manager);
        $this->assertInstanceOf('Illuminate\Cache\Repository', $img->cache);
    }

    public function testConstructorWithInjection()
    {
        // add new default cache
        $manager = Mockery::mock('\Intervention\Image\ImageManager');
        $cache = Mockery::mock('\Illuminate\Cache\Repository');

        $img = new ImageCache($manager, $cache);
        $this->assertInstanceOf('Intervention\Image\ImageCache', $img);
        $this->assertInstanceOf('Intervention\Image\ImageManager', $img->manager);
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
        $sum = 'ad7b81dea42cf2ef7525c274471e3ce6';
        $img = new ImageCache;
        $this->assertEquals($sum, $img->checksum());

        // checksum of test image resized to 300x200
        $sum = '9b3c716836cb438c4619eb0452740019';
        $img = new ImageCache;
        $img->make('foo/bar.jpg');
        $img->resize(300, 200);
        $this->assertEquals($sum, $img->checksum());
    }

    public function testChecksumWithClosure()
    {
        // closure must be serializable
        $sum = 'e869cab5431815e67583b7c8cf3b657a';
        $img = new ImageCache;
        $img->canvas(300, 200, 'fff');
        $img->text('foo', 0, 0, function($font) {
            $font->valign('top');
            $font->size(32);
        });
        $this->assertEquals($img->checksum(), $sum);

        // checksum must differ, if values in closure change
        $sum = 'ec538f3194720c4657edde6af8145730';
        $img = new ImageCache;
        $img->canvas(300, 200, 'fff');
        $img->text('foo', 0, 0, function($font) {
            $font->valign('top');
            $font->size(30);
        });
        $this->assertEquals($img->checksum(), $sum);
    }

    public function testChecksumWithProperty()
    {
        // checksum of test image resized to 300x200
        $sum = '9b3c716836cb438c4619eb0452740019';
        $img = new ImageCache;
        $img->make('foo/bar.jpg');
        $img->resize(300, 200);
        $this->assertEquals($sum, $img->checksum());

        // different checksum with property
        $sum = '299243f98e2de9939ea59e5884ab1b8b';
        $img = new ImageCache;
        $img->setProperty('foo', 'bar');
        $img->make('foo/bar.jpg');
        $img->resize(300, 200);
        $this->assertEquals($sum, $img->checksum());
    }

    public function testProcess()
    {
        $image = Mockery::mock('\Intervention\Image\Image');
        $image->shouldReceive('resize')->with(300, 200)->once()->andReturn($image);
        $image->shouldReceive('blur')->with(2)->once()->andReturn($image);
        $manager = Mockery::mock('\Intervention\Image\ImageManager');
        $manager->shouldReceive('make')->with('foo/bar.jpg')->once()->andReturn($image);
        $cache = Mockery::mock('\Illuminate\Cache\Repository');

        $img = new ImageCache($manager, $cache);
        $img->make('foo/bar.jpg');
        $img->resize(300, 200);
        $img->blur(2);
        $result = $img->process();

        $this->assertEquals(count($img->calls), 0);
        $this->assertInstanceOf('Intervention\Image\Image', $result);        
        $this->assertEquals('e795d413cf6598f49a8e773ce2e07589', $result->cachekey);
    }

    public function testGetImageFromCache()
    {
        $lifetime = 12;
        $checksum = '2fff960136929390427f9409eac34c42';
        $imagedata = 'mocked image data';

        $manager = Mockery::mock('\Intervention\Image\ImageManager');
        $cache = Mockery::mock('\Illuminate\Cache\Repository');
        $cache->shouldReceive('get')->with($checksum)->once()->andReturn($imagedata);
        $img = new ImageCache($manager, $cache);
        $img->make('foo/bar.jpg');
        $img->resize(100, 150);
        $result = $img->get($lifetime);

        $this->assertEquals($imagedata, $result);
    }

    public function testGetImageFromCacheAsObject()
    {
        $lifetime = 12;
        $checksum = '2fff960136929390427f9409eac34c42';
        $imagedata = 'mocked image data';

        $image = Mockery::mock('\Intervention\Image\Image');

        $manager = Mockery::mock('\Intervention\Image\ImageManager');
        $manager->shouldReceive('make')->with($imagedata)->once()->andReturn($image);
        $cache = Mockery::mock('\Illuminate\Cache\Repository');
        $cache->shouldReceive('get')->with($checksum)->once()->andReturn($imagedata);
        $img = new ImageCache($manager, $cache);
        $img->make('foo/bar.jpg');
        $img->resize(100, 150);
        $result = $img->get($lifetime, true);

        $this->assertInstanceOf('Intervention\Image\Image', $result);
    }

    public function testGetImageNotFromCache()
    {
        $lifetime = 12;
        $checksum = '2fff960136929390427f9409eac34c42';
        $imagedata = 'mocked image data';

        $image = Mockery::mock('\Intervention\Image\Image');
        $image->shouldReceive('resize')->with(100, 150)->once()->andReturn($image);
        $image->shouldReceive('encode')->with()->once()->andReturn($imagedata);

        $manager = Mockery::mock('\Intervention\Image\ImageManager');
        $manager->shouldReceive('make')->with('foo/bar.jpg')->once()->andReturn($image);
        $cache = Mockery::mock('\Illuminate\Cache\Repository');
        $cache->shouldReceive('get')->with($checksum)->once()->andReturn(false);
        $cache->shouldReceive('put')->with($checksum, $imagedata, $lifetime)->once()->andReturn(false);

        $img = new ImageCache($manager, $cache);
        $img->make('foo/bar.jpg');
        $img->resize(100, 150);
        $result = $img->get($lifetime);

        $this->assertEquals($imagedata, $result);
    }

    public function testGetImageNotFromCacheAsObject()
    {
        $lifetime = 12;
        $checksum = '2fff960136929390427f9409eac34c42';
        $imagedata = 'mocked image data';

        $image = Mockery::mock('\Intervention\Image\Image');
        $image->shouldReceive('resize')->with(100, 150)->once()->andReturn($image);
        $image->shouldReceive('encode')->with()->once()->andReturn($imagedata);

        $manager = Mockery::mock('\Intervention\Image\ImageManager');
        $manager->shouldReceive('make')->with('foo/bar.jpg')->once()->andReturn($image);
        $cache = Mockery::mock('\Illuminate\Cache\Repository');
        $cache->shouldReceive('get')->with($checksum)->once()->andReturn(false);
        $cache->shouldReceive('put')->with($checksum, $imagedata, $lifetime)->once()->andReturn(false);

        $img = new ImageCache($manager, $cache);
        $img->make('foo/bar.jpg');
        $img->resize(100, 150);
        $result = $img->get($lifetime, true);

        $this->assertEquals($image, $result);
    }

    public function testOriginalFileChanged()
    {
        $filename = __DIR__.'/files/foo.bar';

        // create tmp file
        touch($filename);

        // get original checksum
        $img = new ImageCache;
        $img->make($filename);
        $img->resize(300, 200);
        $checksum_original = $img->checksum();

        // get modified checksum
        clearstatcache();
        $modified = touch($filename, 10);

        // get modified checksum
        $img = new ImageCache;
        $img->make($filename);
        $img->resize(300, 200);
        $checksum_modified = $img->checksum();

        // delete tmp file
        unlink($filename);

        $this->assertTrue($modified);
        $this->assertNotEquals($checksum_original, $checksum_modified);
    }

    public function testBinaryInput()
    {
        $data = file_get_contents(__DIR__.'/files/test.png');
        $img = new ImageCache;
        $img->make($data);
    }
}
