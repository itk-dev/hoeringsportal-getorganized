<?php

namespace App\GetOrganized;

/**
 * @implements \ArrayAccess<string, mixed>
 */
abstract class Entity implements \ArrayAccess, \JsonSerializable
{
    public function __construct(
        private readonly array $data,
    ) {
        $this->build($this->data);
    }

    public function __get(string $name): mixed
    {
        if (\array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }

        throw new \OutOfBoundsException(sprintf('Undefined property: %s', $name));
    }

    public function __set(string $name, mixed $value): void
    {
        throw new \RuntimeException(sprintf('%s not implemented', __METHOD__));
    }

    public function offsetExists($offset): bool
    {
        return \array_key_exists($offset, $this->data);
    }

    public function offsetGet($offset): mixed
    {
        return $this->data[$offset];
    }

    public function offsetSet($offset, $value): void
    {
        throw new \RuntimeException(sprintf('%s is immutable', static::class));
    }

    public function offsetUnset($offset): void
    {
        throw new \RuntimeException(sprintf('%s is immutable', static::class));
    }

    public function jsonSerialize(): mixed
    {
        return $this->data;
    }

    public function getData(): array
    {
        return $this->data;
    }

    protected function build(array $data): void
    {
    }
}
