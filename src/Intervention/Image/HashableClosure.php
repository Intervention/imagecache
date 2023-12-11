<?php

namespace Intervention\Image;

use Closure;
use Laravel\SerializableClosure\UnsignedSerializableClosure;

class HashableClosure
{
    /**
     * Original closure
     *
     * @var \Laravel\SerializableClosure\UnsignedSerializableClosure
     */
    protected $closure;

    /**
     * Create new instance
     *
     * @param Closure $closure
     */
    public function __construct(Closure $closure)
    {
        $this->setClosure($closure);
    }

    /**
     * Set closure for hashing
     *
     * @param Closure $closure
     */
    public function setClosure(Closure $closure)
    {
        $closure = new UnsignedSerializableClosure($closure);

        $this->closure = $closure;

        return $this;
    }

    /**
     * Get current closure
     *
     * @return \Laravel\SerializableClosure\UnsignedSerializableClosure
     */
    public function getClosure()
    {
        return $this->closure;
    }

    /**
     * Get hash of current closure
     *
     * This method uses "laravel/serializable-closure" to serialize the closure. "laravel/serializable-closure",
     * however, adds a identifier by "spl_object_hash" to each serialize
     * call, making it impossible to create unique hashes. This method
     * removes this identifier and builds the hash afterwards.
     *
     * @return string
     */
    public function getHash()
    {
        $serializable = $this->closure->__serialize();
        $data = $serializable['serializable']->__serialize();

        unset($data['self']); // unset identifier added by spl_object_hash

        return md5(serialize($data));
    }
}
