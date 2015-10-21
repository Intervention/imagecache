<?php

namespace Intervention\Image;

use Illuminate\Contracts\Filesystem\FileNotFoundException;

class Filesystem
{
    /**
     * Array of paths to search in
     *
     * @var array
     */
    protected $paths;

    /**
     * @param array $paths
     */
    public function __construct(array $paths)
    {
        $this->paths = $paths;
    }

    /**
     * Returns full image path from given filename
     *
     * @param  string $filename
     * @return string
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function get($path)
    {
        return file_get_contents($this->find($path));
    }

    /**
     * Get the file's last modification time.
     *
     * @param  string  $path
     * @return int
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function lastModified($path)
    {
        return filemtime($this->find($path));
    }

    /**
     * Attempt to find file in defined paths
     *
     * @param $filename
     * @return string
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function find($filename)
    {
        // find file
        foreach ($this->paths as $path) {
            // don't allow '..' in filenames
            $image_path = $path.'/'.str_replace('..', '', $filename);
            if (file_exists($image_path) && is_file($image_path)) {
                // file found
                return $image_path;
            }
        }

        throw new FileNotFoundException;
    }
}
