<?php

namespace PharBuilder;

class Depend
{
    public $name;
    public $version;

    public function __construct($name, $version)
    {
        $this->name = $name;
        $this->version = $version;
    }
}