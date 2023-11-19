<?php

namespace Phnet\Core;

class Assembly
{
    private static $assemblies = [];
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

    public static function init(string $directory, string $name)
    {
        static::$directory = $directory;

        $assembly = static::registerAssembly(static::includeAssembly($name));
        static::loadDependsRecursive($assembly);
        static::loadTypes();
        static::loadResources();
        static::includeFiles();
    }

    public static function initSingle(string $directory, $assembly)
    {
        static::$directory = $directory;
        static::registerAssembly($assembly);
        static::loadDependsRecursive($assembly);
        static::loadTypes();
        static::loadResources();
        static::includeFiles();
    }

    protected static function loadDependsRecursive($assembly)
    {
        foreach ($assembly->getDepends() as $name => $version) {
            if (!key_exists($name, static::$assemblies)) {
                $assembly = static::includeAssembly($name);
                static::registerAssembly($assembly);
                if ($assembly->hasDepends())
                    static::loadDependsRecursive($assembly);
            }
        }
    }

    public static function includeAssembly(string $name)
    {
        return require static::$directory . DIRECTORY_SEPARATOR . $name . '.phar';
    }

    public static function registerAssembly($assembly)
    {
        switch ($assembly->getType()) {
            case 'project':
                static::$assemblies[$assembly->getName()] = $assembly;
                if ($assembly->hasEntrypoint())
                    static::$entrypoint = $assembly;
                break;
            case 'single':
                if ($assembly->hasEntrypoint())
                    static::$entrypoint = $assembly;

                foreach ($assembly->getAssemblies() as $name => $subAssembly) {
                    static::$assemblies[$name] = $subAssembly;
                }

                break;
        }
        return $assembly;
    }

    public static function loadTypes()
    {
        foreach (static::$assemblies as $name => $assembly)
            $assembly->registerTypes();
        foreach (static::$assemblies as $name => $assembly)
            $assembly->preloadClasses();
    }

    public static function loadResources()
    {
        foreach (static::$assemblies as $assembly) {
            foreach ($assembly->getResources() as $alias => $path) {
                Resources::register($alias, $path);
            }
        }
    }

    public static function includeFiles()
    {
        foreach (static::$assemblies as $assembly) {
            foreach ($assembly->getInclude() as $path) {
                require $path;
            }
        }
    }

    public static function entrypoint($argv)
    {
        if (!empty(static::$entrypoint)) {
            static::$entrypoint->run($argv);
        }
    }
}