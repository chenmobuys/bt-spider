<?php

namespace BTSpider\Support;

use ArrayAccess;
use JsonSerializable;

class Config implements ArrayAccess, JsonSerializable
{
    /**
     * @var array $items;
     */
    protected $items = [];

    /**
     * Create a new configuration repository.
     *
     * @param  array  $items
     * @return void
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has($key): bool
    {
        return array_key_exists($key, $this->items);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public function get($key, $default = null)
    {
        if ($this->has($key)) {
            return $this->items[$key];
        }

        if (strpos($key, '.') !== false) {
            $value = $this->items;
            foreach (explode('.', $key) as $segment) {
                if (is_array($value) && array_key_exists($segment, $value)) {
                    $value = $value[$segment];
                } else {
                    return $default;
                }
            }
            return $value;
        }

        return $default;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set($key, $value): void
    {
        $segments = explode('.', $key);

        $temp = &$this->items;
        foreach ($segments as $segment) {
            if (!isset($temp[$segment]) || !is_array($temp[$segment])) {
                $temp[$segment] = [];
            }
            $temp = &$temp[$segment];
        }
        $temp = $value;
    }

    /**
     * @param array $items
     * @return void
     */
    public function merge($items): void
    {
        $this->items = array_merge($this->items, $items);
    }

    /**
     * @return array
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Determine if the given configuration option exists.
     *
     * @param  string  $key
     * @return bool
     */
    public function offsetExists($key): bool
    {
        return $this->has($key);
    }

    /**
     * Get a configuration option.
     *
     * @param  string  $key
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->get($key);
    }

    /**
     * Set a configuration option.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($key, $value): void
    {
        $this->set($key, $value);
    }

    /**
     * Unset a configuration option.
     *
     * @param  string  $key
     * @return void
     */
    public function offsetUnset($key): void
    {
        $this->set($key, null);
    }

    public function jsonSerialize()
    {
        return json_encode($this->all());
    }
}
