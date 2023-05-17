<?php

namespace Phnet\Core;

class Resource
{
    protected $name;
    protected $path;

    public function __construct(string $name, string $path)
    {
        $this->name = $name;
        $this->path = $path;
    }

    public function getPath(): string{
        return $this->path;
    }

    public function getName(){
        return $this->name;
    }
}