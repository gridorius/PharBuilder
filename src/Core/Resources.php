<?php

namespace Phnet\Core;

class Resources
{
    protected static $list = [];

    public static function register($name, $path){
        static::$list[$name] = new Resource($name, $path);
    }

    public static function find(\Closure $callback): array{
        return array_filter(static::$list, $callback);
    }

    public static function get(string $name): ?Resource{
        return static::$list[$name];
    }

    public static function getAll(){
        return static::$list;
    }
}