<?php

namespace Phnet\Builder;

class Packer
{
    protected $items = [];
    protected $directory;
    protected $phar;
    protected $manifest;

    public function __construct(string $directory, string $name)
    {
        $this->directory = $directory;
        $this->phar = new \Phar($directory.DIRECTORY_SEPARATOR.$name);
        $this->manifest = new Manifest();
    }

    public function addToPack(string $path){
        $manifest = Manifest::getFrom($path);
        $pharDirectory = dirname($path);
        foreach ($manifest['types'] as $type => $subPath){
            $this->phar->addFile("phar://{$path}/{$subPath}", $subPath);
            $this->manifest->types[$type] = $subPath;
        }

        foreach ($manifest['files'] as $path){
            copy($pharDirectory.DIRECTORY_SEPARATOR.$path, $this->directory.DIRECTORY_SEPARATOR.$path);
            $this->manifest->files[] = $path;
        }

        foreach ($manifest['resources'] as $subPath => $target){
            $this->phar->addFile("phar://{$path}/{$target}", $subPath);
            $this->manifest->resources[$subPath] = $target;
        }

        $this->phar->buildFromIterator(new \Phar($path));
    }
}