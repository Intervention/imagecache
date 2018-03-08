<?php

namespace Intervention\Image;

use Closure;
use Intervention\Image\ImageManager;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Response as IlluminateResponse;
use Config;
use Storage;

class ImageCacheController extends BaseController
{
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
        $path = $this->getImagePath($filename);

        // image manipulation based on callback
        $manager = new ImageManager(Config::get('image'));
        $content = $manager->cache(function ($image) use ($template, $path) {

            if ($template instanceof Closure) {
                // build from closure callback template
                $template($image->make(self::getImageData($path)));
            } else {
                // build from filter template
                $image->make(self::getImageData($path))->filter($template);
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

        return $this->buildResponse(self::getImageData($path));
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
    protected function getTemplate($template)
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
     * Returns full image path from given filename
     *
     * @param  string $filename
     * @return string
     */
    protected function getImagePath($filename)
    {
        // find file
        foreach (config('imagecache.paths') as $path) {
            list($fs, $dir) = self::parsePath($path);
            $disk = Storage::disk($fs);
            // don't allow '..' in filenames
            $image_path = $dir.'/'.str_replace('..', '', $filename);
            if ($disk->exists($image_path)) {
                // file found
                return "$fs:$image_path";
            }
        }

        // file not found
        abort(404);
    }

    /**
     * Parse a path string.
     *
     * @param path a path string
     * @return array the path components [ 0 => disk name, 1 => file path ]
     */
    private static function parsePath($path)
    {
        $fs = 'local';
        $dir = $path;
        if (preg_match('/(.*):(.*)/', $path, $matches) === 1) {
            list(, $fs, $dir) = $matches;
        }

        return [$fs, $dir];
    }

    /**
     * Load the image data via the Storage subsystem.
     *
     * @param image_path the path to the image from getImagePath()
     */
    private static function getImageData($image_path)
    {
        list($fs, $path) = self::parsePath($image_path);

        return Storage::disk($fs)->get($path);
    }

    /**
     * Builds HTTP response from given image data
     *
     * @param  string $content
     * @return Illuminate\Http\Response
     */
    protected function buildResponse($content)
    {
        // define mime type
        $mime = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $content);

        // respond with 304 not modified if browser has the image cached
        $etag = md5($content);
        $not_modified = isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag;
        $content = $not_modified ? NULL : $content;
        $status_code = $not_modified ? 304 : 200;

        // return http response
        return new IlluminateResponse($content, $status_code, array(
            'Content-Type' => $mime,
            'Cache-Control' => 'max-age='.(config('imagecache.lifetime')*60).', public',
            'Etag' => $etag
        ));
    }
}
