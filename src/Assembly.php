<?php

namespace PharBuilder;

use FilesystemIterator;
use Phar;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Assembly
{
    protected static $_registered = [];
    protected static $_navigation = [];
    protected static $_includedPaths = [];
    protected static $_included = [];
    protected static $_isListen = false;

    public static function registerPhar(string $name, array $depends, array $navigation)
    {
        static::$_registered[$name] = [
            'name' => $name,
            'depends' => $depends
        ];

        static::$_navigation = array_merge(static::$_navigation, $navigation);
    }

    public static function includeAll()
    {
        usort(static::$_registered, function ($l, $r) {
            return in_array($l['name'], $r['depends']) ? -1 : 0;
        });

        foreach (static::$_registered as $name => $pharInfo) {
            static::includePhar($name);
        }
    }

    public static function getPath(string ...$additional): string
    {
        return dirname(Phar::running(false)) .
            (!empty($additional) ? DIRECTORY_SEPARATOR .
                implode('/', $additional)
                : '');
    }

    public static function includePhar(string $name)
    {
        if(static::$_included[$name])
            return;

        $includeIterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator("phar://{$name}/include", FilesystemIterator::SKIP_DOTS)
        );
        foreach ($includeIterator as $path) {
            if (empty(static::$_includedPaths[$path->getPathname()])) {
                require $path->getPathname();
                static::$_includedPaths[$path->getPathname()] = true;
            }
        }
        static::$_included[$name] = true;
    }

    public function includeWithDepends(string $name){
        $depends = static::$_registered[$name]['depends'];
        foreach ($depends as $depend){
            if(static::$_included[$depend])
                continue;

            require $depend;
            static::includeWithDepends($depend);
            static::includePhar($depend);
        }
        static::includePhar($name);
    }
}