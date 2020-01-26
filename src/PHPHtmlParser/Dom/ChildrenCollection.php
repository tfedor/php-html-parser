<?php

declare(strict_types=1);

namespace PHPHtmlParser\Dom;

class ChildrenCollection implements \ArrayAccess, \Iterator, \Countable {

    private $items = [];
    private $index = -1;

    private $first = null;
    private $last = null;

    public function __construct() {
    }

    public function getFirstKey() {
        return $this->first;
    }

    public function getLastKey() {
        return $this->last;
    }

    public function setNext($key, $nextKey) {
        $this->items[$key]['next'] = $nextKey;
    }

    public function setPrev($key, $prevKey) {
        $this->items[$key]['prev'] = $prevKey;
    }

    // Iterator

    public function current() {
        return $this->items[$this->index];
    }

    public function next() {
        $this->index = $this->items[$this->index]['next'];
    }

    public function key() {
        return $this->index;
    }

    public function valid() {
        return array_key_exists($this->index, $this->items);
    }

    public function rewind() {
        $this->index = $this->first;
    }

    // ArrayAccess

    public function offsetExists($offset) {
        return isset($this->items[$offset]);
    }

    public function offsetGet($offset) {
        return $this->items[$offset];
    }

    public function offsetSet($offset, $value) {
        if (is_null($value['prev'])) {
            $this->first = $offset;
        }

        if (is_null($value['next']))  {
            $this->last = $offset;
        }

        $this->items[$offset] = $value;
    }

    public function offsetUnset($offset) {
        if ($offset === $this->first) {
            $this->first =  $this->items[$this->first]['next'];
        }
        if ($offset === $this->last) {
            $this->last =  $this->items[$this->last]['prev'];
        }
        unset($this->items[$offset]);
    }

    // Countable

    public function count() {
        return count($this->items);
    }
}
