<?php

namespace PharBuilder;

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