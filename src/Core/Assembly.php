<?php

namespace Phnet\Core;

use Phar;

class Assembly
{
    private static $assemblies = [];
    private static $types = [];
    private static $entrypoint;
    private static $directory;

    public static function getPath(string ...$additional): string
    {
        return static::$directory .
            (!empty($additional)
                ? DIRECTORY_SEPARATOR . implode('/', $additional)
                : ''
            );
    }

    public static function setDirectory(string $directory)
    {
        static::$directory = $directory;
    }

    public static function init(string $directory, string $name){
        static::$directory = $directory;

        $manifest = static::getManifest($directory, $name);
        static::registerAssembly($name, $manifest);
        static::loadDependsRecursive($manifest);
        static::loadTypes();
        static::loadResources();
    }

    protected static function loadDependsRecursive($manifest){
        foreach ($manifest['pharDepends'] as $name => $version) {
            if (!key_exists($name, static::$assemblies)) {
                $manifest = static::registerAssembly($name, static::getManifest(static::$directory, $name));
                if (!empty($manifest['pharDepends']))
                    static::loadDependsRecursive($manifest);
            }
        }
    }

    protected static function getManifest(string $directory, string $name){
        $phar = static::getPharPath($directory, $name);
        if(!file_exists($phar))
            throw new \Exception("Depends {$name} not exist");
        return json_decode(file_get_contents($phar.DIRECTORY_SEPARATOR.'/manifest.json'), true);
    }

    protected static function getPharPath(string $directory, string $name): string{
        return 'phar://'.$directory.DIRECTORY_SEPARATOR.$name.'.phar';
    }

    public static function registerAssembly(string $name, array $manifest): array
    {
        static::$assemblies[$name] = $manifest;
        if (!empty($manifest['entrypoint']))
            static::$entrypoint = $manifest['entrypoint'];

        return $manifest;
    }

    public static function loadTypes()
    {
        foreach (static::$assemblies as $name => $manifest) {
            $assemblyPath = static::getPharPath(static::$directory, $name);
            foreach ($manifest['types'] as $type => $path) {
                static::$types[$type] = $assemblyPath . DIRECTORY_SEPARATOR . $path;
            }
        }

        spl_autoload_register(function (string $entity) {
            if (key_exists($entity, static::$types))
                require static::$types[$entity];
        }, false, true);

        foreach (static::$types as $type => $path)
            class_exists($type);
    }

    public static function loadResources()
    {
        foreach (static::$assemblies as $name => $manifest) {
            $assemblyPath = static::getPharPath(static::$directory, $name);
            foreach ($manifest['resources'] as $alias => $path) {
                Resources::register($alias, $assemblyPath . DIRECTORY_SEPARATOR . $path);
            }
        }
    }

    public static function entrypoint($argv)
    {
        if (!empty(static::$entrypoint)) {
            [$class, $method] = static::$entrypoint;
            $class::$method($argv);
        }
    }
}