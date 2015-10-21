<?php

namespace Intervention\Image;

use Closure;
use Intervention\Image\Exception\RuntimeException;
use Intervention\Image\ImageManager;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Response as IlluminateResponse;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Config;

class ImageCacheController extends BaseController
{
    /**
     * Filesystem storage
     *
     * @var \Illuminate\Contracts\Filesystem\Filesystem
     */
    private $filesystem;

    /**
     * Get HTTP response of either original image file or
     * template applied file.
     *
     * @param  string $template
     * @param  string $filename
     * @return Illuminate\Http\Response
     */
    public function getResponse($template, $filename)
    {
        switch (strtolower($template)) {
            case 'original':
                return $this->getOriginal($filename);

            case 'download':
                return $this->getDownload($filename);
            
            default:
                return $this->getImage($template, $filename);
        }
    }

    /**
     * Get HTTP response of template applied image file
     *
     * @param  string $template
     * @param  string $filename
     * @return Illuminate\Http\Response
     */
    public function getImage($template, $filename)
    {
        $template = $this->getTemplate($template);
        list($file, $mtime) = $this->getImageFileData($filename);

        // image manipulation based on callback
        $manager = new ImageManager(Config::get('image'));
        $content = $manager->cache(function ($image) use ($template, $file, $mtime) {

            if ($template instanceof Closure) {
                // build from closure callback template
                $template($image->make($file, $mtime));
            } else {
                // build from filter template
                $image->make($file, $mtime)->filter($template);
            }
            
        }, config('imagecache.lifetime'));

        return $this->buildResponse($content);
    }

    /**
     * Get HTTP response of original image file
     *
     * @param  string $filename
     * @return Illuminate\Http\Response
     */
    public function getOriginal($filename)
    {
        $path = $this->getImagePath($filename);

        return $this->buildResponse(file_get_contents($path));
    }

    /**
     * Get HTTP response of original image as download
     *
     * @param  string $filename
     * @return Illuminate\Http\Response
     */
    public function getDownload($filename)
    {
        $response = $this->getOriginal($filename);

        return $response->header(
            'Content-Disposition',
            'attachment; filename=' . $filename
        );
    }

    /**
     * Returns corresponding template object from given template name
     *
     * @param  string $template
     * @return mixed
     */
    private function getTemplate($template)
    {
        $template = config("imagecache.templates.{$template}");

        switch (true) {
            // closure template found
            case is_callable($template):
                return $template;

            // filter template found
            case class_exists($template):
                return new $template;
            
            default:
                // template not found
                abort(404);
                break;
        }
    }

    /**
     * Return image file and modified date from given filename
     *
     * @return array
     */
    private function getImageFileData($filename)
    {
        try {
            return [$this->getFilesystem()->get($filename), $this->getFilesystem()->lastModified($filename)];
        } catch (FileNotFoundException $e) {
            abort(404);
        }
    }

    /**
     * Builds HTTP response from given image data
     *
     * @param  string $content 
     * @return Illuminate\Http\Response
     */
    private function buildResponse($content)
    {
        // define mime type
        $mime = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $content);

        // return http response
        return new IlluminateResponse($content, 200, array(
            'Content-Type' => $mime,
            'Cache-Control' => 'max-age='.(config('imagecache.lifetime')*60).', public',
            'Etag' => md5($content)
        ));
    }

    /**
     * Get Filesystem storage
     *
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    private function getFilesystem()
    {
        if ($this->filesystem) {
            return $this->filesystem;
        }

        $driver = config('imagecache.driver', 'native');

        if ($driver == 'native') {
            $filesystem = new Filesystem(config('imagecache.paths'));
        } else {
            $filesystem = app('filesystem')->disk($driver);
        }

        if (!$filesystem) {
            throw new RuntimeException('Cannot instantiate filesystem');
        }

        return $this->filesystem = $filesystem;
    }
}
