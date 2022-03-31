<?php

namespace App\ShareFile;

abstract class Entity implements \ArrayAccess, \JsonSerializable
{
    /**
     * The data.
     *
     * @var array
     */
    private $data;

    public function __construct(array $data)
    {
        $this->data = $data;
        $this->build($data);
    }

    public function __get($name)
    {
        if (\array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }

        throw new \OutOfBoundsException('Undefined property: '.$name);
    }

    public function __set($name, $value)
    {
        throw new \RuntimeException(__METHOD__.' not implemented');
    }

    public function offsetExists($offset)
    {
        return \array_key_exists($offset, $this->data);
    }

    public function offsetGet($offset)
    {
        return $this->data[$offset];
    }

    public function offsetSet($offset, $value)
    {
        throw new \RuntimeException(static::class.' is immutable');
    }

    public function offsetUnset($offset)
    {
        throw new \RuntimeException(static::class.' is immutable');
    }

    public function jsonSerialize()
    {
        return $this->data;
    }

    public function getData()
    {
        return $this->data;
    }

    protected function build(array $data)
    {
    }
}
