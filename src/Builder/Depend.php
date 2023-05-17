<?php

namespace Phnet\Builder;

class Depend
{
    public $name;
    public $version;

    public function __construct($name, $version = null)
    {
        $this->name = $name;
        $this->version = $version;
    }
}