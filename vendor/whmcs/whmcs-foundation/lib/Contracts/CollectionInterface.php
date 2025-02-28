<?php

namespace WHMCS\Contracts;

interface CollectionInterface
{
    public function all();

    public function avg($key);

    public function average($key);

    public function collapse();

    public function contains($key, $value);

    public function diff($items);

    public function diffKeys($items);

    public function each($callback);

    public function every($step, $offset);

    public function except($keys);

    public function filter($callback);

    public function where($key, $value, $strict);

    public function whereIn($key, $values, $strict);

    public function first($callback, $default);

    public function flatten($depth);

    public function flip();

    public function forget($keys);

    public function get($key, $default);

    public function groupBy($groupBy, $preserveKeys);

    public function keyBy($keyBy);

    public function has($key);

    public function implode($value, $glue);

    public function intersect($items);

    public function isEmpty();

    public function keys();

    public function last($callback, $default);

    public function pluck($value, $key);

    public function map($callback);

    public function flatMap($callback);

    public function max($key);

    public function merge($items);

    public function combine($values);

    public function union($items);

    public function min($key);

    public function only($keys);

    public function forPage($page, $perPage);

    public function pop();

    public function prepend($value, $key);

    public function push($values);

    public function pull($key, $default);

    public function put($key, $value);

    public function random($amount);

    public function reduce($callback, $initial);

    public function reject($callback);

    public function reverse();

    public function search($value, $strict);

    public function shift();

    public function shuffle();

    public function slice($offset, $length);

    public function chunk($size);

    public function sort($callback);

    public function sortBy($callback, $options, $descending);

    public function sortByDesc($callback, $options);

    public function splice($offset, $length, $replacement);

    public function sum($callback);

    public function take($limit);

    public function transform($callback);

    public function unique($key);

    public function values();

    public function zip($items);

    public function toArray();

    public function jsonSerialize();

    public function toJson($options);

    public function getIterator();

    public function getCachingIterator($flags);

    public function count();

    public function offsetExists($key);

    public function offsetGet($key);

    public function offsetSet($key, $value);

    public function offsetUnset($key);

    public function __toString();
}
