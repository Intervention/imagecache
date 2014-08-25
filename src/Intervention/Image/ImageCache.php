<?php

namespace Intervention\Image;

use Exception;
use Jeremeamia\SuperClosure\SerializableClosure;
use \Illuminate\Cache\Repository as Cache;

class ImageCache
{
    /**
     * Cache lifetime in minutes
     * 
     * @var integer
     */
    public $lifetime = 5;

    /**
     * History of name and arguments of calls performed on image
     * 
     * @var array
     */
    public $calls = array();

    /**
     * Processed Image
     * 
     * @var Intervention\Image\Image
     */
    public $image;

    /**
     * Intervention Image Manager
     *
     * @var Intervention\Image\ImageManager
     */
    public $manager;

    /**
     * Illuminate Cache Manager
     *
     * @var Illuminate\Cache\CacheManager
     */
    public $cache;

    /**
     * Create a new instance
     */
    public function __construct(ImageManager $manager = null, Cache $cache = null)
    {
        $this->manager = $manager ? $manager : new ImageManager;
        
        if (is_null($cache)) {
            
            // get laravel app
            $app = function_exists('app') ? app() : null;

            // if laravel app cache exists
            if (is_a($app, 'Illuminate\Foundation\Application')) {
                $cache = $app->make('cache');
            }

            if (is_a($cache, 'Illuminate\Cache\CacheManager')) {

                // add laravel cache
                $this->cache = $cache;

            } else {
                    
                // define path in filesystem
                if (isset($manager->config['cache']['path'])) {
                    $path = $manager->config['cache']['path'];
                } else {
                    $path = __DIR__.'/../../../storage/cache';
                }

                // create new default cache
                $filesystem = new \Illuminate\Filesystem\Filesystem;
                $storage = new \Illuminate\Cache\FileStore($filesystem, $path);
                $this->cache = new \Illuminate\Cache\Repository($storage);
            }

        } else {
            
            $this->cache = $cache;
        }
    }

    /**
     * Magic method to capture action calls
     *
     * @param  String $name
     * @param  Array $arguments
     * @return Intervention\Image\ImageCache
     */
    public function __call($name, $arguments)
    {
        $this->registerCall($name, $arguments);

        return $this;
    }

    /**
     * Returns checksum of current image state
     * 
     * @return string
     */
    public function checksum()
    {
        return md5(serialize($this->getSanitizedCalls()));
    }

    /**
     * Register static call for later use
     *
     * @param  string $name
     * @param  array  $arguments
     * @return void
     */
    private function registerCall($name, $arguments)
    {
        $this->calls[] = array('name' => $name, 'arguments' => $arguments);
    }

    /**
     * Clears history of calls
     * 
     * @return void
     */
    private function clearCalls()
    {
        $this->calls = array();
    }

    /**
     * Return unprocessed calls
     * 
     * @return array
     */
    private function getCalls()
    {
        return count($this->calls) ? $this->calls : array();
    }

    /**
     * Replace Closures in arguments with SerializableClosure
     *
     * @return array
     */
    private function getSanitizedCalls()
    {
        $calls = $this->getCalls();

        foreach ($calls as $i => $call) {
            foreach ($call['arguments'] as $j => $argument) {
                if (is_a($argument, 'Closure')) {
                    $calls[$i]['arguments'][$j] = new SerializableClosure($argument);
                }
            }
        }

        return $calls;
    }

    /**
     * Process call on current image
     * 
     * @param  array $call
     * @return void
     */
    private function processCall($call)
    {
        $this->image = call_user_func_array(array($this->image, $call['name']), $call['arguments']);
    }

    /**
     * Process all saved image calls on Image object
     * 
     * @return Intervention\Image\Image
     */
    public function process()
    {
        // first call on manager
        $this->image = $this->manager;

        // process calls on image
        foreach ($this->getCalls() as $call) {
            $this->processCall($call);
        }

        // append checksum to image
        $this->image->cachekey = $this->checksum();

        $this->clearCalls();

        return $this->image;
    }

    /**
     * Get image either from cache or directly processed
     * and save image in cache if it's not saved yet
     * 
     * @param  int  $lifetime
     * @param  bool $returnObj
     * @return mixed
     */
    public function get($lifetime = null, $returnObj = false)
    {
        $lifetime = is_null($lifetime) ? $this->lifetime : intval($lifetime);

        $key = $this->checksum();

        // try to get image from cache
        $cachedImageData = $this->cache->get($key);

        // if imagedata exists in cache
        if ($cachedImageData) {

            // transform into image-object
            if ($returnObj) {
                $image = $this->manager->make($cachedImageData);
                $image->cachekey = $key;
                return $image;
            }
        
            // return raw data
            return $cachedImageData;

        } else {

            // process image data
            $image = $this->process();

            // encode image data only if image is not encoded yet
            $image = $image->encoded ? $image->encoded : $image->encode();

            // save to cache...
            $this->cache->put($key, (string) $image, $lifetime);

            // return processed image
            return $returnObj ? $image : (string) $image;
        }
    }
}
